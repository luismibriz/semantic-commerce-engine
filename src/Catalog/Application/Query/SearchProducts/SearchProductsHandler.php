<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

use App\Catalog\Domain\Search\SearchLimit;
use App\Catalog\Domain\Search\SearchQuery;
use App\Catalog\Domain\Search\SemanticProductSearch;
use App\Catalog\Domain\Service\EmbeddingGenerator;

/**
 * Use case: rank catalog products by semantic relevance to a free-text query.
 *
 * The query text is embedded with the *same* model used at index time, then
 * compared against the read store. Read-only: it never touches the write side.
 */
final class SearchProductsHandler
{
    public function __construct(
        private readonly EmbeddingGenerator $embeddings,
        private readonly SemanticProductSearch $search,
    ) {
    }

    public function __invoke(SearchProductsQuery $query): SearchProductsResponse
    {
        $searchQuery = new SearchQuery($query->text);
        $limit = null === $query->limit ? SearchLimit::default() : SearchLimit::of($query->limit);

        $queryVector = $this->embeddings->embed($searchQuery->value());
        $results = $this->search->mostRelevantTo($queryVector, $limit);

        return SearchProductsResponse::from($searchQuery, $results);
    }
}
