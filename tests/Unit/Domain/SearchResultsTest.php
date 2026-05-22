<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Search\ProductMatch;
use App\Catalog\Domain\Search\RelevanceScore;
use App\Catalog\Domain\Search\SearchResults;
use PHPUnit\Framework\TestCase;

final class SearchResultsTest extends TestCase
{
    public function testAnEmptyResultSetIsCountableAndIterable(): void
    {
        $results = SearchResults::empty();

        self::assertCount(0, $results);
        self::assertTrue($results->isEmpty());
        self::assertSame([], iterator_to_array($results));
    }

    public function testItKeepsMatchesOrderedByDescendingRelevance(): void
    {
        $results = new SearchResults([
            $this->match('11111111-1111-4111-8111-111111111111', 0.91),
            $this->match('22222222-2222-4222-8222-222222222222', 0.55),
            $this->match('33333333-3333-4333-8333-333333333333', 0.10),
        ]);

        self::assertCount(3, $results);
        self::assertFalse($results->isEmpty());
    }

    public function testItRejectsMatchesThatAreNotRankedByDescendingRelevance(): void
    {
        $this->expectException(InvalidArgument::class);

        new SearchResults([
            $this->match('11111111-1111-4111-8111-111111111111', 0.20),
            $this->match('22222222-2222-4222-8222-222222222222', 0.80),
        ]);
    }

    private function match(string $id, float $score): ProductMatch
    {
        return new ProductMatch(
            ProductId::fromString($id),
            'A product',
            'Its description',
            Money::of(1000, 'EUR'),
            RelevanceScore::fromCosineSimilarity($score),
        );
    }
}
