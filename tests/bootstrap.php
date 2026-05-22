<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__).'/vendor/autoload.php';

// Functional and integration tests boot the Symfony kernel, which needs the
// environment variables resolved. APP_ENV is forced to "test" in phpunit.xml,
// so this loads .env and then .env.test.
(new Dotenv())->bootEnv(\dirname(__DIR__).'/.env');
