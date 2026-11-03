<?php

namespace App\Livewire\Actions\Invoicing;

use App\DTOs\IssueInvoiceResult;
use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Inventory\IssueStock;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemAllocation;
use App\Models\Location;
use App\Models\User;
use App\Services\Customers\CreditLimitService;
use App\Services\Invoicing\DocumentNumberingService;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class IssueInvoice
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly CreditLimitService $creditLimitService,
        private readonly DocumentNumberingService $numberingService,
        private readonly IssueStock $issueStock,
        private readonly Settings $settings,
    ) {}

    /**
     * Issue a draft invoice.
     *
     * Returns an IssueInvoiceResult whose `$creditWarning` is non-null when the customer's
     * credit limit is exceeded and the store is configured to 'warn'.  The caller should
     * surface that message to the operator.
     */
    public function __invoke(Invoice $invoice, string $idempotencyKey): IssueInvoiceResult
    {
        Gate::authorize('issue', $invoice);

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required to issue an invoice.');
        }

        return DB::transaction(function () use ($invoice, $idempotencyKey): IssueInvoiceResult {
            // 1. Lock the invoice row
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. If the SAME idempotency_key was used to post this invoice previously, return it
            //    unchanged regardless of any subsequent status change (paid, void, etc.).
            if ($locked->idempotency_key !== null
                && $locked->idempotency_key === $idempotencyKey
                && $locked->status !== InvoiceStatus::Draft
            ) {
                return new IssueInvoiceResult($locked->load(['items.allocations', 'customer', 'location']));
            }

            // 3. Only draft invoices may be issued; any other non-draft with a mismatched key
            //    is a user error (wrong invoice, duplicate UI submission, etc.).
            if ($locked->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    'invoice' => "Invoice cannot be issued because it is not a draft (current status: {$locked->status->value}).",
                ]);
            }

            if (Invoice::query()->where('idempotency_key', $idempotencyKey)->where('id', '!=', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'idempotency_key' => "Idempotency key [{$idempotencyKey}] has already been used for a different invoice.",
                ]);
            }

            // 4. Re-validate items, active customer/location/products
            $customer = Customer::query()->whereKey($locked->customer_id)->lockForUpdate()->first();
            if (! $customer || ! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'An active customer is required.']);
            }
            if ($locked->channel === 'invoice' && $customer->is_walk_in) {
                throw ValidationException::withMessages(['customer_id' => 'The Walk-in Customer is not allowed on invoice channel.']);
            }

            $location = Location::query()->whereKey($locked->location_id)->lockForUpdate()->first();
            if (! $location || ! $location->is_active) {
                throw ValidationException::withMessages(['location_id' => 'An active location is required.']);
            }

            $items = $locked->items()->with('product')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'An invoice must contain at least one item.']);
            }

            foreach ($items as $item) {
                if ($item->product->trashed() || ! $item->product->is_active) {
                    throw ValidationException::withMessages(['items' => "Product [{$item->description}] is no longer active."]);
                }
            }

            // 5. Recalculate totals with InvoiceCalculator
            $calculatorLines = $items->map(fn (InvoiceItem $item): array => [
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount_amount' => (string) $item->discount_amount,
                'tax_type' => $item->tax_type,
            ])->all();

            $calculated = $this->calculator->calculate($calculatorLines);
            $vatRate = (string) $this->settings->get('tax.vat_rate', '18.00');

            foreach ($items as $index => $item) {
                $lineCalc = $calculated['lines'][$index];
                $taxRate = $item->tax_type === 'standard' ? $vatRate : '0.00';
                $item->update([
                    'tax_rate' => $taxRate,
                    'line_tax' => $lineCalc['line_tax'],
                    'line_total' => $lineCalc['line_total'],
                ]);
            }

            $pricesIncludeTax = (bool) $this->settings->get('tax.prices_include_tax', false);
            $locked->update([
                'prices_include_tax' => $pricesIncludeTax,
                'subtotal' => $calculated['subtotal'],
                'discount_total' => $calculated['discount_total'],
                'tax_total' => $calculated['tax_total'],
                'total' => $calculated['total'],
            ]);

            // 6. Credit-limit check via CreditLimitService (which reads balances through
            //    CustomerBalance — no Gate anywhere in this call chain).
            //    'block'        → throw ValidationException; invoice stays as draft.
            //    'warn'         → proceed; caller surfaces the warning.
            //    'within_limit' → proceed silently.
            $creditWarning = null;
            $creditEvaluation = $this->creditLimitService->evaluate($customer, (string) $locked->total);
            if ($creditEvaluation['outcome'] === 'block') {
                throw ValidationException::withMessages([
                    'customer_id' => "Credit limit exceeded for customer [{$customer->name}].",
                ]);
            }
            if ($creditEvaluation['outcome'] === 'warn') {
                $creditWarning = "Credit limit will be exceeded for customer [{$customer->name}]. "
                    ."Projected balance: {$creditEvaluation['projected_balance']} / Limit: {$creditEvaluation['credit_limit']}.";
            }

            // 7. Take the number with DocumentNumberingService::next('invoice')
            $number = $this->numberingService->next('invoice');

            // 8. Process items with IssueStock in consistent order by product_id
            $orderedItems = $items->sortBy('product_id');

            foreach ($orderedItems as $item) {
                $parts = $this->issueStock->__invoke([
                    'product_id' => $item->product_id,
                    'location_id' => $locked->location_id,
                    'quantity' => (string) $item->quantity,
                    'unit_id' => $item->unit_id,
                    'type' => 'sale',
                    'reference' => $number,
                    'person_name' => auth()->user() instanceof User ? auth()->user()->name : 'System',
                ]);

                foreach ($parts as $part) {
                    InvoiceItemAllocation::query()->create([
                        'invoice_item_id' => $item->id,
                        'location_id' => $locked->location_id,
                        'batch_id' => $part['batch_id'],
                        'base_quantity' => $part['base_quantity'],
                        'stock_movement_id' => $part['movement']->id,
                    ]);
                }
            }

            // 9. Write customer snapshot, audit fields, and mark issued
            $issueDate = $locked->issue_date?->format('Y-m-d') ?? now()->toDateString();

            $locked->update([
                'number' => $number,
                'status' => InvoiceStatus::Issued,
                'issue_date' => $issueDate,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_address' => $customer->address,
                'customer_tin' => $customer->tin,
                'customer_vrn' => $customer->vrn,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $invoice = $locked->fresh(['items.allocations', 'customer', 'location']);

            return new IssueInvoiceResult($invoice, $creditWarning);
        });
    }
}
