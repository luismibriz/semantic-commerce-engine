<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use InvalidArgumentException;

/**
 * Raised when a value object or aggregate receives input that violates an
 * invariant. It deliberately extends \InvalidArgumentException so callers that
 * are not domain-aware still treat it as a client error.
 */
final class InvalidArgument extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
