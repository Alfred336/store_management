<?php

namespace App\Livewire\Actions\Sales;

use App\Models\Location;
use App\Models\PosSession;
use Illuminate\Validation\ValidationException;

/** Open a new POS session for the authenticated cashier. Only one open session per user is allowed. */
class OpenPosSession
{
    /**
     * @param  array{location_id: int, opening_cash?: string}  $data
     */
    public function __invoke(array $data): PosSession
    {
        abort_unless(auth()->user()?->can('pos.open_session'), 403);

        $locationId = (int) $data['location_id'];
        $location = Location::query()->whereKey($locationId)->where('is_active', true)->first();
        if ($location === null) {
            throw ValidationException::withMessages(['location_id' => 'An active location is required.']);
        }

        $openingCash = $data['opening_cash'] ?? '0.00';
        if (! is_numeric($openingCash) || bccomp((string) $openingCash, '0', 2) < 0) {
            throw ValidationException::withMessages(['opening_cash' => 'Opening cash must be zero or greater.']);
        }

        $userId = (int) auth()->id();

        // The partial unique index enforces one open session per user at the DB level.
        // Check here first to surface a friendly error.
        $existing = PosSession::query()
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['session' => 'You already have an open POS session.']);
        }

        return PosSession::query()->create([
            'user_id' => $userId,
            'location_id' => $location->id,
            'opened_at' => now(),
            'opening_cash' => $openingCash,
            'status' => 'open',
        ]);
    }
}
