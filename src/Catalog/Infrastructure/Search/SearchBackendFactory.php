<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use App\Catalog\Domain\Search\ProductSearchIndex;
use App\Catalog\Domain\Search\SemanticProductSearch;
use InvalidArgumentException;

/**
 * Selects the active vector-search backend from configuration (SEARCH_BACKEND).
 *
 * Both backends implement the very same domain ports — `SemanticProductSearch`
 * and `ProductSearchIndex` — so the choice is a pure infrastructure detail:
 * the domain, the use cases, the HTTP endpoints and the tests never change.
 */
final class SearchBackendFactory
{
    public const ELASTICSEARCH = 'elasticsearch';
    public const PGVECTOR = 'pgvector';

    public function __construct(
        private readonly ElasticsearchProductSearch $elasticsearchSearch,
        private readonly ElasticsearchProductIndex $elasticsearchInstaller,
        private readonly PgVectorProductSearch $pgVectorSearch,
        private readonly PgVectorProductIndex $pgVectorInstaller,
        private readonly string $backend,
    ) {
    }

    public function semanticSearch(): SemanticProductSearch
    {
        return $this->usePgVector() ? $this->pgVectorSearch : $this->elasticsearchSearch;
    }

    public function searchIndex(): ProductSearchIndex
    {
        return $this->usePgVector() ? $this->pgVectorSearch : $this->elasticsearchSearch;
    }

    public function installer(): SearchIndexInstaller
    {
        return $this->usePgVector() ? $this->pgVectorInstaller : $this->elasticsearchInstaller;
    }

    private function usePgVector(): bool
    {
        return match (strtolower(trim($this->backend))) {
            self::PGVECTOR => true,
            self::ELASTICSEARCH => false,
            default => throw new InvalidArgumentException(\sprintf('Unknown search backend "%s". Expected "%s" or "%s".', $this->backend, self::ELASTICSEARCH, self::PGVECTOR)),
        };
    }
}
