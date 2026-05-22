<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Search\SearchLimit;
use App\Catalog\Infrastructure\Search\PgVectorProductIndex;
use App\Catalog\Infrastructure\Search\PgVectorProductSearch;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

/**
 * Exercises the pgvector adapter against a real PostgreSQL + pgvector instance:
 * projection, cosine-distance ranking and eviction. Skips itself when the
 * database or the `vector` extension is unavailable.
 */
final class PgVectorProductSearchIntegrationTest extends KernelTestCase
{
    private const ID_A = 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa';
    private const ID_B = 'bbbbbbbb-2222-4222-8222-bbbbbbbbbbbb';
    private const ID_C = 'cccccccc-3333-4333-8333-cccccccccccc';

    private PgVectorProductSearch $search;

    protected function setUp(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        try {
            $connection->executeQuery('SELECT 1');
            // A 3-dimensional column keeps the crafted test vectors readable.
            (new PgVectorProductIndex($connection, 3))->reset();
        } catch (Throwable $e) {
            self::markTestSkipped('PostgreSQL/pgvector is not available: '.$e->getMessage());
        }

        $this->search = new PgVectorProductSearch($connection);
    }

    public function testItRanksProjectedProductsBySemanticRelevance(): void
    {
        $this->search->project($this->indexedProduct(self::ID_A, [1.0, 0.0, 0.0]));
        $this->search->project($this->indexedProduct(self::ID_C, [0.7, 0.7, 0.0]));
        $this->search->project($this->indexedProduct(self::ID_B, [0.0, 1.0, 0.0]));

        $results = $this->search->mostRelevantTo(new EmbeddingVector([1.0, 0.0, 0.0]), SearchLimit::of(10));

        self::assertSame(
            [self::ID_A, self::ID_C, self::ID_B],
            array_map(static fn ($match): string => $match->productId()->value(), $results->all()),
        );
    }

    public function testRemovingAProductEvictsItFromSearch(): void
    {
        $this->search->project($this->indexedProduct(self::ID_A, [1.0, 0.0, 0.0]));
        $this->search->remove(ProductId::fromString(self::ID_A));

        $results = $this->search->mostRelevantTo(new EmbeddingVector([1.0, 0.0, 0.0]), SearchLimit::of(10));

        self::assertCount(0, $results);
    }

    /**
     * @param list<float> $vector
     */
    private function indexedProduct(string $id, array $vector): Product
    {
        $product = Product::register(
            ProductId::fromString($id),
            new ProductName('Producto '.$id),
            new ProductDescription('Descripción del producto '.$id),
            Money::of(1999, 'EUR'),
        );
        $product->index(new EmbeddingVector($vector), new DateTimeImmutable('2026-05-22'));

        return $product;
    }
}
