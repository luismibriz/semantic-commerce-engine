<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

/**
 * Builds the Elasticsearch client from a DSN, keeping the static builder out
 * of the service container wiring.
 */
final class ElasticsearchClientFactory
{
    public static function create(string $dsn): Client
    {
        return ClientBuilder::create()
            ->setHosts([$dsn])
            ->setRetries(2)
            ->build();
    }
}
