<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\IndexProduct;

/**
 * Intent: register (or refresh) a product and make it discoverable by
 * generating its embedding.
 *
 * It carries only primitives so it can cross any boundary (HTTP, CLI, queue)
 * without dragging domain types along. Value objects are built inside the
 * handler, where validation belongs.
 */
final class IndexProductCommand
{
    public function __construct(
        public readonly string $productId,
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
    ) {
    }
}
