<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Search\SearchLimit;
use PHPUnit\Framework\TestCase;

final class SearchLimitTest extends TestCase
{
    public function testItAcceptsAValueWithinBounds(): void
    {
        self::assertSame(25, SearchLimit::of(25)->value());
    }

    public function testTheDefaultLimitIsApplied(): void
    {
        self::assertSame(SearchLimit::DEFAULT, SearchLimit::default()->value());
    }

    public function testItRejectsAValueBelowTheMinimum(): void
    {
        $this->expectException(InvalidArgument::class);

        SearchLimit::of(0);
    }

    public function testItRejectsAValueAboveTheMaximum(): void
    {
        $this->expectException(InvalidArgument::class);

        SearchLimit::of(SearchLimit::MAX + 1);
    }
}
