<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Domain\Repository\ProductRepository;
use App\Catalog\Domain\Search\ProductSearchIndex;
use App\Catalog\Infrastructure\Search\SearchIndexInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rebuilds the Elasticsearch read model from Postgres, the source of truth.
 * Proves the projection is fully derivable and recoverable.
 */
#[AsCommand(
    name: 'search:reindex',
    description: 'Rebuilds the Elasticsearch read model from the relational store.',
)]
final class ReindexProductsCommand extends Command
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SearchIndexInstaller $index,
        private readonly ProductSearchIndex $searchIndex,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rebuilding the semantic search index');

        $this->index->reset();

        $projected = 0;
        $skipped = 0;
        foreach ($this->products->all() as $product) {
            if (!$product->isIndexed()) {
                ++$skipped;
                continue;
            }

            $this->searchIndex->project($product);
            ++$projected;
        }

        $io->success(\sprintf(
            'Projected %d product(s) into "%s" (%d skipped: not yet embedded).',
            $projected,
            $this->index->name(),
            $skipped,
        ));

        return Command::SUCCESS;
    }
}
