<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Search;

use App\Catalog\Domain\Exception\InvalidArgument;
use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * An ordered, immutable list of product matches. The list is required to be
 * sorted by descending relevance — that invariant is enforced on construction
 * so no adapter can return mis-ranked results unnoticed.
 *
 * @implements IteratorAggregate<int, ProductMatch>
 */
final class SearchResults implements IteratorAggregate, Countable
{
    /** @var list<ProductMatch> */
    private readonly array $matches;

    /**
     * @param array<array-key, ProductMatch> $matches
     */
    public function __construct(array $matches)
    {
        $previous = null;
        foreach ($matches as $match) {
            if (null !== $previous && !$previous->score()->isAtLeast($match->score())) {
                throw InvalidArgument::because('Search results must be ordered by descending relevance.');
            }
            $previous = $match;
        }

        $this->matches = array_values($matches);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return list<ProductMatch>
     */
    public function all(): array
    {
        return $this->matches;
    }

    public function count(): int
    {
        return \count($this->matches);
    }

    public function isEmpty(): bool
    {
        return [] === $this->matches;
    }

    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->matches);
    }
}
