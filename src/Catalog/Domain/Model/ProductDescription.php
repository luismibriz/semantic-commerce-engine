<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidArgument;

/**
 * Free-text description. It is the main signal fed to the embedding model,
 * so it must carry enough meaning to be discoverable.
 */
final class ProductDescription
{
    public const MAX_LENGTH = 5000;

    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ('' === $value) {
            throw InvalidArgument::because('Product description cannot be empty.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidArgument::because(\sprintf('Product description cannot exceed %d characters.', self::MAX_LENGTH));
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
