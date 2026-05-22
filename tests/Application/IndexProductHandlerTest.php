<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Catalog\Application\Command\IndexProduct\IndexProductCommand;
use App\Catalog\Application\Command\IndexProduct\IndexProductHandler;
use App\Catalog\Domain\Event\ProductWasIndexed;
use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Infrastructure\Embedding\HashingEmbeddingGenerator;
use App\Tests\Doubles\FixedClock;
use App\Tests\Doubles\InMemoryProductRepository;
use App\Tests\Doubles\RecordingDomainEventBus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IndexProductHandlerTest extends TestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';

    private InMemoryProductRepository $products;
    private RecordingDomainEventBus $eventBus;
    private IndexProductHandler $handler;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->eventBus = new RecordingDomainEventBus();
        $this->handler = new IndexProductHandler(
            $this->products,
            new HashingEmbeddingGenerator(256),
            $this->eventBus,
            FixedClock::at('2026-05-21T10:00:00+00:00'),
        );
    }

    public function testItRegistersIndexesAndPersistsANewProduct(): void
    {
        ($this->handler)(new IndexProductCommand(
            productId: self::ID,
            name: 'Camiseta térmica',
            description: 'Roja, de manga larga, para ciclismo de invierno',
            priceAmount: 2599,
            priceCurrency: 'EUR',
        ));

        $product = $this->products->ofId(ProductId::fromString(self::ID));

        self::assertNotNull($product);
        self::assertTrue($product->isIndexed());
        self::assertSame(256, $product->embedding()?->dimensions());
        self::assertEquals(new DateTimeImmutable('2026-05-21T10:00:00+00:00'), $product->indexedAt());
    }

    public function testItPublishesAProductWasIndexedEvent(): void
    {
        ($this->handler)(new IndexProductCommand(self::ID, 'Bidón', 'Bidón de 750 ml', 999, 'EUR'));

        $events = $this->eventBus->published();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProductWasIndexed::class, $events[0]);
        self::assertSame(self::ID, $events[0]->productId()->value());
    }

    public function testIndexingTheSameIdTwiceUpdatesInsteadOfDuplicating(): void
    {
        ($this->handler)(new IndexProductCommand(self::ID, 'Nombre inicial', 'Descripción inicial', 100, 'EUR'));
        ($this->handler)(new IndexProductCommand(self::ID, 'Nombre revisado', 'Descripción revisada', 350, 'EUR'));

        self::assertSame(1, $this->products->count());

        $product = $this->products->ofId(ProductId::fromString(self::ID));
        self::assertNotNull($product);
        self::assertSame('Nombre revisado', $product->name()->value());
        self::assertSame(350, $product->price()->amount());
    }

    public function testItRejectsInvalidInputWithoutPersistingAnything(): void
    {
        try {
            ($this->handler)(new IndexProductCommand(self::ID, 'Camiseta', 'Térmica', 2599, 'EURO'));
            self::fail('Expected an InvalidArgument exception.');
        } catch (InvalidArgument) {
            self::assertSame(0, $this->products->count());
            self::assertSame([], $this->eventBus->published());
        }
    }
}
