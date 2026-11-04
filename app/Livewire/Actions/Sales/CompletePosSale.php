<?php

namespace App\Livewire\Actions\Sales;

use App\DTOs\IssueInvoiceResult;
use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Invoicing\IssueInvoice;
use App\Livewire\Actions\Payments\RecordPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\Product;
use App\Services\Customers\CreditLimitService;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Money\Money;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Complete a POS sale:
 *  1. Validates items, customer, session and tenders.
 *  2. For credit sales (empty tenders): requires pos.credit_sale, non-walk-in, within credit limit.
 *  3. Creates a draft invoice with channel='pos'.
 *  4. Issues it via IssueInvoice (uses RCT-… numbering, deducts stock).
 *  5. Records each tender as a Payment allocated to the invoice.
 */
class CompletePosSale
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly CreditLimitService $creditLimitService,
        private readonly IssueInvoice $issueInvoice,
        private readonly RecordPayment $recordPayment,
        private readonly StockService $stockService,
        private readonly Money $money,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id?: int, quantity: string, unit_price: string, discount_amount?: string}>  $items
     * @param  array<int, array{method: string, amount: string, reference?: string}>  $tenders  Empty = credit sale
     */
    public function __invoke(
        array $items,
        int $customerId,
        array $tenders,
        int $sessionId,
        string $idempotencyKey,
    ): IssueInvoiceResult {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'A sale must contain at least one item.']);
        }

        if (trim($idempotencyKey) === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key is required.']);
        }

        return DB::transaction(function () use ($items, $customerId, $tenders, $sessionId, $idempotencyKey): IssueInvoiceResult {
            // 1. Lock and validate session
            $session = PosSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if ($session === null || ! $session->isOpen()) {
                throw ValidationException::withMessages(['session' => 'An open POS session is required.']);
            }

            // 2. Load and validate customer
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->first();
            if ($customer === null || ! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'An active customer is required.']);
            }

            $isCreditSale = empty($tenders);

            // 3. Credit sale guards
            if ($isCreditSale) {
                abort_unless(auth()->user()?->can('pos.credit_sale'), 403);

                if ($customer->is_walk_in) {
                    throw ValidationException::withMessages(['customer_id' => 'Walk-in customer cannot take a credit sale.']);
                }
            }

            // 4. Build invoice items and calculate totals
            $vatRate = (string) $this->settings->get('tax.vat_rate', '18.00');
            $preparedLines = [];
            $calculatorLines = [];

            foreach ($items as $index => $item) {
                $product = Product::query()->whereKey($item['product_id'])
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($product === null) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => 'An active product is required.']);
                }

                $unitId = isset($item['unit_id']) && (int) $item['unit_id'] > 0
                    ? (int) $item['unit_id']
                    : (int) $product->base_unit_id;

                $quantity = (string) $item['quantity'];
                if (! is_numeric($quantity) || bccomp($quantity, '0', 3) <= 0) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'Quantity must be positive.']);
                }

                $unitPrice = $this->money->decimal($item['unit_price']);
                $discount = $this->money->round((string) ($item['discount_amount'] ?? '0.00'));
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

            foreach ($calculated['lines'] as $i => $calcLine) {
                $preparedLines[$i]['line_tax'] = $calcLine['line_tax'];
                $preparedLines[$i]['line_total'] = $calcLine['line_total'];
            }

            $invoiceTotal = $this->money->decimal($calculated['total']);

            // 5. Credit limit check for credit sales (warn → block at POS)
            if ($isCreditSale) {
                $eval = $this->creditLimitService->evaluate($customer, $invoiceTotal);
                if (in_array($eval['outcome'], ['warn', 'block'], true)) {
                    throw ValidationException::withMessages([
                        'customer_id' => "Credit limit exceeded for {$customer->name} (projected: {$eval['projected_balance']} / limit: {$eval['credit_limit']}).",
                    ]);
                }
            }

            // 6. For cash sales, validate total tendered >= invoice total
            if (! $isCreditSale) {
                $totalTendered = array_reduce(
                    $tenders,
                    fn (string $carry, array $t): string => bcadd($carry, $this->money->decimal($t['amount']), 2),
                    '0.00'
                );

                if (bccomp($totalTendered, $invoiceTotal, 2) < 0) {
                    throw ValidationException::withMessages(['tenders' => 'Total tendered is less than invoice total.']);
                }
            }

            // 7. Create draft invoice directly (bypasses ValidateAndPrepareInvoiceData walk-in check)
            $pricesIncludeTax = (bool) $this->settings->get('tax.prices_include_tax', false);

            $draft = Invoice::query()->create([
                'channel' => 'pos',
                'status' => InvoiceStatus::Draft,
                'customer_id' => $customer->id,
                'location_id' => $session->location_id,
                'pos_session_id' => $session->id,
                'currency' => (string) $this->settings->get('business.currency', 'TZS'),
                'prices_include_tax' => $pricesIncludeTax,
                'subtotal' => $calculated['subtotal'],
                'discount_total' => $calculated['discount_total'],
                'tax_total' => $calculated['tax_total'],
                'total' => $calculated['total'],
                'amount_paid' => '0.00',
                'created_by' => auth()->id(),
            ]);

            foreach ($preparedLines as $lineData) {
                $draft->items()->create($lineData);
            }

            // 8. Issue the invoice (handles RCT numbering, stock deduction, snapshot)
            $result = ($this->issueInvoice)($draft, $idempotencyKey);
            $issued = $result->invoice;

            // 9. Record tenders as payments
            if (! $isCreditSale) {
                $remainingBalance = (string) $issued->total;

                foreach ($tenders as $tender) {
                    $method = (string) $tender['method'];
                    $tenderAmount = $this->money->decimal($tender['amount']);

                    if (bccomp($tenderAmount, '0', 2) <= 0) {
                        continue;
                    }

                    // Allocate up to remaining balance
                    $allocation = bccomp($tenderAmount, $remainingBalance, 2) >= 0
                        ? $remainingBalance
                        : $tenderAmount;

                    if (bccomp($allocation, '0', 2) <= 0) {
                        break; // invoice fully paid
                    }

                    // Cash: change_given = tendered - allocated
                    $changeGiven = null;
                    if ($method === 'cash' && bccomp($tenderAmount, $allocation, 2) > 0) {
                        $changeGiven = bcsub($tenderAmount, $allocation, 2);
                    }

                    ($this->recordPayment)([
                        'customer_id' => $customer->id,
                        'method' => $method,
                        'reference' => $tender['reference'] ?? null,
                        'amount' => $tenderAmount,
                        'amount_tendered' => $tenderAmount,
                        'change_given' => $changeGiven,
                        'received_at' => now()->toDateString(),
                        'pos_session_id' => $session->id,
                        'allocations' => [
                            ['invoice_id' => $issued->id, 'amount' => $allocation],
                        ],
                    ], Str::uuid()->toString());

                    $remainingBalance = bcsub($remainingBalance, $allocation, 2);
                }
            }

            return $result;
        });
    }
}
