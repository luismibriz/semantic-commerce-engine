<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Search;

use App\Catalog\Domain\Model\Product;

/**
 * Write side of the search read model: projects an indexed product into the
 * discovery store so it becomes queryable.
 *
 * Source of truth stays in the relational repository; this is a derived,
 * fully rebuildable projection — hence a port distinct from SemanticProductSearch.
 */
interface ProductSearchIndex
{
    /**
     * Upserts the product into the search store. Requires an indexed product
     * (one carrying an embedding).
     */
    public function project(Product $product): void;

    public function remove(\App\Catalog\Domain\Model\ProductId $productId): void;
}
