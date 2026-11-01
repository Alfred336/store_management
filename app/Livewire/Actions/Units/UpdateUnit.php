<?php

namespace App\Livewire\Actions\Units;

use App\Models\Unit;
use Illuminate\Support\Facades\Gate;

/** Update a unit after enforcing its policy. */
class UpdateUnit
{
    /** @param array{name: string, abbreviation: string} $data */
    public function __invoke(Unit $unit, array $data): Unit
    {
        Gate::authorize('update', $unit);
        $unit->update($data);

        return $unit;
    }
}
