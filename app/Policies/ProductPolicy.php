<?php

namespace App\Policies;

/** Authorize product access using the products.* permission family. */
class ProductPolicy extends InventoryResourcePolicy
{
    protected function resource(): string
    {
        return 'products';
    }
}
