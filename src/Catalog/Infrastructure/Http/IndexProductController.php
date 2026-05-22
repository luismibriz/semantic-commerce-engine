<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Bus\CommandBus;
use App\Catalog\Application\Command\IndexProduct\IndexProductCommand;
use App\Catalog\Domain\Exception\InvalidArgument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * POST /api/products — registers (or refreshes) a product and indexes it.
 *
 * The controller only adapts HTTP to a command: it never builds domain value
 * objects, so all business validation stays in the handler/domain.
 */
final class IndexProductController
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    #[Route('/api/products', name: 'products_index', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $price = $body['price'] ?? null;
        if (!\is_array($price)) {
            throw InvalidArgument::because('Field "price" must be an object with "amount" and "currency".');
        }

        $providedId = $body['id'] ?? null;
        $productId = \is_string($providedId) && '' !== trim($providedId)
            ? trim($providedId)
            : Uuid::v4()->toRfc4122();

        $command = new IndexProductCommand(
            productId: $productId,
            name: $this->requireString($body, 'name'),
            description: $this->requireString($body, 'description'),
            priceAmount: $this->requireInt($price, 'amount'),
            priceCurrency: $this->requireString($price, 'currency'),
        );

        $this->commandBus->dispatch($command);

        return new JsonResponse(['id' => $productId], Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        if (!\is_array($decoded)) {
            throw InvalidArgument::because('Request body must be a JSON object.');
        }

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidArgument::because(\sprintf('Field "%s" is required and must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!\is_int($value)) {
            throw InvalidArgument::because(\sprintf('Field "%s" is required and must be an integer.', $key));
        }

        return $value;
    }
}
