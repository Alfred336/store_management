<?php

namespace App\Livewire\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class DeleteInvoiceDraft
{
    public function __invoke(Invoice $invoice): void
    {
        Gate::authorize('delete', $invoice);

        DB::transaction(function () use ($invoice): void {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== InvoiceStatus::Draft) {
                throw new InvalidArgumentException('Only draft invoices can be deleted.');
            }

            $locked->delete();
        });
    }
}
