<?php

namespace App\Livewire\Actions\Quotations;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Money\Money;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Validates quotation payload and calculates line totals.
 *
 * Key differences from invoice validation:
 *  - customer_id OR prospect_name required (not both mandatory).
 *  - unit_price is negotiated; callers with quotations.update may set any non-negative price.
 *  - discount above pos.max_discount_percent requires quotations.approve_discount.
 *  - valid_until defaults to quote_date + quotations.validity_days setting.
 */
class ValidateAndPrepareQuotationData
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InvoiceCalculator $calculator,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data, bool $allowOverridePrice = true): array
    {
        $itemsData = $data['items'] ?? [];
        if (! is_array($itemsData) || count($itemsData) === 0) {
            throw ValidationException::withMessages(['items' => 'A quotation must contain at least one item.']);
        }

        // ── Customer or prospect ───────────────────────────────────────────────
        $customerId = $data['customer_id'] ?? null;
        $prospectName = isset($data['prospect_name']) ? trim((string) $data['prospect_name']) : '';
        $customer = null;

        if ($customerId) {
            $customer = Customer::query()->whereKey($customerId)->first();
            if (! $customer || ! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'An active customer is required.']);
            }
        } elseif ($prospectName === '') {
            throw ValidationException::withMessages(['prospect_name' => 'A customer or prospect name is required.']);
        }

        // ── Location ──────────────────────────────────────────────────────────
        $locationId = $data['location_id'] ?? null;
        $location = Location::query()->whereKey($locationId)->first();
        if (! $location || ! $location->is_active) {
            throw ValidationException::withMessages(['location_id' => 'An active location is required.']);
        }

        // ── Dates ─────────────────────────────────────────────────────────────
        $quoteDate = isset($data['quote_date']) && $data['quote_date'] !== ''
            ? Carbon::parse($data['quote_date'])->toDateString()
            : now()->toDateString();

        $validityDays = (int) $this->settings->get('quotations.validity_days', 14);
        $validUntil = isset($data['valid_until']) && $data['valid_until'] !== ''
            ? Carbon::parse($data['valid_until'])->toDateString()
            : Carbon::parse($quoteDate)->addDays($validityDays)->toDateString();

        if ($validUntil < $quoteDate) {
            throw ValidationException::withMessages(['valid_until' => 'Valid until date must be on or after the quote date.']);
        }

        // ── Line items ────────────────────────────────────────────────────────
        $vatRate = (string) $this->settings->get('tax.vat_rate', '18.00');
        $maxDiscountPct = $this->settings->get('pos.max_discount_percent', null);
        $money = app(Money::class);

        $preparedLines = [];
        $calculatorLines = [];

        foreach ($itemsData as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $product = Product::query()->whereKey($productId)->first();
            if (! $product || $product->trashed() || ! $product->is_active) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'An active product is required.']);
            }

            $rawQty = (string) ($item['quantity'] ?? '0');
            if (! is_numeric($rawQty) || bccomp($rawQty, '0', 3) <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'Quantity must be positive.']);
            }

            $unitId = isset($item['unit_id']) && $item['unit_id'] !== ''
                ? (int) $item['unit_id']
                : (int) $product->base_unit_id;

            $isValidUnit = ($unitId === (int) $product->base_unit_id)
                || $product->productUnits()->where('unit_id', $unitId)->exists();
            if (! $isValidUnit) {
                throw ValidationException::withMessages(["items.{$index}.unit_id" => 'Selected unit is not valid for this product.']);
            }

            // Negotiated price — default to selling_price × packaging factor.
            if (isset($item['unit_price']) && $item['unit_price'] !== '' && $allowOverridePrice) {
                $rawPrice = (string) $item['unit_price'];
            } else {
                // Default: selling_price, adjusted for packaging factor if non-base unit.
                $basePrice = (string) $product->selling_price;
                if ($unitId !== (int) $product->base_unit_id) {
                    $pu = $product->productUnits()->where('unit_id', $unitId)->first();
                    $rawPrice = $pu ? $money->round(bcmul($basePrice, (string) $pu->factor, 8)) : $basePrice;
                } else {
                    $rawPrice = $basePrice;
                }
            }

            if (! is_numeric($rawPrice) || bccomp($rawPrice, '0', 2) < 0) {
                throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Unit price cannot be negative.']);
            }
            $unitPrice = $money->decimal($rawPrice);

            $rawDiscount = (string) ($item['discount_amount'] ?? '0.00');
            if (! is_numeric($rawDiscount) || bccomp($rawDiscount, '0', 2) < 0) {
                throw ValidationException::withMessages(["items.{$index}.discount_amount" => 'Discount amount cannot be negative.']);
            }
            $discount = $money->round($rawDiscount);

            $lineSubtotal = $money->round(bcmul($rawQty, $unitPrice, 8));
            if ($money->compare($discount, $lineSubtotal) > 0) {
                throw ValidationException::withMessages(["items.{$index}.discount_amount" => 'Discount amount cannot exceed line subtotal.']);
            }

            // Discount percentage check.
            if (is_string($maxDiscountPct) && is_numeric($maxDiscountPct) && bccomp($lineSubtotal, '0', 2) > 0) {
                $discountPct = bcmul(bcdiv($discount, $lineSubtotal, 8), '100', 4);
                if (bccomp($discountPct, $maxDiscountPct, 4) > 0) {
                    // Requires approve_discount permission.
                    Gate::authorize('approveDiscount', Quotation::class);
                }
            }

            $baseQuantity = $this->stockService->convertToBase($product, $unitId, $rawQty);
            $taxType = $product->tax_type ?? 'standard';
            $taxRate = $taxType === 'standard' ? $vatRate : '0.00';

            $preparedLines[] = [
                'product_id' => $product->id,
                'description' => $product->name,
                'sku' => $product->sku,
                'unit_id' => $unitId,
                'quantity' => $rawQty,
                'base_quantity' => $baseQuantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_type' => $taxType,
                'tax_rate' => $taxRate,
            ];

            $calculatorLines[] = [
                'quantity' => $rawQty,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_type' => $taxType,
            ];
        }

        $calculated = $this->calculator->calculate($calculatorLines);

        foreach ($calculated['lines'] as $index => $calcLine) {
            $preparedLines[$index]['line_tax'] = $calcLine['line_tax'];
            $preparedLines[$index]['line_total'] = $calcLine['line_total'];
        }

        return [
            'customer' => $customer,
            'prospect_name' => $prospectName ?: null,
            'prospect_phone' => isset($data['prospect_phone']) ? trim((string) $data['prospect_phone']) ?: null : null,
            'prospect_email' => isset($data['prospect_email']) ? trim((string) $data['prospect_email']) ?: null : null,
            'prospect_address' => isset($data['prospect_address']) ? trim((string) $data['prospect_address']) ?: null : null,
            'location' => $location,
            'quote_date' => $quoteDate,
            'valid_until' => $validUntil,
            'items' => $preparedLines,
            'calculated' => $calculated,
        ];
    }
}
