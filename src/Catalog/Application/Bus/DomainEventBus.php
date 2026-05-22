<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bus;

use App\Catalog\Domain\Event\DomainEvent;

/**
 * Publishes domain events recorded by an aggregate to any number of
 * subscribers. Having zero subscribers is a valid state.
 */
interface DomainEventBus
{
    public function publish(DomainEvent $event): void;
}
