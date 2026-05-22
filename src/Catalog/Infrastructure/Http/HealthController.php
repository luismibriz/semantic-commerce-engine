<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * GET /api/health — readiness probe.
 *
 * It actually exercises a dependency instead of returning a hard-coded "ok":
 * it pings PostgreSQL, the single source of truth. If the database is
 * unreachable the service cannot function, so the endpoint reports 503 — which
 * lets an orchestrator stop routing traffic here. The search backend has its
 * own container-level health check, so it is not duplicated here.
 */
final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/api/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $databaseUp = $this->databaseIsReachable();

        return new JsonResponse(
            [
                'status' => $databaseUp ? 'ok' : 'degraded',
                'checks' => [
                    'database' => $databaseUp ? 'up' : 'down',
                ],
            ],
            $databaseUp ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function databaseIsReachable(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
