<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Search\ProductMatch;
use App\Catalog\Domain\Search\ProductSearchIndex;
use App\Catalog\Domain\Search\RelevanceScore;
use App\Catalog\Domain\Search\SearchLimit;
use App\Catalog\Domain\Search\SearchResults;
use App\Catalog\Domain\Search\SemanticProductSearch;
use LogicException;

/**
 * In-memory stand-in for the Elasticsearch adapter. It implements both search
 * ports and computes cosine similarity directly, so use-case tests can assert
 * ranking behaviour without a running search cluster.
 */
final class InMemorySemanticProductSearch implements SemanticProductSearch, ProductSearchIndex
{
    /** @var array<string, Product> */
    private array $indexed = [];

    public function project(Product $product): void
    {
        if (!$product->isIndexed()) {
            throw new LogicException('Cannot project a product without an embedding.');
        }

        $this->indexed[$product->id()->value()] = $product;
    }

    public function remove(ProductId $productId): void
    {
        unset($this->indexed[$productId->value()]);
    }

    public function mostRelevantTo(EmbeddingVector $queryVector, SearchLimit $limit): SearchResults
    {
        $matches = [];

        foreach ($this->indexed as $product) {
            $embedding = $product->embedding();
            if (null === $embedding) {
                continue;
            }

            $cosine = max(-1.0, min(1.0, $queryVector->cosineSimilarity($embedding)));

            $matches[] = new ProductMatch(
                $product->id(),
                $product->name()->value(),
                $product->description()->value(),
                $product->price(),
                RelevanceScore::fromCosineSimilarity($cosine),
            );
        }

        usort(
            $matches,
            static fn (ProductMatch $a, ProductMatch $b): int => $b->score()->value() <=> $a->score()->value(),
        );

        return new SearchResults(\array_slice($matches, 0, $limit->value()));
    }
}
