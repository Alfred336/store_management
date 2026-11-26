<?php

namespace App\Livewire\Actions\Sales;

use App\Models\PosHeldSale;
use App\Services\Access\LocationAccess;
use Illuminate\Validation\ValidationException;

/** Restore a held cart and delete its record. */
class ResumeSale
{
    public function __invoke(int $heldSaleId): PosHeldSale
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        $held = PosHeldSale::query()->findOrFail($heldSaleId);

        // Any user with pos.use can resume their own held sales; managers can resume any.
        $isOwn = $held->user_id === (int) auth()->id();
        if (! $isOwn && ! auth()->user()->can('pos.close_any_session')) {
            throw ValidationException::withMessages(['held_sale' => 'You can only resume your own held sales.']);
        }

        // User must have access to the held sale's location regardless of ownership.
        app(LocationAccess::class)->ensure(auth()->user(), $held->location_id);

        $held->delete();

        return $held;
    }
}
