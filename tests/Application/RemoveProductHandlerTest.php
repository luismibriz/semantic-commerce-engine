<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Catalog\Application\Command\RemoveProduct\RemoveProductCommand;
use App\Catalog\Application\Command\RemoveProduct\RemoveProductHandler;
use App\Catalog\Domain\Event\ProductWasRemoved;
use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Tests\Doubles\FixedClock;
use App\Tests\Doubles\InMemoryProductRepository;
use App\Tests\Doubles\RecordingDomainEventBus;
use PHPUnit\Framework\TestCase;

final class RemoveProductHandlerTest extends TestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';

    private InMemoryProductRepository $products;
    private RecordingDomainEventBus $eventBus;
    private RemoveProductHandler $handler;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->eventBus = new RecordingDomainEventBus();
        $this->handler = new RemoveProductHandler(
            $this->products,
            $this->eventBus,
            FixedClock::at('2026-05-22T09:00:00+00:00'),
        );
    }

    public function testItRemovesAnExistingProduct(): void
    {
        $this->products->save($this->aProduct());

        ($this->handler)(new RemoveProductCommand(self::ID));

        self::assertNull($this->products->ofId(ProductId::fromString(self::ID)));
    }

    public function testItPublishesAProductWasRemovedEvent(): void
    {
        $this->products->save($this->aProduct());

        ($this->handler)(new RemoveProductCommand(self::ID));

        $events = $this->eventBus->published();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductWasRemoved::class, $events[0]);
        self::assertSame(self::ID, $events[0]->productId()->value());
    }

    public function testItFailsWhenTheProductDoesNotExist(): void
    {
        $this->expectException(ProductNotFound::class);

        ($this->handler)(new RemoveProductCommand(self::ID));
    }

    private function aProduct(): Product
    {
        return Product::register(
            ProductId::fromString(self::ID),
            new ProductName('Producto'),
            new ProductDescription('Descripción del producto'),
            Money::of(1000, 'EUR'),
        );
    }
}
