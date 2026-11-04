<?php

namespace App\Livewire\Actions\Sales;

use App\Models\PosSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Close an open POS session. Cashier closes own; manager can close any. */
class ClosePosSession
{
    /**
     * @param  array{closing_cash_counted: string, notes?: string}  $data
     */
    public function __invoke(PosSession $session, array $data): PosSession
    {
        $user = auth()->user();
        $isOwn = $session->user_id === (int) auth()->id();

        if ($isOwn) {
            abort_unless($user?->can('pos.close_session'), 403);
        } else {
            abort_unless($user?->can('pos.close_any_session'), 403);
        }

        if (! $session->isOpen()) {
            throw ValidationException::withMessages(['session' => 'Session is already closed.']);
        }

        $closingCash = $data['closing_cash_counted'];
        if (! is_numeric($closingCash) || bccomp((string) $closingCash, '0', 2) < 0) {
            throw ValidationException::withMessages(['closing_cash_counted' => 'Closing cash must be zero or greater.']);
        }

        return DB::transaction(function () use ($session, $data, $closingCash): PosSession {
            $locked = PosSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['session' => 'Session is already closed.']);
            }

            // Expected cash = opening cash + all cash payments recorded in this session
            $cashPaymentsTotal = $locked->payments()
                ->where('status', 'active')
                ->where('method', 'cash')
                ->sum('amount');

            $expectedCash = bcadd((string) $locked->opening_cash, number_format((float) $cashPaymentsTotal, 2, '.', ''), 2);
            $difference = bcsub((string) $closingCash, $expectedCash, 2);

            $locked->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                'closing_cash_counted' => $closingCash,
                'expected_cash' => $expectedCash,
                'difference' => $difference,
                'notes' => $data['notes'] ?? null,
            ]);

            return $locked->fresh();
        });
    }
}
