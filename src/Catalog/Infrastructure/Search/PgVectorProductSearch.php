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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use LogicException;

/**
 * pgvector adapter for the discovery read model — the alternative to
 * Elasticsearch, selectable via SEARCH_BACKEND.
 *
 * It uses a dedicated read-model table (`product_search_index`) so the CQRS
 * separation holds: this is a derived projection, not the write model. Ranking
 * is delegated to pgvector's cosine-distance operator over an HNSW index, so
 * the catalog is never scanned in PHP.
 */
final class PgVectorProductSearch implements SemanticProductSearch, ProductSearchIndex
{
    /** Read-model table; kept in sync with {@see PgVectorProductIndex}. */
    private const TABLE = 'product_search_index';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function project(Product $product): void
    {
        $embedding = $product->embedding();
        if (null === $embedding) {
            throw new LogicException('Cannot project a product that has not been indexed yet.');
        }

        $this->connection->executeStatement(
            'INSERT INTO '.self::TABLE.' (id, name, description, price_amount, price_currency, embedding)
             VALUES (:id, :name, :description, :priceAmount, :priceCurrency, :embedding::vector)
             ON CONFLICT (id) DO UPDATE SET
                 name = EXCLUDED.name,
                 description = EXCLUDED.description,
                 price_amount = EXCLUDED.price_amount,
                 price_currency = EXCLUDED.price_currency,
                 embedding = EXCLUDED.embedding',
            [
                'id' => $product->id()->value(),
                'name' => $product->name()->value(),
                'description' => $product->description()->value(),
                'priceAmount' => $product->price()->amount(),
                'priceCurrency' => $product->price()->currency(),
                'embedding' => $this->toVectorLiteral($embedding),
            ],
            [
                'priceAmount' => ParameterType::INTEGER,
            ],
        );
    }

    public function remove(ProductId $productId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM '.self::TABLE.' WHERE id = :id',
            ['id' => $productId->value()],
        );
    }

    public function mostRelevantTo(EmbeddingVector $queryVector, SearchLimit $limit): SearchResults
    {
        // `<=>` is pgvector's cosine distance in [0, 2]; relevance is 1 - that
        // distance. Ordering by distance ascending gives descending relevance,
        // which satisfies the SearchResults invariant.
        $rows = $this->connection->executeQuery(
            'SELECT id, name, description, price_amount, price_currency,
                    1 - (embedding <=> :vector::vector) AS score
             FROM '.self::TABLE.'
             ORDER BY embedding <=> :vector::vector
             LIMIT :limit',
            [
                'vector' => $this->toVectorLiteral($queryVector),
                'limit' => $limit->value(),
            ],
            [
                'limit' => ParameterType::INTEGER,
            ],
        )->fetchAllAssociative();

        $matches = [];
        foreach ($rows as $row) {
            $matches[] = new ProductMatch(
                ProductId::fromString((string) $row['id']),
                (string) $row['name'],
                (string) $row['description'],
                Money::of((int) $row['price_amount'], (string) $row['price_currency']),
                RelevanceScore::fromCosineSimilarity($this->clamp((float) $row['score'])),
            );
        }

        return new SearchResults($matches);
    }

    private function toVectorLiteral(EmbeddingVector $vector): string
    {
        // pgvector's text representation is identical to a JSON array: [a,b,c].
        return json_encode($vector->components(), \JSON_THROW_ON_ERROR);
    }

    /**
     * Absorbs floating-point drift so the cosine stays within RelevanceScore's
     * valid [-1, 1] range.
     */
    private function clamp(float $score): float
    {
        return max(-1.0, min(1.0, $score));
    }
}
