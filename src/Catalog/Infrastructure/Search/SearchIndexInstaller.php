<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

/**
 * Owns the physical lifecycle of whatever store backs semantic search — an
 * Elasticsearch index or a pgvector table. Implemented once per backend so the
 * console commands stay backend-agnostic.
 */
interface SearchIndexInstaller
{
    public function name(): string;

    /**
     * Creates the index/table if it does not exist. Idempotent.
     */
    public function install(): void;

    /**
     * Drops and recreates the index/table. Used before a full reindex.
     */
    public function reset(): void;
}
