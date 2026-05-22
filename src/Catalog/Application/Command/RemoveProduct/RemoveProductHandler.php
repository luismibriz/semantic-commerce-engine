<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\RemoveProduct;

use App\Catalog\Application\Bus\DomainEventBus;
use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Catalog\Domain\Service\Clock;

/**
 * Use case: remove a product.
 *
 * Symmetric with IndexProductHandler: it mutates only the write model and
 * records a ProductWasRemoved event. A projector — not this handler — is what
 * later evicts the product from the search read model, so the use case stays
 * unaware that a read store exists (the same CQRS rule as indexing).
 */
final class RemoveProductHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly DomainEventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RemoveProductCommand $command): void
    {
        $id = ProductId::fromString($command->productId);

        $product = $this->products->ofId($id);
        if (null === $product) {
            throw ProductNotFound::withId($id->value());
        }

        $product->remove($this->clock->now());
        $this->products->remove($product);

        foreach ($product->releaseEvents() as $event) {
            $this->eventBus->publish($event);
        }
    }
}
