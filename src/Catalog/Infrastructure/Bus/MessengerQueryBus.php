<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Bus;

use App\Catalog\Application\Bus\QueryBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * QueryBus backed by a dedicated Symfony Messenger bus ("query.bus").
 *
 * HandleTrait synchronously dispatches and returns the single handler's
 * result, which is exactly the read-side contract.
 */
final class MessengerQueryBus implements QueryBus
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    public function ask(object $query): mixed
    {
        try {
            return $this->handle($query);
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }
}
