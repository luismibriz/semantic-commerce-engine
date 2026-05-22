<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Search;

use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\ProductId;

/**
 * A single hit of a semantic search: a product plus how relevant it is to the
 * query. This is a read model — it is never loaded as an aggregate.
 */
final class ProductMatch
{
    public function __construct(
        private readonly ProductId $productId,
        private readonly string $name,
        private readonly string $description,
        private readonly Money $price,
        private readonly RelevanceScore $score,
    ) {
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function score(): RelevanceScore
    {
        return $this->score;
    }
}
