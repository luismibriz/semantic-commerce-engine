<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidArgument;

/**
 * A dense numeric representation of a piece of text produced by an embedding
 * model. Two vectors are "semantically close" when their cosine similarity is
 * high.
 *
 * The vector is treated as an opaque value: the domain never inspects its
 * components, it only compares whole vectors.
 */
final class EmbeddingVector
{
    /** @var list<float> */
    private readonly array $components;

    /**
     * Accepts an arbitrary array on purpose: this constructor sits at a trust
     * boundary (JSON columns, external API payloads). Every element is
     * validated below, so the public contract must not assume a clean shape.
     *
     * @param array<array-key, mixed> $components
     */
    public function __construct(array $components)
    {
        if ([] === $components) {
            throw InvalidArgument::because('An embedding vector cannot be empty.');
        }

        $normalized = [];
        foreach ($components as $component) {
            if (!\is_int($component) && !\is_float($component)) {
                throw InvalidArgument::because('An embedding vector must contain only numbers.');
            }

            if (!is_finite((float) $component)) {
                throw InvalidArgument::because('An embedding vector cannot contain NaN or infinite values.');
            }

            $normalized[] = (float) $component;
        }

        $this->components = $normalized;
    }

    public function dimensions(): int
    {
        return \count($this->components);
    }

    /**
     * @return list<float>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * Cosine similarity in the range [-1, 1]; 1 means identical orientation.
     * Returns 0.0 when either vector has zero magnitude.
     */
    public function cosineSimilarity(self $other): float
    {
        if ($this->dimensions() !== $other->dimensions()) {
            throw InvalidArgument::because(\sprintf('Cannot compare vectors of different dimensions (%d vs %d).', $this->dimensions(), $other->dimensions()));
        }

        $dot = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($this->components as $i => $a) {
            $b = $other->components[$i];
            $dot += $a * $b;
            $magnitudeA += $a * $a;
            $magnitudeB += $b * $b;
        }

        if (0.0 === $magnitudeA || 0.0 === $magnitudeB) {
            return 0.0;
        }

        return $dot / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }
}
