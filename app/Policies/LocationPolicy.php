<?php

namespace App\Policies;

/** Authorize location access using the locations.* permission family. */
class LocationPolicy extends InventoryResourcePolicy
{
    protected function resource(): string
    {
        return 'locations';
    }
}
