<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Catalog\Domain\Service\Clock;
use DateTimeImmutable;

final class FixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $now)
    {
    }

    public static function at(string $iso8601): self
    {
        return new self(new DateTimeImmutable($iso8601));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
