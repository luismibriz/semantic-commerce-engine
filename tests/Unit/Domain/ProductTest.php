<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Event\ProductWasIndexed;
use App\Catalog\Domain\Event\ProductWasRemoved;
use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';

    public function testANewlyRegisteredProductIsNotYetIndexed(): void
    {
        $product = $this->aProduct();

        self::assertFalse($product->isIndexed());
        self::assertNull($product->embedding());
        self::assertNull($product->indexedAt());
        self::assertSame([], $product->releaseEvents());
    }

    public function testSearchableTextCombinesNameAndDescription(): void
    {
        $product = Product::register(
            ProductId::fromString(self::ID),
            new ProductName('Camiseta térmica'),
            new ProductDescription('Ideal para ciclismo en invierno'),
            Money::of(2599, 'EUR'),
        );

        self::assertSame('Camiseta térmica. Ideal para ciclismo en invierno', $product->searchableText());
    }

    public function testIndexingAttachesTheEmbeddingAndRecordsAnEvent(): void
    {
        $product = $this->aProduct();
        $occurredOn = new DateTimeImmutable('2026-05-21 10:00:00');

        $product->index(new EmbeddingVector([0.1, 0.2, 0.3]), $occurredOn);

        self::assertTrue($product->isIndexed());
        self::assertSame($occurredOn, $product->indexedAt());

        $events = $product->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductWasIndexed::class, $events[0]);
        self::assertTrue($events[0]->productId()->equals($product->id()));
        self::assertSame(3, $events[0]->dimensions());
    }

    public function testReleasingEventsClearsThem(): void
    {
        $product = $this->aProduct();
        $product->index(new EmbeddingVector([1.0]), new DateTimeImmutable());

        self::assertCount(1, $product->releaseEvents());
        self::assertSame([], $product->releaseEvents());
    }

    public function testRevisingCatalogDataInvalidatesTheEmbedding(): void
    {
        $product = $this->aProduct();
        $product->index(new EmbeddingVector([1.0, 2.0]), new DateTimeImmutable());
        $product->releaseEvents();

        $product->reviseCatalogData(
            new ProductName('Nuevo nombre'),
            new ProductDescription('Nueva descripción'),
            Money::of(100, 'EUR'),
        );

        self::assertFalse($product->isIndexed());
        self::assertNull($product->embedding());
    }

    public function testRemovingRecordsAProductWasRemovedEvent(): void
    {
        $product = $this->aProduct();

        $product->remove(new DateTimeImmutable('2026-05-22 09:00:00'));

        $events = $product->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductWasRemoved::class, $events[0]);
        self::assertTrue($events[0]->productId()->equals($product->id()));
    }

    private function aProduct(): Product
    {
        return Product::register(
            ProductId::fromString(self::ID),
            new ProductName('Bidón de agua'),
            new ProductDescription('Bidón de 750 ml para ciclismo'),
            Money::of(999, 'EUR'),
        );
    }
}
