<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Embedding;

use RuntimeException;
use Throwable;

/**
 * Raised when the external embedding provider cannot be reached or returns an
 * unusable payload. It is an infrastructure failure, not a client error.
 */
final class EmbeddingFailed extends RuntimeException
{
    public static function provider(string $provider, string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('Embedding provider "%s" failed: %s', $provider, $reason), 0, $previous);
    }
}
