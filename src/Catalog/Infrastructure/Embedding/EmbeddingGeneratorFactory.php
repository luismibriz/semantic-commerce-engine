<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Embedding;

use App\Catalog\Domain\Service\EmbeddingGenerator;
use InvalidArgumentException;

/**
 * Selects the active embedding provider from configuration, so the rest of
 * the system depends only on the EmbeddingGenerator port.
 */
final class EmbeddingGeneratorFactory
{
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_HASHING = 'hashing';

    public function __construct(
        private readonly OpenAiEmbeddingGenerator $openAi,
        private readonly HashingEmbeddingGenerator $hashing,
    ) {
    }

    public function create(string $provider): EmbeddingGenerator
    {
        return match (strtolower(trim($provider))) {
            self::PROVIDER_OPENAI => $this->openAi,
            self::PROVIDER_HASHING => $this->hashing,
            default => throw new InvalidArgumentException(\sprintf('Unknown embedding provider "%s". Expected "%s" or "%s".', $provider, self::PROVIDER_OPENAI, self::PROVIDER_HASHING)),
        };
    }
}
