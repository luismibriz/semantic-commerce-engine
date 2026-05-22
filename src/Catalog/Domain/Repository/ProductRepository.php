<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;

/**
 * Write-side port for the Product aggregate. Implementations live in the
 * infrastructure layer; the domain only knows this contract.
 */
interface ProductRepository
{
    public function ofId(ProductId $id): ?Product;

    public function save(Product $product): void;

    public function remove(Product $product): void;

    /**
     * Streams the whole catalog. Used to rebuild the search read model from
     * the source of truth.
     *
     * @return iterable<Product>
     */
    public function all(): iterable;
}
