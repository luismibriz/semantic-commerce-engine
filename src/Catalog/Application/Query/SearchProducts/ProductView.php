<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

/**
 * Flat, serialization-friendly read model of a single search hit. Keeping it
 * free of domain value objects means the HTTP layer can encode it directly.
 */
final class ProductView
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly float $score,
    ) {
    }

    /**
     * @return array{id: string, name: string, description: string, price: array{amount: int, currency: string}, score: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => [
                'amount' => $this->priceAmount,
                'currency' => $this->priceCurrency,
            ],
            'score' => $this->score,
        ];
    }
}
