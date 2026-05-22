<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\IndexProduct;

use App\Catalog\Application\Bus\DomainEventBus;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Catalog\Domain\Service\Clock;
use App\Catalog\Domain\Service\EmbeddingGenerator;

/**
 * Use case: index a product.
 *
 * The operation is an upsert keyed by product id, so re-sending the same id
 * is idempotent in identity and simply refreshes catalog data and embedding.
 *
 * Order of effects is intentional: the embedding is generated *before* the
 * aggregate is saved, so a failing embedding provider aborts the whole use
 * case and never leaves a half-indexed product behind.
 */
final class IndexProductHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly EmbeddingGenerator $embeddings,
        private readonly DomainEventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(IndexProductCommand $command): void
    {
        $id = ProductId::fromString($command->productId);
        $name = new ProductName($command->name);
        $description = new ProductDescription($command->description);
        $price = Money::of($command->priceAmount, $command->priceCurrency);

        $product = $this->products->ofId($id);

        if (null === $product) {
            $product = Product::register($id, $name, $description, $price);
        } else {
            $product->reviseCatalogData($name, $description, $price);
        }

        $vector = $this->embeddings->embed($product->searchableText());
        $product->index($vector, $this->clock->now());

        $this->products->save($product);

        foreach ($product->releaseEvents() as $event) {
            $this->eventBus->publish($event);
        }
    }
}
