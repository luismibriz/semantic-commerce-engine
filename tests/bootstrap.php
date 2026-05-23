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

// Functional and integration tests boot the Symfony kernel: bootEnv loads
// .env and then .env.test.
(new Dotenv())->bootEnv(\dirname(__DIR__).'/.env');
