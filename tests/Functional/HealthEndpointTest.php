<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * The endpoint's status depends on whether a database is reachable, so this
 * test asserts the contract (shape + a valid status code), not a fixed value.
 */
final class HealthEndpointTest extends ApiTestCase
{
    public function testItReportsAStructuredHealthStatus(): void
    {
        $this->get('/api/health');

        self::assertContains($this->statusCode(), [200, 503]);

        $body = $this->jsonResponse();
        self::assertContains($body['status'], ['ok', 'degraded']);
        self::assertIsArray($body['checks']);
        self::assertArrayHasKey('database', $body['checks']);
    }
}
