<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Catalog\Application\Query\SearchProducts\SearchProductsHandler;
use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Tests\Doubles\InMemorySemanticProductSearch;
use App\Tests\Doubles\PredefinedEmbeddingGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SearchProductsHandlerTest extends TestCase
{
    private const ID_A = 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa';
    private const ID_B = 'bbbbbbbb-2222-4222-8222-bbbbbbbbbbbb';
    private const ID_C = 'cccccccc-3333-4333-8333-cccccccccccc';

    private InMemorySemanticProductSearch $search;

    protected function setUp(): void
    {
        $this->search = new InMemorySemanticProductSearch();

        // Crafted embeddings: A is parallel to the query, C is 45° away,
        // B is orthogonal — so the expected ranking is A, C, B.
        $this->indexProduct(self::ID_A, [1.0, 0.0, 0.0]);
        $this->indexProduct(self::ID_C, [0.7, 0.7, 0.0]);
        $this->indexProduct(self::ID_B, [0.0, 1.0, 0.0]);
    }

    public function testItReturnsProductsRankedBySemanticRelevance(): void
    {
        $handler = $this->handlerForQueryVector('camiseta roja para el frío', [1.0, 0.0, 0.0]);

        $response = $handler(new SearchProductsQuery('camiseta roja para el frío'));

        self::assertSame(
            [self::ID_A, self::ID_C, self::ID_B],
            array_map(static fn ($view): string => $view->id, $response->results),
        );

        $scores = array_map(static fn ($view): float => $view->score, $response->results);
        self::assertEqualsWithDelta(1.0, $scores[0], 1e-9);
        self::assertGreaterThan($scores[1], $scores[0]);
        self::assertGreaterThan($scores[2], $scores[1]);
    }

    public function testItRespectsTheRequestedLimit(): void
    {
        $handler = $this->handlerForQueryVector('camiseta', [1.0, 0.0, 0.0]);

        $response = $handler(new SearchProductsQuery('camiseta', 2));

        self::assertCount(2, $response->results);
        self::assertSame([self::ID_A, self::ID_C], array_map(static fn ($v): string => $v->id, $response->results));
    }

    public function testItRejectsAnEmptyQuery(): void
    {
        $handler = $this->handlerForQueryVector('unused', [1.0, 0.0, 0.0]);

        $this->expectException(InvalidArgument::class);

        $handler(new SearchProductsQuery('   '));
    }

    public function testItRejectsAnOutOfRangeLimit(): void
    {
        $handler = $this->handlerForQueryVector('camiseta', [1.0, 0.0, 0.0]);

        $this->expectException(InvalidArgument::class);

        $handler(new SearchProductsQuery('camiseta', 999));
    }

    /**
     * @param list<float> $queryVector
     */
    private function handlerForQueryVector(string $text, array $queryVector): SearchProductsHandler
    {
        return new SearchProductsHandler(
            new PredefinedEmbeddingGenerator([$text => $queryVector]),
            $this->search,
        );
    }

    /**
     * @param list<float> $vector
     */
    private function indexProduct(string $id, array $vector): void
    {
        $product = Product::register(
            ProductId::fromString($id),
            new ProductName('Producto '.$id),
            new ProductDescription('Descripción del producto '.$id),
            Money::of(1999, 'EUR'),
        );
        $product->index(new EmbeddingVector($vector), new DateTimeImmutable('2026-05-21'));

        $this->search->project($product);
    }
}
