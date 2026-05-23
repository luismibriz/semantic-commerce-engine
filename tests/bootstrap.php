<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__).'/vendor/autoload.php';

// The test suite must always run under APP_ENV=test. When the host (e.g. the
// `app` container in docker-compose.yml) exports APP_ENV=dev, that value
// lands in $_ENV, $_SERVER and getenv() — and Symfony's KernelTestCase reads
// $_ENV first, so a stale "dev" there would silently boot the kernel in the
// wrong environment and skip the `when@test` framework.test config. We pin
// it back to "test" here, before Dotenv::bootEnv populates the rest.
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';
putenv('APP_ENV=test');

// Same story for the database: the `app` container exports DATABASE_URL
// pointing at the production-side `app` database. If we let it through, the
// integration tests would drop and recreate the schema on the database the
// running application uses. We reroute to DATABASE_URL_TEST (set by
// docker-compose for the `app` service) when present.
if (false !== ($testDatabaseUrl = getenv('DATABASE_URL_TEST'))) {
    $_ENV['DATABASE_URL'] = $testDatabaseUrl;
    $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
    putenv('DATABASE_URL='.$testDatabaseUrl);
}

// Functional and integration tests boot the Symfony kernel: bootEnv loads
// .env and then .env.test.
(new Dotenv())->bootEnv(\dirname(__DIR__).'/.env');
