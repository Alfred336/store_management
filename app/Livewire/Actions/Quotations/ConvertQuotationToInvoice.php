<?php

namespace App\Livewire\Actions\Quotations;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Quotation;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Convert a sent or accepted (non-expired) quotation to an invoice DRAFT.
 *
 * Rules:
 *  - Requires quotations.convert permission (Gate::authorize via policy).
 *  - Only allowed once — idempotency guard via converted_invoice_id.
 *  - A registered customer must be on the quotation (or provided via $customerId).
 *  - Quoted line prices are carried over verbatim — this is explicitly authorised
 *    by the quotations.convert permission check and does not weaken the POS price rule
 *    (POS uses CompletePosSale, not CreateInvoiceDraft).
 *  - No stock movement; that happens later when IssueInvoice is called.
 */
class ConvertQuotationToInvoice
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  int|null  $customerId  Required when the quotation has only prospect details.
     */
    public function __invoke(Quotation $quotation, ?int $customerId = null): Invoice
    {
        Gate::authorize('convert', $quotation);

        return DB::transaction(function () use ($quotation, $customerId): Invoice {
            // Lock the quotation row to prevent double-conversion.
            $locked = Quotation::query()
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->canConvert()) {
                throw ValidationException::withMessages(['quotation' => 'Only sent or accepted quotations can be converted.']);
            }

            if ($locked->isExpired()) {
                throw ValidationException::withMessages(['quotation' => 'An expired quotation must be revised before converting.']);
            }

            if ($locked->converted_invoice_id !== null) {
                throw ValidationException::withMessages(['quotation' => 'This quotation has already been converted to an invoice.']);
            }

            // Resolve the customer — required for invoices.
            $resolvedCustomerId = $customerId ?? $locked->customer_id;
            if (! $resolvedCustomerId) {
                throw ValidationException::withMessages(['customer_id' => 'A registered customer must be selected before converting a prospect quotation to an invoice.']);
            }

            $customer = Customer::query()
                ->whereKey($resolvedCustomerId)
                ->lockForUpdate()
                ->first();

            if (! $customer || ! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'An active registered customer is required.']);
            }

            if ($customer->is_walk_in) {
                throw ValidationException::withMessages(['customer_id' => 'The Walk-in Customer cannot be used on an invoice.']);
            }

            $location = Location::query()->whereKey($locked->location_id)->first();
            if (! $location || ! $location->is_active) {
                throw ValidationException::withMessages(['location_id' => 'The quotation location is no longer active.']);
            }

            $items = $locked->items()->with('product')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'The quotation has no items.']);
            }

            foreach ($items as $item) {
                if ($item->product->trashed() || ! $item->product->is_active) {
                    throw ValidationException::withMessages(['items' => "Product [{$item->description}] is no longer active."]);
                }
            }

            // Re-calculate totals with the stored quoted prices (prices_include_tax from quotation).
            $calculatorLines = $items->map(fn ($item): array => [
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount_amount' => (string) $item->discount_amount,
                'tax_type' => $item->tax_type,
            ])->all();

            $calculated = $this->calculator->calculate($calculatorLines);
            $vatRate = (string) $this->settings->get('tax.vat_rate', '18.00');

            // Create the invoice draft.
            $invoice = Invoice::query()->create([
                'channel' => 'invoice',
                'status' => InvoiceStatus::Draft,
                'customer_id' => $customer->id,
                'location_id' => $locked->location_id,
                'quotation_id' => $locked->id,
                'issue_date' => now()->toDateString(),
                'currency' => $locked->currency,
                'prices_include_tax' => $locked->prices_include_tax,
                'subtotal' => $calculated['subtotal'],
                'discount_total' => $calculated['discount_total'],
                'tax_total' => $calculated['tax_total'],
                'total' => $calculated['total'],
                'amount_paid' => '0.00',
                'notes' => $locked->notes,
                'terms' => $locked->terms,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $index => $item) {
                $lineCalc = $calculated['lines'][$index];
                $taxRate = $item->tax_type === 'standard' ? $vatRate : '0.00';

                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'sku' => $item->sku,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'base_quantity' => $item->base_quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'tax_type' => $item->tax_type,
                    'tax_rate' => $taxRate,
                    'line_tax' => $lineCalc['line_tax'],
                    'line_total' => $lineCalc['line_total'],
                ]);
            }

            // Mark quotation as converted; link to the new invoice.
            $locked->update([
                'status' => QuotationStatus::Converted,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice->fresh(['items.unit', 'customer', 'location']);
        });
    }
}
