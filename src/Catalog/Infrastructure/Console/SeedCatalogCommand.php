<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Application\Bus\CommandBus;
use App\Catalog\Application\Command\IndexProduct\IndexProductCommand;
use App\Catalog\Domain\Repository\ProductRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds a small, curated demo catalogue so the API has something to search
 * the moment the stack is up. It drives the same CommandBus the HTTP API
 * uses, so seeded products are indistinguishable from products posted via
 * `POST /api/products` — they go through the full write-side use case,
 * embed and project into the read model.
 *
 * Idempotent by default: if the catalogue already contains products it does
 * nothing, so it is safe to call from the container entrypoint on every
 * boot. Pass `--force` to seed anyway (the deterministic UUIDs below make
 * the writes upserts, not duplicates).
 */
#[AsCommand(
    name: 'app:seed',
    description: 'Seeds a small demo catalogue (idempotent unless --force).',
)]
final class SeedCatalogCommand extends Command
{
    /**
     * @var list<array{id: string, name: string, description: string, amount: int, currency: string}>
     *
     * The descriptions are deliberately wordy and concrete — the search is
     * semantic, so giving it real context ("para el frío", "ventilado",
     * "lluvia") is what makes the demo queries land on the right product
     */
    private const PRODUCTS = [
        [
            'id' => '11111111-1111-4111-8111-000000000001',
            'name' => 'Gafas Tech aurora',
            'description' => 'Gafas deportivas con lente fotocromática roja para ciclismo de carretera y running. Se adaptan a la luz, protegen del viento y caben bajo el casco.',
            'amount' => 8999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000002',
            'name' => 'Maillot térmico invierno',
            'description' => 'Camiseta térmica de manga larga, color rojo, para ciclismo en frío. Tejido interior afelpado, cuello alto y bolsillos traseros.',
            'amount' => 7499,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000003',
            'name' => 'Culote acolchado de larga distancia',
            'description' => 'Culote con tirantes y badana de alta densidad pensada para rutas de varias horas. Tejido transpirable, costuras planas para evitar rozaduras.',
            'amount' => 11999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000004',
            'name' => 'Chaqueta impermeable plegable',
            'description' => 'Chaqueta cortavientos impermeable que se pliega al tamaño de un bolsillo. Costuras termoselladas, capucha ajustable, ideal para días de lluvia.',
            'amount' => 13999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000005',
            'name' => 'Casco aero ventilado',
            'description' => 'Casco aerodinámico con 18 ventilaciones para entrenamientos de verano. Carcasa in-mold, correa Fidlock y sistema de ajuste en altura.',
            'amount' => 14999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000006',
            'name' => 'Guantes de invierno windstopper',
            'description' => 'Guantes largos cortavientos para temperaturas bajo cero. Palma con grip de silicona, dedo índice y pulgar compatibles con pantalla táctil.',
            'amount' => 3999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000007',
            'name' => 'Calcetines de compresión técnica',
            'description' => 'Calcetines de compresión graduada que mejoran el retorno venoso en entrenamientos largos y carreras de fondo. Punto reforzado en talón y puntera.',
            'amount' => 1999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000008',
            'name' => 'Bidón aislante 750 ml',
            'description' => 'Bidón térmico de doble pared que mantiene la bebida fría durante 4 horas. Boquilla high-flow, base antideslizante, apto para porta-bidones estándar.',
            'amount' => 1499,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000009',
            'name' => 'Zapatillas trail con grip agresivo',
            'description' => 'Zapatillas de trail running con suela Vibram y tacos profundos para terreno técnico y barro. Drop bajo, mediasuela con respuesta.',
            'amount' => 12999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000010',
            'name' => 'Mallas largas térmicas',
            'description' => 'Mallas térmicas para running y fitness en frío. Tejido brushed por dentro, cintura ancha, bolsillo trasero con cremallera para llaves.',
            'amount' => 5499,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000011',
            'name' => 'Mochila de hidratación 6 L',
            'description' => 'Mochila ligera con depósito de hidratación de 1,5 L para mountain bike y trail. Tirantes ergonómicos, ventilación en espalda y bolsillos elásticos.',
            'amount' => 6999,
            'currency' => 'EUR',
        ],
        [
            'id' => '11111111-1111-4111-8111-000000000012',
            'name' => 'Top deportivo de alto impacto',
            'description' => 'Top de sujeción alta para running y entrenamientos de impacto. Tejido elástico y transpirable, copa moldeada, espalda nadadora.',
            'amount' => 3499,
            'currency' => 'EUR',
        ],
    ];

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly ProductRepository $products,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Seed even if the catalogue already has products (writes upsert by deterministic UUID).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        if (!$force && $this->catalogIsAlreadyPopulated()) {
            $io->note('Catalogue already populated; skipping seed (use --force to re-seed).');

            return Command::SUCCESS;
        }

        $io->title('Seeding demo catalogue');

        foreach (self::PRODUCTS as $product) {
            $this->commandBus->dispatch(new IndexProductCommand(
                $product['id'],
                $product['name'],
                $product['description'],
                $product['amount'],
                $product['currency'],
            ));
        }

        $io->success(\sprintf('Seeded %d product(s).', \count(self::PRODUCTS)));

        return Command::SUCCESS;
    }

    private function catalogIsAlreadyPopulated(): bool
    {
        foreach ($this->products->all() as $_) {
            return true;
        }

        return false;
    }
}
