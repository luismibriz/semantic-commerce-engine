<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use DateTimeImmutable;

/**
 * Port over "now". Injecting it keeps aggregates and handlers deterministic
 * and therefore testable.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
