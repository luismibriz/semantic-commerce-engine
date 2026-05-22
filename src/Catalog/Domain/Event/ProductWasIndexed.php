<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use DateTimeImmutable;

/**
 * Recorded whenever a product receives a fresh embedding and becomes
 * discoverable through semantic search.
 */
final class ProductWasIndexed implements DomainEvent
{
    public function __construct(
        private readonly ProductId $productId,
        private readonly int $dimensions,
        private readonly DateTimeImmutable $occurredOn,
    ) {
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
