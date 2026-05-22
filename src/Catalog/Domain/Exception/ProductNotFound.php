<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use RuntimeException;

/**
 * Raised when an operation targets a product id that does not exist. It is a
 * client error (the caller asked for the wrong thing) and maps to HTTP 404.
 */
final class ProductNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Product "%s" was not found.', $id));
    }
}
