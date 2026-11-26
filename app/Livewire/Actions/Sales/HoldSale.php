<?php

namespace App\Livewire\Actions\Sales;

use App\Models\PosHeldSale;
use App\Services\Access\LocationAccess;

/** Persist the current cart for later resumption. Does not touch stock, numbering, or payments. */
class HoldSale
{
    /**
     * @param array{
     *   location_id: int,
     *   pos_session_id?: int|null,
     *   customer_id?: int|null,
     *   label?: string,
     *   cart: array<int, mixed>
     * } $data
     */
    public function __invoke(array $data): PosHeldSale
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        app(LocationAccess::class)->ensure(auth()->user(), (int) $data['location_id']);

        return PosHeldSale::query()->create([
            'user_id' => auth()->id(),
            'location_id' => $data['location_id'],
            'pos_session_id' => $data['pos_session_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'label' => $data['label'] ?? '',
            'cart' => $data['cart'],
        ]);
    }
}
