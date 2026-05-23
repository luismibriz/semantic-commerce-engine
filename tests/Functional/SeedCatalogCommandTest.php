<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\Repository\ProductRepository;
use App\Catalog\Infrastructure\Console\SeedCatalogCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The entrypoint runs `app:seed` on every boot, so idempotence is the
 * contract that matters: a second run must not duplicate inserts, and
 * --force must upsert (deterministic UUIDs) rather than create new rows.
 *
 * The full pipeline is exercised — the real CommandBus, IndexProductHandler,
 * EmbeddingGenerator and projector — against the in-memory ProductRepository
 * wired in `config/services_test.yaml`. The DoctrineProductRepository round-
 * trip is covered separately under tests/Integration.
 */
final class SeedCatalogCommandTest extends KernelTestCase
{
    private CommandTester $tester;
    private ProductRepository $products;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $command = $container->get(SeedCatalogCommand::class);
        self::assertInstanceOf(SeedCatalogCommand::class, $command);
        $this->tester = new CommandTester($command);

        $products = $container->get(ProductRepository::class);
        self::assertInstanceOf(ProductRepository::class, $products);
        $this->products = $products;
    }

    public function testItSeedsTwelveProductsOnAnEmptyCatalogue(): void
    {
        $this->tester->execute([]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Seeded 12 product(s).', $this->tester->getDisplay());
        self::assertSame(12, $this->countProducts());
    }

    public function testItIsIdempotentWhenRunTwice(): void
    {
        $this->tester->execute([]);
        $this->tester->execute([]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Catalogue already populated', $this->tester->getDisplay());
        self::assertSame(12, $this->countProducts(), 'No duplicate inserts on the second run.');
    }

    public function testForceReSeedsWithoutDuplicating(): void
    {
        // Deterministic UUIDs make --force an upsert, not a duplicator: after
        // a forced second pass the count is still 12.
        $this->tester->execute([]);
        $this->tester->execute(['--force' => true]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Seeded 12 product(s).', $this->tester->getDisplay());
        self::assertSame(12, $this->countProducts());
    }

    private function countProducts(): int
    {
        $count = 0;
        foreach ($this->products->all() as $_) {
            ++$count;
        }

        return $count;
    }
}
