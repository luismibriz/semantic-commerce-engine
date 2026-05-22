<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class DeleteProductEndpointTest extends ApiTestCase
{
    public function testItRemovesAnExistingProduct(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Producto a eliminar',
            'description' => 'Descripción del producto a eliminar.',
            'price' => ['amount' => 1000, 'currency' => 'EUR'],
        ]);
        $id = $this->jsonResponse()['id'];
        self::assertIsString($id);

        $this->delete('/api/products/'.$id);
        self::assertSame(204, $this->statusCode());

        // Removing it again proves it is gone from the write model.
        $this->delete('/api/products/'.$id);
        self::assertSame(404, $this->statusCode());
    }

    public function testItReturns404ForAnUnknownProduct(): void
    {
        $this->delete('/api/products/11111111-1111-4111-8111-111111111111');

        self::assertSame(404, $this->statusCode());
        self::assertArrayHasKey('error', $this->jsonResponse());
    }
}
