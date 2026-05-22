<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidArgument;

final class ProductName
{
    public const MAX_LENGTH = 150;

    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ('' === $value) {
            throw InvalidArgument::because('Product name cannot be empty.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidArgument::because(\sprintf('Product name cannot exceed %d characters.', self::MAX_LENGTH));
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
