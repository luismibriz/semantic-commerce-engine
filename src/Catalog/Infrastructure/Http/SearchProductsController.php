<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Bus\QueryBus;
use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use App\Catalog\Application\Query\SearchProducts\SearchProductsResponse;
use App\Catalog\Domain\Exception\InvalidArgument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/products/search?q=...&limit=... — semantic product discovery.
 */
final class SearchProductsController
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    #[Route('/api/products/search', name: 'products_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $text = (string) $request->query->get('q', '');
        $limit = $this->readLimit($request);

        /** @var SearchProductsResponse $response */
        $response = $this->queryBus->ask(new SearchProductsQuery($text, $limit));

        return new JsonResponse($response->toArray());
    }

    private function readLimit(Request $request): ?int
    {
        $raw = $request->query->get('limit');

        if (null === $raw || '' === $raw) {
            return null;
        }

        if (!ctype_digit((string) $raw)) {
            throw InvalidArgument::because('Query parameter "limit" must be a positive integer.');
        }

        return (int) $raw;
    }
}
