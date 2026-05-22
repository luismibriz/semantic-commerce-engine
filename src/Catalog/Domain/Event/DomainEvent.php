<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use DateTimeImmutable;

/**
 * Marker contract for everything an aggregate records about its own past.
 */
interface DomainEvent
{
    public function occurredOn(): DateTimeImmutable;
}
