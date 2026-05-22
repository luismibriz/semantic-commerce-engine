<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Embedding;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Service\EmbeddingGenerator;

/**
 * Offline, deterministic embedding generator based on the hashing trick.
 *
 * It is NOT a semantic model: it projects token occurrences into a fixed
 * vector space, so similarity reflects lexical overlap, not meaning. Its
 * purpose is twofold:
 *   - let the whole stack run with `docker compose up` without any API key;
 *   - give the test suite a fully deterministic, fast embedding source.
 *
 * Production semantic search must use OpenAiEmbeddingGenerator.
 */
final class HashingEmbeddingGenerator implements EmbeddingGenerator
{
    public function __construct(private readonly int $dimensions)
    {
        if ($dimensions < 1) {
            throw InvalidArgument::because('Embedding dimensions must be positive.');
        }
    }

    public function embed(string $text): EmbeddingVector
    {
        $text = trim($text);
        if ('' === $text) {
            throw InvalidArgument::because('Cannot embed empty text.');
        }

        $vector = array_fill(0, $this->dimensions, 0.0);

        foreach ($this->tokenize($text) as $token) {
            // Two independent hashes: one for the bucket, one for the sign,
            // which keeps the projection roughly unbiased.
            $bucket = (int) (hexdec(substr(md5($token), 0, 8)) % $this->dimensions);
            $sign = 0 === (hexdec(substr(md5('s'.$token), 0, 8)) % 2) ? 1.0 : -1.0;
            $vector[$bucket] += $sign;
        }

        return new EmbeddingVector($this->normalize($vector));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $lower = mb_strtolower($text);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, \PREG_SPLIT_NO_EMPTY);

        return false === $tokens ? [] : $tokens;
    }

    /**
     * @param list<float> $vector
     *
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $magnitude = 0.0;
        foreach ($vector as $component) {
            $magnitude += $component * $component;
        }

        if (0.0 === $magnitude) {
            // No alphanumeric tokens: fall back to a stable non-zero unit vector.
            $vector[0] = 1.0;

            return $vector;
        }

        $magnitude = sqrt($magnitude);

        return array_map(static fn (float $c): float => $c / $magnitude, $vector);
    }
}
