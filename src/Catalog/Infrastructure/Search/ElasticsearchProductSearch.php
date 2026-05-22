<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Search\ProductMatch;
use App\Catalog\Domain\Search\ProductSearchIndex;
use App\Catalog\Domain\Search\RelevanceScore;
use App\Catalog\Domain\Search\SearchLimit;
use App\Catalog\Domain\Search\SearchResults;
use App\Catalog\Domain\Search\SemanticProductSearch;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use LogicException;

/**
 * Elasticsearch adapter for the discovery read model.
 *
 * It implements both ports: the projection side (ProductSearchIndex) that
 * keeps documents up to date, and the query side (SemanticProductSearch) that
 * runs approximate-nearest-neighbour search over the dense_vector field.
 */
final class ElasticsearchProductSearch implements SemanticProductSearch, ProductSearchIndex
{
    /**
     * Candidates to inspect per shard before ranking. Higher = better recall,
     * slower query. 10x the page size with a floor is a sane default.
     */
    private const MIN_CANDIDATES = 100;

    public function __construct(
        private readonly Client $client,
        private readonly string $indexName,
    ) {
    }

    public function project(Product $product): void
    {
        $embedding = $product->embedding();
        if (null === $embedding) {
            throw new LogicException('Cannot project a product that has not been indexed yet.');
        }

        $this->client->index([
            'index' => $this->indexName,
            'id' => $product->id()->value(),
            // wait_for makes the document searchable as soon as the call
            // returns, which keeps index-then-search flows predictable.
            'refresh' => 'wait_for',
            'body' => [
                'name' => $product->name()->value(),
                'description' => $product->description()->value(),
                'price_amount' => $product->price()->amount(),
                'price_currency' => $product->price()->currency(),
                'embedding' => $embedding->components(),
            ],
        ]);
    }

    public function remove(ProductId $productId): void
    {
        try {
            $this->client->delete([
                'index' => $this->indexName,
                'id' => $productId->value(),
                'refresh' => 'wait_for',
            ]);
        } catch (ClientResponseException $e) {
            if (404 !== $e->getCode()) {
                throw $e;
            }
        }
    }

    public function mostRelevantTo(EmbeddingVector $queryVector, SearchLimit $limit): SearchResults
    {
        $response = $this->client->search([
            'index' => $this->indexName,
            'body' => [
                'size' => $limit->value(),
                'knn' => [
                    'field' => 'embedding',
                    'query_vector' => $queryVector->components(),
                    'k' => $limit->value(),
                    'num_candidates' => max(self::MIN_CANDIDATES, $limit->value() * 10),
                ],
                '_source' => ['name', 'description', 'price_amount', 'price_currency'],
            ],
        ]);

        /** @var list<array<string, mixed>> $hits */
        $hits = $response['hits']['hits'] ?? [];

        $matches = [];
        foreach ($hits as $hit) {
            /** @var array<string, mixed> $source */
            $source = $hit['_source'];

            $matches[] = new ProductMatch(
                ProductId::fromString((string) $hit['_id']),
                (string) $source['name'],
                (string) $source['description'],
                Money::of((int) $source['price_amount'], (string) $source['price_currency']),
                RelevanceScore::fromCosineSimilarity($this->toCosine((float) $hit['_score'])),
            );
        }

        return new SearchResults($matches);
    }

    /**
     * Elasticsearch reports a cosine kNN hit as (1 + cosine) / 2 so scores are
     * always positive. This inverts that to recover the raw cosine the domain
     * RelevanceScore expects. Clamped to absorb floating-point drift.
     */
    private function toCosine(float $score): float
    {
        return max(-1.0, min(1.0, 2.0 * $score - 1.0));
    }
}
