<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\RemoveProduct;

/**
 * Intent: delete a product from the catalog and stop it being discoverable.
 */
final class RemoveProductCommand
{
    public function __construct(public readonly string $productId)
    {
    }
}
