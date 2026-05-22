<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Service\EmbeddingGenerator;
use RuntimeException;

/**
 * Returns hand-crafted vectors for known input strings, giving search tests
 * full control over relevance ordering. Unknown input fails loudly so a test
 * can never silently rely on an embedding it did not define.
 */
final class PredefinedEmbeddingGenerator implements EmbeddingGenerator
{
    /** @var array<string, list<float>> */
    private array $map;

    /**
     * @param array<string, list<float>> $map text => vector components
     */
    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function embed(string $text): EmbeddingVector
    {
        $text = trim($text);

        if (!isset($this->map[$text])) {
            throw new RuntimeException(\sprintf('No predefined embedding registered for "%s".', $text));
        }

        return new EmbeddingVector($this->map[$text]);
    }
}
