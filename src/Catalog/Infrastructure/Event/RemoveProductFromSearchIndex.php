<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Event;

use App\Catalog\Domain\Event\ProductWasRemoved;
use App\Catalog\Domain\Search\ProductSearchIndex;

/**
 * Projection: when a product is removed from the write model, evict it from
 * the search read model.
 *
 * Unlike the indexing projector, this one does not load the aggregate — it has
 * already been deleted. The event carries the id, which is all the eviction
 * needs.
 */
final class RemoveProductFromSearchIndex
{
    public function __construct(private readonly ProductSearchIndex $searchIndex)
    {
    }

    public function __invoke(ProductWasRemoved $event): void
    {
        $this->searchIndex->remove($event->productId());
    }
}
