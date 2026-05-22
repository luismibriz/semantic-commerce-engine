<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

/**
 * Intent: find the products that best match a natural-language query.
 */
final class SearchProductsQuery
{
    public function __construct(
        public readonly string $text,
        public readonly ?int $limit = null,
    ) {
    }
}
