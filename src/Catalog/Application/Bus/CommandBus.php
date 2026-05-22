<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bus;

/**
 * Dispatches a command to its single handler. Commands change state and never
 * return a value — that is the CQRS write side.
 */
interface CommandBus
{
    public function dispatch(object $command): void;
}
