<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Search\RelevanceScore;
use PHPUnit\Framework\TestCase;

final class RelevanceScoreTest extends TestCase
{
    public function testItIsBuiltFromACosineSimilarity(): void
    {
        self::assertSame(0.83, RelevanceScore::fromCosineSimilarity(0.83)->value());
    }

    public function testItAcceptsTheBoundsOfTheRange(): void
    {
        self::assertSame(1.0, RelevanceScore::fromCosineSimilarity(1.0)->value());
        self::assertSame(-1.0, RelevanceScore::fromCosineSimilarity(-1.0)->value());
    }

    public function testItRejectsAValueOutsideTheCosineRange(): void
    {
        $this->expectException(InvalidArgument::class);

        RelevanceScore::fromCosineSimilarity(1.5);
    }

    public function testItRejectsANonFiniteValue(): void
    {
        $this->expectException(InvalidArgument::class);

        RelevanceScore::fromCosineSimilarity(\NAN);
    }

    public function testItComparesScores(): void
    {
        $high = RelevanceScore::fromCosineSimilarity(0.9);
        $low = RelevanceScore::fromCosineSimilarity(0.2);

        self::assertTrue($high->isAtLeast($low));
        self::assertFalse($low->isAtLeast($high));
    }
}
