<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class IndexProductEndpointTest extends ApiTestCase
{
    public function testItIndexesAValidProduct(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Maillot térmico de invierno',
            'description' => 'Camiseta térmica roja para ciclismo en frío.',
            'price' => ['amount' => 5999, 'currency' => 'EUR'],
        ]);

        self::assertSame(201, $this->statusCode());
        self::assertArrayHasKey('id', $this->jsonResponse());
    }

    public function testItRejectsAnInvalidCurrency(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Producto',
            'description' => 'Descripción válida',
            'price' => ['amount' => 1000, 'currency' => 'EURO'],
        ]);

        self::assertSame(400, $this->statusCode());
        self::assertArrayHasKey('error', $this->jsonResponse());
    }

    public function testItRejectsANegativePrice(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Producto',
            'description' => 'Descripción válida',
            'price' => ['amount' => -1, 'currency' => 'EUR'],
        ]);

        self::assertSame(400, $this->statusCode());
    }

    public function testItRejectsAMalformedJsonBody(): void
    {
        $this->postRaw('/api/products', '{not valid json');

        self::assertSame(400, $this->statusCode());
    }

    public function testItRejectsAMissingField(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Producto sin descripción',
            'price' => ['amount' => 1000, 'currency' => 'EUR'],
        ]);

        self::assertSame(400, $this->statusCode());
    }
}
