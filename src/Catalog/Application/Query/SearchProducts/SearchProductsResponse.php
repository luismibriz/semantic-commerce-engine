<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

use App\Catalog\Domain\Search\SearchQuery;
use App\Catalog\Domain\Search\SearchResults;

/**
 * Read model returned by SearchProductsHandler.
 */
final class SearchProductsResponse
{
    /**
     * @param list<ProductView> $results
     */
    private function __construct(
        public readonly string $query,
        public readonly array $results,
    ) {
    }

    public static function from(SearchQuery $query, SearchResults $results): self
    {
        $views = [];
        foreach ($results as $match) {
            $views[] = new ProductView(
                id: $match->productId()->value(),
                name: $match->name(),
                description: $match->description(),
                priceAmount: $match->price()->amount(),
                priceCurrency: $match->price()->currency(),
                score: $match->score()->value(),
            );
        }

        return new self($query->value(), $views);
    }

    /**
     * @return array{query: string, count: int, results: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'count' => \count($this->results),
            'results' => array_map(static fn (ProductView $v): array => $v->toArray(), $this->results),
        ];
    }
}
