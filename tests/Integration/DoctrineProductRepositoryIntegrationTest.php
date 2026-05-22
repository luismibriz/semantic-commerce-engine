<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Infrastructure\Persistence\Doctrine\DoctrineProductRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

/**
 * Exercises the real Doctrine adapter against PostgreSQL: it proves the pure
 * Product entity and its value objects round-trip through the XML mapping and
 * the custom DBAL types. Skips itself when no database is reachable, so the
 * default `composer test` run stays green without infrastructure.
 */
final class DoctrineProductRepositoryIntegrationTest extends KernelTestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';

    private EntityManagerInterface $entityManager;
    private DoctrineProductRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('PostgreSQL is not reachable: '.$e->getMessage());
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->repository = new DoctrineProductRepository($this->entityManager);
    }

    public function testItRoundTripsAnIndexedProduct(): void
    {
        $product = Product::register(
            ProductId::fromString(self::ID),
            new ProductName('Maillot térmico'),
            new ProductDescription('Camiseta térmica roja para el frío'),
            Money::of(5999, 'EUR'),
        );
        $product->index(new EmbeddingVector([0.1, 0.2, 0.3]), new DateTimeImmutable('2026-05-22 10:00:00'));

        $this->repository->save($product);
        // Detach everything so the read genuinely hits the database.
        $this->entityManager->clear();

        $loaded = $this->repository->ofId(ProductId::fromString(self::ID));

        self::assertNotNull($loaded);
        self::assertSame('Maillot térmico', $loaded->name()->value());
        self::assertSame('Camiseta térmica roja para el frío', $loaded->description()->value());
        self::assertTrue($loaded->price()->equals(Money::of(5999, 'EUR')));
        self::assertTrue($loaded->isIndexed());
        self::assertSame([0.1, 0.2, 0.3], $loaded->embedding()?->components());
    }

    public function testItRemovesAProduct(): void
    {
        $product = Product::register(
            ProductId::fromString(self::ID),
            new ProductName('Producto'),
            new ProductDescription('Descripción del producto'),
            Money::of(1000, 'EUR'),
        );
        $this->repository->save($product);

        $this->repository->remove($product);
        $this->entityManager->clear();

        self::assertNull($this->repository->ofId(ProductId::fromString(self::ID)));
    }
}
