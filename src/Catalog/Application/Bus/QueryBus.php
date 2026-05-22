<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bus;

/**
 * Dispatches a query to its single handler and returns the read model it
 * produces. Queries never change state — that is the CQRS read side.
 */
interface QueryBus
{
    public function ask(object $query): mixed;
}
