<?php

namespace App\Actions\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\Gate;

/** Update a location after enforcing its policy. */
class UpdateLocation
{
    /** @param array{name: string, type: string, is_active: bool} $data */
    public function __invoke(Location $location, array $data): Location
    {
        Gate::authorize('update', $location);
        $location->update($data);

        return $location;
    }
}
