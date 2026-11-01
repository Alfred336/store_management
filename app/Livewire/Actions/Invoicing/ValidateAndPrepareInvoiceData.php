<?php

namespace App\Livewire\Actions\Invoicing;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Money\Money;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Validates invoice payload rules and calculates line items with snapshots and totals.
 */
class ValidateAndPrepareInvoiceData
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InvoiceCalculator $calculator,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{customer: Customer, location: Location, issue_date: string|null, due_date: string|null, items: list<array<string, mixed>>, calculated: array<string, mixed>}
     */
    public function prepare(array $data): array
    {
        $itemsData = $data['items'] ?? [];
        if (! is_array($itemsData) || count($itemsData) === 0) {
            throw ValidationException::withMessages(['items' => 'An invoice must contain at least one item.']);
        }

        $customerId = $data['customer_id'] ?? null;
        $customer = Customer::query()->whereKey($customerId)->first();
        if (! $customer || ! $customer->is_active) {
            throw ValidationException::withMessages(['customer_id' => 'An active customer is required.']);
        }
        if ($customer->is_walk_in) {
            throw ValidationException::withMessages(['customer_id' => 'The Walk-in Customer is not allowed on invoice channel.']);
        }

        $locationId = $data['location_id'] ?? null;
        $location = Location::query()->whereKey($locationId)->first();
        if (! $location || ! $location->is_active) {
            throw ValidationException::withMessages(['location_id' => 'An active location is required.']);
        }

        $issueDate = isset($data['issue_date']) && $data['issue_date'] !== ''
            ? Carbon::parse($data['issue_date'])->toDateString()
            : now()->toDateString();

        $dueDate = isset($data['due_date']) && $data['due_date'] !== ''
            ? Carbon::parse($data['due_date'])->toDateString()
            : null;

        if ($dueDate !== null && $dueDate < $issueDate) {
            throw ValidationException::withMessages(['due_date' => 'Due date must be on or after issue date.']);
        }

        $vatRate = (string) $this->settings->get('tax.vat_rate', '18.00');
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
            $quantity = $rawQty;

            $rawPrice = (string) ($item['unit_price'] ?? '0');
            if (! is_numeric($rawPrice) || bccomp($rawPrice, '0', 2) < 0) {
                throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Unit price cannot be negative.']);
            }
            $unitPrice = $money->decimal($rawPrice);

            $unitId = isset($item['unit_id']) && $item['unit_id'] !== ''
                ? (int) $item['unit_id']
                : (int) $product->base_unit_id;

            $isValidUnit = ($unitId === (int) $product->base_unit_id)
                || $product->productUnits()->where('unit_id', $unitId)->exists();

            if (! $isValidUnit) {
                throw ValidationException::withMessages(["items.{$index}.unit_id" => 'Selected unit is not valid for this product.']);
            }

            $rawDiscount = (string) ($item['discount_amount'] ?? '0.00');
            if (! is_numeric($rawDiscount) || bccomp($rawDiscount, '0', 2) < 0) {
                throw ValidationException::withMessages(["items.{$index}.discount_amount" => 'Discount amount cannot be negative.']);
            }
            $discount = $money->round($rawDiscount);

            $lineSubtotal = $money->round(bcmul($quantity, $unitPrice, 8));
            if ($money->compare($discount, $lineSubtotal) > 0) {
                throw ValidationException::withMessages(["items.{$index}.discount_amount" => 'Discount amount cannot exceed line subtotal.']);
            }

            $baseQuantity = $this->stockService->convertToBase($product, $unitId, $quantity);
            $taxType = $product->tax_type ?? 'standard';
            $taxRate = $taxType === 'standard' ? $vatRate : '0.00';

            $preparedLines[] = [
                'product_id' => $product->id,
                'description' => $product->name,
                'sku' => $product->sku,
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_type' => $taxType,
                'tax_rate' => $taxRate,
            ];

            $calculatorLines[] = [
                'quantity' => $quantity,
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
            'location' => $location,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'items' => $preparedLines,
            'calculated' => $calculated,
        ];
    }
}
