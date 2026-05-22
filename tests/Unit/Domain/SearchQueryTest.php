<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Search\SearchQuery;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    public function testItKeepsAndTrimsTheQueryText(): void
    {
        self::assertSame(
            'camiseta roja para el frío',
            (new SearchQuery('  camiseta roja para el frío  '))->value(),
        );
    }

    public function testItRejectsAnEmptyQuery(): void
    {
        $this->expectException(InvalidArgument::class);

        new SearchQuery('   ');
    }

    public function testItRejectsAnOverlongQuery(): void
    {
        $this->expectException(InvalidArgument::class);

        new SearchQuery(str_repeat('a', SearchQuery::MAX_LENGTH + 1));
    }
}
