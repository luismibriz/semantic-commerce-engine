<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use Elastic\Elasticsearch\Client;

/**
 * Owns the lifecycle of the Elasticsearch index that backs semantic search:
 * its mapping (including the dense_vector field) and its creation/reset.
 */
final class ElasticsearchProductIndex implements SearchIndexInstaller
{
    public function __construct(
        private readonly Client $client,
        private readonly string $indexName,
        private readonly int $dimensions,
    ) {
    }

    public function name(): string
    {
        return $this->indexName;
    }

    public function exists(): bool
    {
        return $this->client->indices()->exists(['index' => $this->indexName])->asBool();
    }

    /**
     * Creates the index if it is missing. Idempotent, so it is safe to run on
     * every container start.
     */
    public function install(): void
    {
        if ($this->exists()) {
            return;
        }

        $this->client->indices()->create([
            'index' => $this->indexName,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'name' => ['type' => 'text'],
                        'description' => ['type' => 'text'],
                        'price_amount' => ['type' => 'integer'],
                        'price_currency' => ['type' => 'keyword'],
                        'embedding' => [
                            'type' => 'dense_vector',
                            'dims' => $this->dimensions,
                            'index' => true,
                            // Cosine similarity matches how embeddings are
                            // generated and how relevance is interpreted.
                            'similarity' => 'cosine',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Drops and recreates the index. Used before a full reindex.
     */
    public function reset(): void
    {
        if ($this->exists()) {
            $this->client->indices()->delete(['index' => $this->indexName]);
        }

        $this->install();
    }
}
