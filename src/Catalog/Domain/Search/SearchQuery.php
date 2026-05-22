<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Search;

use App\Catalog\Domain\Exception\InvalidArgument;

/**
 * The natural-language intent a shopper expresses, e.g. "camiseta roja para
 * el frío". It is the raw text that will be turned into an embedding.
 */
final class SearchQuery
{
    public const MAX_LENGTH = 500;

    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ('' === $value) {
            throw InvalidArgument::because('A search query cannot be empty.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidArgument::because(\sprintf('A search query cannot exceed %d characters.', self::MAX_LENGTH));
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
