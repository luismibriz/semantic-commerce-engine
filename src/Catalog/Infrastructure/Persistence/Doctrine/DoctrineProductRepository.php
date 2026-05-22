<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Postgres-backed write model. The Product entity stays pure: its persistence
 * is described by an out-of-domain XML mapping (config/doctrine/Product.orm.xml).
 */
final class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function ofId(ProductId $id): ?Product
    {
        return $this->entityManager->find(Product::class, $id);
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function remove(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }

    /**
     * Streams the whole catalog for read-model rebuilds. Iterating keeps the
     * identity map from holding every product at once.
     *
     * @return iterable<Product>
     */
    public function all(): iterable
    {
        $query = $this->entityManager->createQuery(
            'SELECT p FROM '.Product::class.' p ORDER BY p.indexedAt ASC'
        );

        foreach ($query->toIterable() as $product) {
            /* @var Product $product */
            yield $product;
        }
    }
}
