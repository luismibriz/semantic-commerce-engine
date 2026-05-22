<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Application\Bus\CommandBus;
use App\Catalog\Application\Bus\QueryBus;
use App\Catalog\Application\Command\IndexProduct\IndexProductCommand;
use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Seeds the catalog and measures search latency, so the performance claims in
 * the README are backed by reproducible numbers rather than assertions.
 *
 * It runs through the real use cases (the CQRS buses), so the figures include
 * everything a request pays: query embedding + the vector store round-trip.
 * Run it against each SEARCH_BACKEND to compare them on equal terms.
 */
#[AsCommand(
    name: 'app:benchmark',
    description: 'Seeds products and measures end-to-end search latency.',
)]
final class BenchmarkCommand extends Command
{
    /** @var list<string> */
    private const ADJECTIVES = ['térmico', 'transpirable', 'impermeable', 'ligero', 'reflectante', 'acolchado'];

    /** @var list<string> */
    private const ITEMS = ['maillot', 'culote', 'chaqueta', 'guantes', 'calcetines', 'casco', 'bidón', 'zapatillas'];

    /** @var list<string> */
    private const CONTEXTS = ['para invierno', 'para ciclismo de montaña', 'para rutas largas', 'para el frío', 'para competición', 'para entrenamiento'];

    /** @var list<string> */
    private const QUERIES = [
        'ropa de abrigo para rodar con frío',
        'algo ligero y transpirable para verano',
        'equipación impermeable para la lluvia',
        'qué llevar para una marcha larga',
        'protección para la cabeza en mountain bike',
        'hidratación para entrenamientos intensos',
    ];

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('products', null, InputOption::VALUE_REQUIRED, 'Number of products to index', '200');
        $this->addOption('searches', null, InputOption::VALUE_REQUIRED, 'Number of search queries to run', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $products = max(1, (int) $input->getOption('products'));
        $searches = max(1, (int) $input->getOption('searches'));

        $io->title('Semantic search benchmark');

        $io->section(\sprintf('Indexing %d products', $products));
        $io->progressStart($products);
        for ($i = 0; $i < $products; ++$i) {
            $this->commandBus->dispatch(new IndexProductCommand(
                Uuid::v4()->toRfc4122(),
                $this->name($i),
                $this->description($i),
                random_int(500, 30000),
                'EUR',
            ));
            $io->progressAdvance();
        }
        $io->progressFinish();

        $io->section(\sprintf('Running %d searches', $searches));
        /** @var list<float> $latencies */
        $latencies = [];
        for ($i = 0; $i < $searches; ++$i) {
            $query = self::QUERIES[$i % \count(self::QUERIES)];

            $start = hrtime(true);
            $this->queryBus->ask(new SearchProductsQuery($query, 10));
            $latencies[] = (hrtime(true) - $start) / 1_000_000.0;
        }
        sort($latencies);

        $total = array_sum($latencies);
        $io->table(
            ['Metric', 'Value'],
            [
                ['samples', (string) \count($latencies)],
                ['min', $this->ms($latencies[0])],
                ['p50', $this->ms($this->percentile($latencies, 50))],
                ['p95', $this->ms($this->percentile($latencies, 95))],
                ['p99', $this->ms($this->percentile($latencies, 99))],
                ['max', $this->ms($latencies[\count($latencies) - 1])],
                ['mean', $this->ms($total / \count($latencies))],
                ['throughput', \sprintf('%.0f searches/s', \count($latencies) / ($total / 1000.0))],
            ],
        );

        $io->success('Benchmark complete.');

        return Command::SUCCESS;
    }

    private function name(int $seed): string
    {
        return ucfirst(self::ITEMS[$seed % \count(self::ITEMS)])
            .' '.self::ADJECTIVES[$seed % \count(self::ADJECTIVES)];
    }

    private function description(int $seed): string
    {
        return \sprintf(
            '%s %s %s, diseñado %s.',
            ucfirst(self::ITEMS[$seed % \count(self::ITEMS)]),
            self::ADJECTIVES[$seed % \count(self::ADJECTIVES)],
            self::ADJECTIVES[($seed + 3) % \count(self::ADJECTIVES)],
            self::CONTEXTS[$seed % \count(self::CONTEXTS)],
        );
    }

    /**
     * @param list<float> $sorted ascending latencies
     */
    private function percentile(array $sorted, int $percentile): float
    {
        $rank = (int) ceil($percentile / 100 * \count($sorted)) - 1;

        return $sorted[max(0, min($rank, \count($sorted) - 1))];
    }

    private function ms(float $value): string
    {
        return \sprintf('%.2f ms', $value);
    }
}
