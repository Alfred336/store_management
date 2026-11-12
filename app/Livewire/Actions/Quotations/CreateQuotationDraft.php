<?php

namespace App\Livewire\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateQuotationDraft
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InvoiceCalculator $calculator,
        private readonly Settings $settings,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(array $data): Quotation
    {
        Gate::authorize('create', Quotation::class);

        return DB::transaction(function () use ($data): Quotation {
            $prepared = (new ValidateAndPrepareQuotationData(
                $this->stockService,
                $this->calculator,
                $this->settings,
            ))->prepare($data);

            $pricesIncludeTax = (bool) $this->settings->get('tax.prices_include_tax', false);

            $quotation = Quotation::query()->create([
                'status' => QuotationStatus::Draft,
                'customer_id' => $prepared['customer']?->id,
                'prospect_name' => $prepared['prospect_name'],
                'prospect_phone' => $prepared['prospect_phone'],
                'prospect_email' => $prepared['prospect_email'],
                'prospect_address' => $prepared['prospect_address'],
                'location_id' => $prepared['location']->id,
                'quote_date' => $prepared['quote_date'],
                'valid_until' => $prepared['valid_until'],
                'currency' => (string) $this->settings->get('business.currency', 'TZS'),
                'prices_include_tax' => $pricesIncludeTax,
                'subtotal' => $prepared['calculated']['subtotal'],
                'discount_total' => $prepared['calculated']['discount_total'],
                'tax_total' => $prepared['calculated']['tax_total'],
                'total' => $prepared['calculated']['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'revision' => 1,
                'created_by' => auth()->id(),
            ]);

            foreach ($prepared['items'] as $itemData) {
                $quotation->items()->create($itemData);
            }

            return $quotation->fresh(['items.unit', 'items.product', 'customer', 'location']);
        });
    }
}
