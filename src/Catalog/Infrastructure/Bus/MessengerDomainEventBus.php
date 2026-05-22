<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Bus;

use App\Catalog\Application\Bus\DomainEventBus;
use App\Catalog\Domain\Event\DomainEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * DomainEventBus backed by a dedicated Messenger bus ("event.bus") configured
 * to allow zero handlers.
 */
final class MessengerDomainEventBus implements DomainEventBus
{
    public function __construct(private readonly MessageBusInterface $eventBus)
    {
    }

    public function publish(DomainEvent $event): void
    {
        try {
            $this->eventBus->dispatch($event);
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }
}
