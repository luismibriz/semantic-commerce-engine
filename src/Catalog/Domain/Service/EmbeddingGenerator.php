<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\Model\EmbeddingVector;

/**
 * Port over the external embedding model. The domain depends on this
 * abstraction, never on a concrete provider (OpenAI, a local model, ...).
 */
interface EmbeddingGenerator
{
    /**
     * @throws \App\Catalog\Domain\Exception\InvalidArgument when $text is empty
     */
    public function embed(string $text): EmbeddingVector;
}
