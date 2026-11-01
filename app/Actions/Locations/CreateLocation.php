<?php

namespace App\Actions\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\Gate;

/** Create a location after enforcing the location policy. */
class CreateLocation
{
    /** @param array{name: string, type: string, is_active: bool} $data */
    public function __invoke(array $data): Location
    {
        Gate::authorize('create', Location::class);

        return Location::query()->create($data);
    }
}
