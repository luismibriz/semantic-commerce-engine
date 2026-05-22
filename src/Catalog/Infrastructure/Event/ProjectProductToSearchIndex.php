<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Event;

use App\Catalog\Domain\Event\ProductWasIndexed;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Catalog\Domain\Search\ProductSearchIndex;

/**
 * Projection: when a product is indexed in the write model, push it into the
 * Elasticsearch read model so it becomes discoverable.
 *
 * Running it as an event handler keeps the use case (IndexProductHandler)
 * unaware of the read store. It executes synchronously on "event.bus": a
 * production system would move this behind an outbox to survive a search
 * store outage — see ai/DECISIONS.md.
 */
final class ProjectProductToSearchIndex
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductSearchIndex $searchIndex,
    ) {
    }

    public function __invoke(ProductWasIndexed $event): void
    {
        $product = $this->products->ofId($event->productId());

        // The aggregate may have been removed between command and projection.
        if (null === $product) {
            return;
        }

        $this->searchIndex->project($product);
    }
}
