<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Infrastructure\Search\SearchIndexInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'search:setup',
    description: 'Creates the Elasticsearch index that backs semantic search.',
)]
final class SetupSearchIndexCommand extends Command
{
    public function __construct(private readonly SearchIndexInstaller $index)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Drop the index first if it already exists.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('reset')) {
            $this->index->reset();
            $io->success(\sprintf('Index "%s" was reset.', $this->index->name()));

            return Command::SUCCESS;
        }

        $this->index->install();
        $io->success(\sprintf('Index "%s" is ready.', $this->index->name()));

        return Command::SUCCESS;
    }
}
