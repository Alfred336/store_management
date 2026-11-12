<?php

namespace App\Livewire\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Create a new DRAFT revision from a sent, rejected, or expired quotation.
 *
 * The original quotation is left unchanged for history.
 * The new draft has revision = old.revision + 1 and revised_from_id = old.id.
 */
class ReviseQuotation
{
    public function __construct(private readonly Settings $settings) {}

    public function __invoke(Quotation $quotation): Quotation
    {
        Gate::authorize('revise', $quotation);

        return DB::transaction(function () use ($quotation): Quotation {
            $locked = Quotation::query()
                ->with('items')
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $canRevise = $locked->status === QuotationStatus::Sent
                || $locked->status === QuotationStatus::Rejected
                || $locked->isExpired();

            if (! $canRevise) {
                throw ValidationException::withMessages(['quotation' => 'Only sent, rejected, or expired quotations can be revised.']);
            }

            $newDraft = Quotation::query()->create([
                'status' => QuotationStatus::Draft,
                'customer_id' => $locked->customer_id,
                'prospect_name' => $locked->prospect_name,
                'prospect_phone' => $locked->prospect_phone,
                'prospect_email' => $locked->prospect_email,
                'prospect_address' => $locked->prospect_address,
                'location_id' => $locked->location_id,
                'quote_date' => now()->toDateString(),
                'valid_until' => $locked->valid_until->isFuture()
                    ? $locked->valid_until->toDateString()
                    : now()->addDays((int) $this->settings->get('quotations.validity_days', 14))->toDateString(),
                'currency' => $locked->currency,
                'prices_include_tax' => $locked->prices_include_tax,
                'subtotal' => $locked->subtotal,
                'discount_total' => $locked->discount_total,
                'tax_total' => $locked->tax_total,
                'total' => $locked->total,
                'notes' => $locked->notes,
                'terms' => $locked->terms,
                'revision' => $locked->revision + 1,
                'revised_from_id' => $locked->id,
                'created_by' => auth()->id(),
            ]);

            foreach ($locked->items as $item) {
                $newDraft->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'sku' => $item->sku,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'base_quantity' => $item->base_quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'tax_type' => $item->tax_type,
                    'tax_rate' => $item->tax_rate,
                    'line_tax' => $item->line_tax,
                    'line_total' => $item->line_total,
                ]);
            }

            return $newDraft->fresh(['items.unit', 'customer', 'location']);
        });
    }
}
