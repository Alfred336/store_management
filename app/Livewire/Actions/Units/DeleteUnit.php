<?php

namespace App\Livewire\Actions\Units;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Support\Facades\Gate;

/** Delete a unit only when no product references it. */
class DeleteUnit
{
    public function __invoke(Unit $unit): bool
    {
        Gate::authorize('delete', $unit);

        if (Product::query()->where('base_unit_id', $unit->id)->exists()
            || ProductUnit::query()->where('unit_id', $unit->id)->exists()) {
            return false;
        }

        return (bool) $unit->delete();
    }
}
