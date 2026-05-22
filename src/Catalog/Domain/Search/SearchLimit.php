<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Search;

use App\Catalog\Domain\Exception\InvalidArgument;

/**
 * How many results a search may return. Bounded on purpose: an unbounded
 * limit would let a single request scan and rank the whole catalog.
 */
final class SearchLimit
{
    public const MIN = 1;
    public const MAX = 50;
    public const DEFAULT = 10;

    private function __construct(private readonly int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidArgument::because(\sprintf('Search limit must be between %d and %d.', self::MIN, self::MAX));
        }

        return new self($value);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function value(): int
    {
        return $this->value;
    }
}
