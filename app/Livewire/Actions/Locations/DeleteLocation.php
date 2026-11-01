<?php

namespace App\Livewire\Actions\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\Gate;

/** Delete a location only when no stock level or movement references it. */
class DeleteLocation
{
    public function __invoke(Location $location): bool
    {
        Gate::authorize('delete', $location);

        if ($location->stockLevels()->exists() || $location->stockMovements()->exists()) {
            return false;
        }

        return (bool) $location->delete();
    }
}
