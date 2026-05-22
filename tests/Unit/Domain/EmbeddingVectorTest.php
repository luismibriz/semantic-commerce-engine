<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\EmbeddingVector;
use PHPUnit\Framework\TestCase;

final class EmbeddingVectorTest extends TestCase
{
    public function testItExposesItsDimensionsAndComponents(): void
    {
        $vector = new EmbeddingVector([0.1, 0.2, 0.3]);

        self::assertSame(3, $vector->dimensions());
        self::assertSame([0.1, 0.2, 0.3], $vector->components());
    }

    public function testItCastsIntegerComponentsToFloat(): void
    {
        self::assertSame([1.0, 0.0], (new EmbeddingVector([1, 0]))->components());
    }

    public function testItRejectsAnEmptyVector(): void
    {
        $this->expectException(InvalidArgument::class);

        new EmbeddingVector([]);
    }

    public function testItRejectsNonFiniteComponents(): void
    {
        $this->expectException(InvalidArgument::class);

        new EmbeddingVector([1.0, \INF]);
    }

    public function testCosineSimilarityOfIdenticalVectorsIsOne(): void
    {
        $vector = new EmbeddingVector([0.4, 0.3, 0.2]);

        self::assertEqualsWithDelta(1.0, $vector->cosineSimilarity($vector), 1e-9);
    }

    public function testCosineSimilarityOfOrthogonalVectorsIsZero(): void
    {
        $a = new EmbeddingVector([1.0, 0.0]);
        $b = new EmbeddingVector([0.0, 1.0]);

        self::assertEqualsWithDelta(0.0, $a->cosineSimilarity($b), 1e-9);
    }

    public function testCosineSimilarityOfOppositeVectorsIsMinusOne(): void
    {
        $a = new EmbeddingVector([1.0, 1.0]);
        $b = new EmbeddingVector([-1.0, -1.0]);

        self::assertEqualsWithDelta(-1.0, $a->cosineSimilarity($b), 1e-9);
    }

    public function testCosineSimilarityWithAZeroVectorIsZero(): void
    {
        $a = new EmbeddingVector([0.0, 0.0]);
        $b = new EmbeddingVector([1.0, 1.0]);

        self::assertSame(0.0, $a->cosineSimilarity($b));
    }

    public function testItRejectsComparingVectorsOfDifferentDimensions(): void
    {
        $this->expectException(InvalidArgument::class);

        (new EmbeddingVector([1.0, 2.0]))->cosineSimilarity(new EmbeddingVector([1.0, 2.0, 3.0]));
    }
}
