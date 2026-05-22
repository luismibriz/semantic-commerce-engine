<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for functional tests. They boot the kernel and drive the real
 * HTTP stack; in the test environment the infrastructure adapters are the
 * in-memory doubles (see config/services_test.yaml), so no database or
 * Elasticsearch is required.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Keep the kernel (and therefore the in-memory adapters) alive across
        // the several requests a single test makes — e.g. index then search.
        $this->client->disableReboot();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postJson(string $uri, array $payload): void
    {
        $this->postRaw($uri, json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    protected function postRaw(string $uri, string $body): void
    {
        $this->client->request('POST', $uri, [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }

    protected function get(string $uri): void
    {
        $this->client->request('GET', $uri);
    }

    protected function delete(string $uri): void
    {
        $this->client->request('DELETE', $uri);
    }

    protected function statusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function jsonResponse(): array
    {
        $decoded = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }
}
