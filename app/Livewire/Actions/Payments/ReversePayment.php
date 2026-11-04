<?php

namespace App\Livewire\Actions\Payments;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reverse a payment: restore each allocated invoice's amount_paid and status,
 * mark the payment reversed.  Allocations are kept for audit.
 */
class ReversePayment
{
    public function __invoke(Payment $payment, string $reason): Payment
    {
        Gate::authorize('reverse', $payment);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to reverse a payment.']);
        }

        return DB::transaction(function () use ($payment, $reason): Payment {
            $locked = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'reversed') {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            }

            // Load allocations
            $allocations = $locked->allocations()->with('invoice')->get();

            // Lock the invoice rows in ascending id order to prevent deadlocks
            $invoiceIds = $allocations->pluck('invoice_id')
                ->sort()
                ->unique()
                ->values()
                ->all();

            /** @var array<int, Invoice> $invoices */
            $invoices = [];
            if ($invoiceIds !== []) {
                $invoices = Invoice::query()
                    ->whereIn('id', $invoiceIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id')
                    ->all();
            }

            // Restore amount_paid and status for each allocation
            foreach ($allocations as $alloc) {
                $invoice = $invoices[(int) $alloc->invoice_id] ?? null;
                if ($invoice === null) {
                    continue;
                }

                $newPaid = bcsub((string) $invoice->amount_paid, (string) $alloc->amount, 2);
                // Guard against floating-point drift: clamp to 0
                if (bccomp($newPaid, '0.00', 2) < 0) {
                    $newPaid = '0.00';
                }

                $newStatus = match (true) {
                    bccomp($newPaid, '0.00', 2) === 0 => InvoiceStatus::Issued,
                    default => InvoiceStatus::PartiallyPaid,
                };

                $invoice->update(['amount_paid' => $newPaid, 'status' => $newStatus]);
            }

            // Mark the payment reversed
            $locked->update([
                'status' => 'reversed',
                'reversed_by' => auth()->id(),
                'reversed_at' => now(),
                'reverse_reason' => $reason,
            ]);

            return $locked->fresh(['allocations.invoice']);
        });
    }
}
