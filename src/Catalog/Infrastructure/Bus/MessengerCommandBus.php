<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Bus;

use App\Catalog\Application\Bus\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * CommandBus backed by a dedicated Symfony Messenger bus ("command.bus").
 *
 * Messenger wraps handler exceptions; this adapter unwraps the first one so
 * callers see the real domain exception instead of a transport wrapper.
 */
final class MessengerCommandBus implements CommandBus
{
    public function __construct(private readonly MessageBusInterface $commandBus)
    {
    }

    public function dispatch(object $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }
}
