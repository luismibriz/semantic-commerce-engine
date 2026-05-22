<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use Doctrine\DBAL\Connection;

/**
 * Lifecycle of the pgvector read-model table. It enables the `vector`
 * extension, creates the `product_search_index` table with a `vector(N)`
 * column, and builds an HNSW index for cosine similarity so search is an
 * approximate-nearest-neighbour lookup, not a sequential scan.
 */
final class PgVectorProductIndex implements SearchIndexInstaller
{
    /** Read-model table; kept in sync with {@see PgVectorProductSearch}. */
    private const TABLE = 'product_search_index';

    public function __construct(
        private readonly Connection $connection,
        private readonly int $dimensions,
    ) {
    }

    public function name(): string
    {
        return self::TABLE;
    }

    public function install(): void
    {
        $this->connection->executeStatement('CREATE EXTENSION IF NOT EXISTS vector');

        $this->connection->executeStatement(\sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id UUID PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                price_amount INTEGER NOT NULL,
                price_currency VARCHAR(3) NOT NULL,
                embedding vector(%d) NOT NULL
            )',
            self::TABLE,
            $this->dimensions,
        ));

        $this->connection->executeStatement(\sprintf(
            'CREATE INDEX IF NOT EXISTS %1$s_embedding_hnsw
             ON %1$s USING hnsw (embedding vector_cosine_ops)',
            self::TABLE,
        ));
    }

    public function reset(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
        $this->install();
    }
}
