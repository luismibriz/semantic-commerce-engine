<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepository;

/**
 * In-memory ProductRepository for use-case tests: no database, fully
 * deterministic, exercises the exact same port the production adapter does.
 */
final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> */
    private array $products = [];

    public function ofId(ProductId $id): ?Product
    {
        return $this->products[$id->value()] ?? null;
    }

    public function save(Product $product): void
    {
        $this->products[$product->id()->value()] = $product;
    }

    public function remove(Product $product): void
    {
        unset($this->products[$product->id()->value()]);
    }

    public function all(): iterable
    {
        return array_values($this->products);
    }

    public function count(): int
    {
        return \count($this->products);
    }
}
