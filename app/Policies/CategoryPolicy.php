<?php

namespace App\Policies;

/** Authorize category access using the categories.* permission family. */
class CategoryPolicy extends InventoryResourcePolicy
{
    protected function resource(): string
    {
        return 'categories';
    }
}
