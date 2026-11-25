<?php

namespace App\Livewire\Actions\Payments;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PosSession;
use App\Services\Access\LocationAccess;
use App\Services\Invoicing\DocumentNumberingService;
use App\Services\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Record a customer payment and optionally allocate it to open invoices.
 *
 * Rules:
 * - Same idempotency key returns the existing payment without re-posting.
 * - Walk-in customer is allowed when all allocations target that customer's invoices.
 * - Allocation amounts may not exceed each invoice's current balance.
 * - Any amount not allocated remains as unallocated customer credit.
 * - Invoice rows are locked in ascending id order to prevent deadlocks.
 * - Each invoice's location is checked via LocationAccess::ensure().
 * - Allocations spanning invoices at different locations are refused for non-Admin users.
 *   Admin users may span locations; the payment's location_id is set to null in that case.
 * - For POS payments the location comes from the POS session.
 */
class RecordPayment
{
    public function __construct(
        private readonly DocumentNumberingService $numbering,
        private readonly Money $money,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, string $idempotencyKey): Payment
    {
        Gate::authorize('create', Payment::class);

        if (trim($idempotencyKey) === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key is required.']);
        }

        $this->validate($data);

        return DB::transaction(function () use ($data, $idempotencyKey): Payment {
            // Idempotency: return the existing payment if the key was used before
            $existing = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing->load('allocations');
            }

            // Lock the customer
            $customer = Customer::query()
                ->whereKey($data['customer_id'])
                ->lockForUpdate()
                ->first();

            if ($customer === null || ! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'An active customer is required.']);
            }

            $rawAllocations = $data['allocations'] ?? [];
            $allocations = is_array($rawAllocations) ? $rawAllocations : [];

            // Lock invoice rows in ascending id order to prevent deadlocks
            $invoiceIds = collect($allocations)
                ->pluck('invoice_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
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

            // Validate each allocation
            $allocationTotal = '0.00';
            foreach ($allocations as $alloc) {
                $invoiceId = (int) ($alloc['invoice_id'] ?? 0);
                $allocAmount = $this->money->decimal((string) ($alloc['amount'] ?? '0'));

                $invoice = $invoices[$invoiceId] ?? null;
                if ($invoice === null) {
                    throw ValidationException::withMessages(['allocations' => "Invoice #{$invoiceId} not found."]);
                }

                if ((int) $invoice->customer_id !== (int) $customer->id) {
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

                $allocationTotal = bcadd($allocationTotal, $allocAmount, 2);
            }

            if (bccomp($allocationTotal, $this->money->decimal((string) $data['amount']), 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Total allocations exceed the payment amount.']);
            }

            // ── Location access ──────────────────────────────────────────────────
            $access = app(LocationAccess::class);
            $paymentLocationId = null;

            $posSessionId = $data['pos_session_id'] ?? null;
            if ($posSessionId !== null) {
                $session = PosSession::query()->whereKey($posSessionId)->first();
                if ($session !== null) {
                    $access->ensure(auth()->user(), $session->location_id);
                    $paymentLocationId = $session->location_id;
                }
            }

            if ($invoiceIds !== []) {
                /** @var list<int> $invoiceLocationIds */
                $invoiceLocationIds = collect($invoices)
                    ->pluck('location_id')
                    ->unique()
                    ->values()
                    ->all();

                foreach ($invoiceLocationIds as $locId) {
                    $access->ensure(auth()->user(), (int) $locId);
                }

                if ($posSessionId === null) {
                    if (count($invoiceLocationIds) === 1) {
                        $paymentLocationId = (int) $invoiceLocationIds[0];
                    } else {
                        // Multi-location: only Admin users (allowedLocationIds === null) may proceed.
                        if ($access->allowedLocationIds(auth()->user()) !== null) {
                            throw ValidationException::withMessages([
                                'allocations' => 'You may not allocate a payment across invoices at different locations.',
                            ]);
                        }
                        // Admin: payment location stays null (spans multiple locations).
                    }
                }
            }
            // ────────────────────────────────────────────────────────────────────

            // Create the payment
            $number = $this->numbering->next('payment');

            $payment = Payment::create([
                'number' => $number,
                'customer_id' => $customer->id,
                'location_id' => $paymentLocationId,
                'method' => $data['method'],
                'provider' => $data['provider'] ?? null,
                'reference' => $data['reference'] ?? null,
                'amount' => $data['amount'],
                'amount_tendered' => $data['amount_tendered'] ?? null,
                'change_given' => $data['change_given'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'received_by' => auth()->id(),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'pos_session_id' => $posSessionId,
            ]);

            // Apply allocations
            foreach ($allocations as $alloc) {
                $invoiceId = (int) $alloc['invoice_id'];
                $allocAmount = $this->money->decimal((string) $alloc['amount']);
                $invoice = $invoices[$invoiceId];

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                    'amount' => $allocAmount,
                ]);

                $newPaid = bcadd($invoice->amount_paid, $allocAmount, 2);
                $newStatus = bccomp($newPaid, $invoice->total, 2) >= 0
                    ? InvoiceStatus::Paid
                    : InvoiceStatus::PartiallyPaid;

                $invoice->update(['amount_paid' => $newPaid, 'status' => $newStatus]);
            }

            return $payment->load('allocations');
        });
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        $validMethods = ['cash', 'mobile_money', 'bank_transfer', 'cheque', 'card'];

        if (empty($data['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => 'A customer is required.']);
        }

        $amount = (string) ($data['amount'] ?? '');
        if ($amount === '' || ! is_numeric($amount) || bccomp($amount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        if (! in_array($data['method'] ?? '', $validMethods, true)) {
            throw ValidationException::withMessages(['method' => 'Invalid payment method.']);
        }

        if (empty($data['received_at'])) {
            throw ValidationException::withMessages(['received_at' => 'Received date is required.']);
        }
    }
}
