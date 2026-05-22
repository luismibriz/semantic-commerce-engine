<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Search;

use App\Catalog\Domain\Exception\InvalidArgument;

/**
 * How relevant a product is to a query, expressed as the cosine similarity
 * between their embeddings. Range [-1, 1]; the closer to 1, the better.
 */
final class RelevanceScore
{
    private function __construct(private readonly float $value)
    {
    }

    public static function fromCosineSimilarity(float $similarity): self
    {
        if (!is_finite($similarity)) {
            throw InvalidArgument::because('A relevance score must be a finite number.');
        }

        if ($similarity < -1.0 || $similarity > 1.0) {
            throw InvalidArgument::because('A cosine similarity must lie within [-1, 1].');
        }

        return new self($similarity);
    }

    public function value(): float
    {
        return $this->value;
    }

    public function isAtLeast(self $other): bool
    {
        return $this->value >= $other->value;
    }
}
