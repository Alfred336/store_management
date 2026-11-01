<?php

namespace App\Livewire\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateInvoiceDraft
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InvoiceCalculator $calculator,
        private readonly Settings $settings,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(array $data): Invoice
    {
        Gate::authorize('create', Invoice::class);

        return DB::transaction(function () use ($data): Invoice {
            $prepared = (new ValidateAndPrepareInvoiceData(
                $this->stockService,
                $this->calculator,
                $this->settings,
            ))->prepare($data);

            $pricesIncludeTax = (bool) $this->settings->get('tax.prices_include_tax', false);

            $invoice = Invoice::query()->create([
                'channel' => 'invoice',
                'status' => InvoiceStatus::Draft,
                'customer_id' => $prepared['customer']->id,
                'location_id' => $prepared['location']->id,
                'issue_date' => $prepared['issue_date'],
                'due_date' => $prepared['due_date'],
                'currency' => (string) $this->settings->get('business.currency', 'TZS'),
                'prices_include_tax' => $pricesIncludeTax,
                'subtotal' => $prepared['calculated']['subtotal'],
                'discount_total' => $prepared['calculated']['discount_total'],
                'tax_total' => $prepared['calculated']['tax_total'],
                'total' => $prepared['calculated']['total'],
                'amount_paid' => '0.00',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($prepared['items'] as $itemData) {
                $invoice->items()->create($itemData);
            }

            return $invoice->fresh(['items.unit', 'items.product', 'customer', 'location']);
        });
    }
}
