<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Bus\CommandBus;
use App\Catalog\Application\Command\RemoveProduct\RemoveProductCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DELETE /api/products/{id} — removes a product from the catalog.
 *
 * The {id} requirement keeps this route from shadowing /api/products/search.
 */
final class DeleteProductController
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    #[Route(
        '/api/products/{id}',
        name: 'products_delete',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE'],
    )]
    public function __invoke(string $id): Response
    {
        $this->commandBus->dispatch(new RemoveProductCommand($id));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
