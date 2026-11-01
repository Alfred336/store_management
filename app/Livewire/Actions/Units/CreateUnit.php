<?php

namespace App\Livewire\Actions\Units;

use App\Models\Unit;
use Illuminate\Support\Facades\Gate;

/** Create a unit after enforcing the unit creation policy. */
class CreateUnit
{
    /** @param array{name: string, abbreviation: string} $data */
    public function __invoke(array $data): Unit
    {
        Gate::authorize('create', Unit::class);

        return Unit::query()->create($data);
    }
}
