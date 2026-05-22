<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use DateTimeImmutable;

/**
 * Recorded when a product is deleted from the catalog. It drives the removal
 * of the product from the search read model — the mirror of ProductWasIndexed.
 */
final class ProductWasRemoved implements DomainEvent
{
    public function __construct(
        private readonly ProductId $productId,
        private readonly DateTimeImmutable $occurredOn,
    ) {
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
