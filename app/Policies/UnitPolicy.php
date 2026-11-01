<?php

namespace App\Policies;

/** Authorize unit access using the units.* permission family. */
class UnitPolicy extends InventoryResourcePolicy
{
    protected function resource(): string
    {
        return 'units';
    }
}
