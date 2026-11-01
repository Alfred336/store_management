<?php

namespace App\Livewire\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class UpdateInvoiceDraft
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InvoiceCalculator $calculator,
        private readonly Settings $settings,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(Invoice $invoice, array $data): Invoice
    {
        Gate::authorize('update', $invoice);

        return DB::transaction(function () use ($invoice, $data): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== InvoiceStatus::Draft) {
                throw new InvalidArgumentException('Only draft invoices can be updated.');
            }

            $prepared = (new ValidateAndPrepareInvoiceData(
                $this->stockService,
                $this->calculator,
                $this->settings,
            ))->prepare($data);

            $pricesIncludeTax = (bool) $this->settings->get('tax.prices_include_tax', false);

            $locked->items()->delete();

            $locked->update([
                'customer_id' => $prepared['customer']->id,
                'location_id' => $prepared['location']->id,
                'issue_date' => $prepared['issue_date'],
                'due_date' => $prepared['due_date'],
                'prices_include_tax' => $pricesIncludeTax,
                'subtotal' => $prepared['calculated']['subtotal'],
                'discount_total' => $prepared['calculated']['discount_total'],
                'tax_total' => $prepared['calculated']['tax_total'],
                'total' => $prepared['calculated']['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            foreach ($prepared['items'] as $itemData) {
                $locked->items()->create($itemData);
            }

            return $locked->fresh(['items.unit', 'items.product', 'customer', 'location']);
        });
    }
}
