<?php

namespace App\Livewire\Actions\Payments;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Allocate unallocated credit from an existing active payment to open invoices.
 *
 * The allocation amounts must not exceed the payment's remaining unallocated credit,
 * nor exceed each invoice's current balance.
 */
class AllocateCredit
{
    public function __construct(
        private readonly Money $money,
    ) {}

    /**
     * @param  array<int, array{invoice_id: int, amount: string}>  $allocations
     */
    public function __invoke(Payment $payment, array $allocations): Payment
    {
        Gate::authorize('create', Payment::class);

        if ($payment->status !== 'active') {
            throw ValidationException::withMessages(['payment' => 'Only active payments can be allocated.']);
        }

        if ($allocations === []) {
            throw ValidationException::withMessages(['allocations' => 'At least one allocation is required.']);
        }

        return DB::transaction(function () use ($payment, $allocations): Payment {
            // Lock the payment row
            $locked = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['payment' => 'Only active payments can be allocated.']);
            }

            $available = $locked->unallocatedCredit();

            // Lock invoice rows in ascending id order
            $invoiceIds = collect($allocations)
                ->pluck('invoice_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->unique()
                ->values()
                ->all();

            /** @var array<int, Invoice> $invoices */
            $invoices = Invoice::query()
                ->whereIn('id', $invoiceIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all();

            $allocationTotal = '0.00';

            foreach ($allocations as $alloc) {
                $invoiceId = (int) $alloc['invoice_id'];
                $allocAmount = $this->money->decimal((string) $alloc['amount']);

                $invoice = $invoices[$invoiceId] ?? null;
                if ($invoice === null) {
                    throw ValidationException::withMessages(['allocations' => "Invoice #{$invoiceId} not found."]);
                }

                if ((int) $invoice->customer_id !== (int) $locked->customer_id) {
                    throw ValidationException::withMessages(['allocations' => "Invoice #{$invoiceId} does not belong to this customer."]);
                }

                if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
                    throw ValidationException::withMessages(['allocations' => "Invoice #{$invoiceId} cannot receive payments (status: {$invoice->status->value})."]);
                }

                if (bccomp($allocAmount, '0.00', 2) <= 0) {
                    throw ValidationException::withMessages(['allocations' => "Allocation amount for invoice #{$invoiceId} must be greater than zero."]);
                }

                $balance = $invoice->balance();
                if (bccomp($allocAmount, $balance, 2) > 0) {
                    throw ValidationException::withMessages(['allocations' => "Allocation of {$allocAmount} for invoice #{$invoiceId} exceeds its balance of {$balance}."]);
                }

                // Check no duplicate allocation for this payment+invoice
                if (PaymentAllocation::query()
                    ->where('payment_id', $locked->id)
                    ->where('invoice_id', $invoiceId)
                    ->exists()) {
                    throw ValidationException::withMessages(['allocations' => "Invoice #{$invoiceId} already has an allocation from this payment."]);
                }

                $allocationTotal = bcadd($allocationTotal, $allocAmount, 2);
            }

            if (bccomp($allocationTotal, $available, 2) > 0) {
                throw ValidationException::withMessages(['allocations' => "Total allocations ({$allocationTotal}) exceed the available credit ({$available})."]);
            }

            // Apply
            foreach ($allocations as $alloc) {
                $invoiceId = (int) $alloc['invoice_id'];
                $allocAmount = $this->money->decimal((string) $alloc['amount']);
                $invoice = $invoices[$invoiceId];

                PaymentAllocation::create([
                    'payment_id' => $locked->id,
                    'invoice_id' => $invoiceId,
                    'amount' => $allocAmount,
                ]);

                $newPaid = bcadd($invoice->amount_paid, $allocAmount, 2);
                $newStatus = bccomp($newPaid, $invoice->total, 2) >= 0
                    ? InvoiceStatus::Paid
                    : InvoiceStatus::PartiallyPaid;

                $invoice->update(['amount_paid' => $newPaid, 'status' => $newStatus]);
            }

            return $locked->fresh('allocations');
        });
    }
}
