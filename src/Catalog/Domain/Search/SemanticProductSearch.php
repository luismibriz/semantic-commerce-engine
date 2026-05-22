<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Search;

use App\Catalog\Domain\Model\EmbeddingVector;

/**
 * Read-side port of the discovery engine. Given a query embedding it returns
 * the most relevant products, already ranked.
 *
 * It is deliberately separate from ProductRepository: the write model (the
 * Product aggregate) and the read model (ProductMatch) have different shapes
 * and different reasons to change — this is the CQRS seam.
 */
interface SemanticProductSearch
{
    public function mostRelevantTo(EmbeddingVector $queryVector, SearchLimit $limit): SearchResults;
}
