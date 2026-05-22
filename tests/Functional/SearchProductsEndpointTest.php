<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class SearchProductsEndpointTest extends ApiTestCase
{
    public function testItReturnsAProductItHasJustIndexed(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Maillot térmico de invierno',
            'description' => 'Camiseta térmica de manga larga para ciclismo en frío.',
            'price' => ['amount' => 5999, 'currency' => 'EUR'],
        ]);
        self::assertSame(201, $this->statusCode());

        $this->get('/api/products/search?q='.rawurlencode('camiseta térmica para el frío'));

        self::assertSame(200, $this->statusCode());
        $body = $this->jsonResponse();
        self::assertSame('camiseta térmica para el frío', $body['query']);
        self::assertIsArray($body['results']);
        self::assertGreaterThanOrEqual(1, $body['count']);
    }

    public function testItRejectsAnEmptyQuery(): void
    {
        $this->get('/api/products/search?q=');

        self::assertSame(400, $this->statusCode());
        self::assertArrayHasKey('error', $this->jsonResponse());
    }

    public function testItRejectsAnOutOfRangeLimit(): void
    {
        $this->get('/api/products/search?q=camiseta&limit=999');

        self::assertSame(400, $this->statusCode());
    }
}
