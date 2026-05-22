<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Clock;

use App\Catalog\Domain\Service\Clock;
use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
