<?php

namespace App\Livewire\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Inventory\ReturnStock;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VoidInvoice
{
    public function __construct(
        private readonly ReturnStock $returnStock,
    ) {}

    public function __invoke(Invoice $invoice, string $reason): Invoice
    {
        Gate::authorize('void', $invoice);

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to void an invoice.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $trimmedReason): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === InvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    'invoice' => 'Draft invoices cannot be voided.',
                ]);
            }

            if ($locked->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice is already void.',
                ]);
            }

            if (bccomp((string) $locked->amount_paid, '0.00', 2) > 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'Payments must be reversed before voiding this invoice.',
                ]);
            }

            $this->returnStock->__invoke($locked, $trimmedReason);

            $locked->update([
                'status' => InvoiceStatus::Void,
                'voided_by' => auth()->id(),
                'voided_at' => now(),
                'void_reason' => $trimmedReason,
            ]);

            return $locked->fresh(['items.allocations']);
        });
    }
}
