<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Catalog\Application\Bus\DomainEventBus;
use App\Catalog\Domain\Event\DomainEvent;

/**
 * Captures every published domain event so tests can assert on them.
 */
final class RecordingDomainEventBus implements DomainEventBus
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function publish(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function published(): array
    {
        return $this->events;
    }
}
