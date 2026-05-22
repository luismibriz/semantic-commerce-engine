<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Infrastructure\Embedding\HashingEmbeddingGenerator;
use PHPUnit\Framework\TestCase;

final class HashingEmbeddingGeneratorTest extends TestCase
{
    public function testItProducesVectorsOfTheConfiguredDimension(): void
    {
        $generator = new HashingEmbeddingGenerator(128);

        self::assertSame(128, $generator->embed('camiseta térmica')->dimensions());
    }

    public function testItIsDeterministic(): void
    {
        $generator = new HashingEmbeddingGenerator(64);

        self::assertSame(
            $generator->embed('bidón de agua')->components(),
            $generator->embed('bidón de agua')->components(),
        );
    }

    public function testItProducesUnitVectors(): void
    {
        $generator = new HashingEmbeddingGenerator(64);

        $magnitude = 0.0;
        foreach ($generator->embed('guantes de invierno')->components() as $component) {
            $magnitude += $component * $component;
        }

        self::assertEqualsWithDelta(1.0, sqrt($magnitude), 1e-9);
    }

    public function testTextsSharingWordsAreCloserThanUnrelatedTexts(): void
    {
        $generator = new HashingEmbeddingGenerator(512);

        $reference = $generator->embed('camiseta térmica roja para ciclismo');
        $related = $generator->embed('camiseta térmica roja de invierno');
        $unrelated = $generator->embed('bomba de aire para bicicleta');

        self::assertGreaterThan(
            $reference->cosineSimilarity($unrelated),
            $reference->cosineSimilarity($related),
        );
    }

    public function testItRejectsEmptyText(): void
    {
        $this->expectException(InvalidArgument::class);

        (new HashingEmbeddingGenerator(64))->embed('   ');
    }

    public function testItRejectsANonPositiveDimension(): void
    {
        $this->expectException(InvalidArgument::class);

        new HashingEmbeddingGenerator(0);
    }
}
