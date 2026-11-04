<?php

namespace App\Livewire\Sales\Pos;

use App\Livewire\Actions\Sales\ClosePosSession;
use App\Livewire\Actions\Sales\CompletePosSale;
use App\Livewire\Actions\Sales\HoldSale;
use App\Livewire\Actions\Sales\OpenPosSession;
use App\Livewire\Actions\Sales\ResumeSale;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PosHeldSale;
use App\Models\PosSession;
use App\Models\Product;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Settings\Settings;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Full-screen POS terminal: product search, cart, payment, held sales, and session management. */
#[Title('Point of Sale')]
class Index extends Component
{
    // ─── Session ──────────────────────────────────────────────────────────

    #[Locked]
    public ?int $sessionId = null;

    public string $openingCash = '0.00';

    public string $closingCash = '0.00';

    public string $sessionNotes = '';

    public ?int $openLocationId = null;

    // ─── Cart ─────────────────────────────────────────────────────────────

    /** @var array<int, array{product_id: int, name: string, sku: string, unit_id: int, quantity: numeric-string, unit_price: numeric-string, discount_amount: numeric-string, tax_type: string}> */
    public array $cart = [];

    // ─── Customer ─────────────────────────────────────────────────────────

    #[Locked]
    public ?int $customerId = null;

    public string $customerSearch = '';

    // ─── Product search ───────────────────────────────────────────────────

    public string $productSearch = '';

    // ─── UI panels ────────────────────────────────────────────────────────

    public string $activePanel = 'sale'; // 'sale'|'payment'|'session'|'held'|'receipt'

    // ─── Tenders ──────────────────────────────────────────────────────────

    /** @var array<int, array{method: string, amount: numeric-string, reference: string}> */
    public array $tenders = [];

    // ─── Receipt ──────────────────────────────────────────────────────────

    #[Locked]
    public ?int $lastInvoiceId = null;

    public string $lastReceiptNumber = '';

    public string $lastReceiptTotal = '';

    public string $lastChangeGiven = '0.00';

    // ─── Idempotency ──────────────────────────────────────────────────────

    public string $posIdempotencyKey = '';

    // ─── Mount ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        // Find an existing open session for this user
        $session = PosSession::query()
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->with('location')
            ->first();

        if ($session !== null) {
            $this->sessionId = $session->id;
        }

        $this->posIdempotencyKey = Str::uuid()->toString();
    }

    // ─── Session management ───────────────────────────────────────────────

    public function openSession(OpenPosSession $action): void
    {
        abort_unless(auth()->user()?->can('pos.open_session'), 403);

        try {
            $session = $action([
                'location_id' => (int) $this->openLocationId,
                'opening_cash' => $this->openingCash,
            ]);
            $this->sessionId = $session->id;
            $this->activePanel = 'sale';
            $this->openingCash = '0.00';
            $this->openLocationId = null;
            Flux::toast(variant: 'success', text: 'Session opened.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function closeSession(ClosePosSession $action): void
    {
        $session = $this->loadSession();
        if ($session === null) {
            return;
        }

        try {
            $action($session, [
                'closing_cash_counted' => $this->closingCash,
                'notes' => $this->sessionNotes ?: null,
            ]);
            $this->sessionId = null;
            $this->cart = [];
            $this->customerId = null;
            $this->activePanel = 'session';
            $this->closingCash = '0.00';
            $this->sessionNotes = '';
            Flux::toast(variant: 'success', text: 'Session closed.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    // ─── Product search ───────────────────────────────────────────────────

    public function addToCart(int $productId): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        $product = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->firstOrFail();

        // Check if product already in cart — bump quantity
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $product->id && $item['unit_id'] === (int) $product->base_unit_id) {
                $this->cart[$index]['quantity'] = bcadd($this->cart[$index]['quantity'], '1.000', 3);
                $this->productSearch = '';

                return;
            }
        }

        $this->cart[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku ?? '',
            'unit_id' => (int) $product->base_unit_id,
            'quantity' => '1.000',
            'unit_price' => bcadd($product->selling_price, '0', 2),
            'discount_amount' => '0.00',
            'tax_type' => $product->tax_type ?? 'standard',
        ];

        $this->productSearch = '';
    }

    public function removeFromCart(int $index): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function updateCartItem(int $index, string $field, string $value): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        if (! isset($this->cart[$index])) {
            return;
        }

        if (in_array($field, ['quantity', 'unit_price', 'discount_amount'], true) && is_numeric($value)) {
            $this->cart[$index][$field] = $value;
        }
    }

    // ─── Totals ───────────────────────────────────────────────────────────

    /** @return array{subtotal: numeric-string, discount_total: numeric-string, tax_total: numeric-string, total: numeric-string} */
    #[Computed]
    public function totals(): array
    {
        if (empty($this->cart)) {
            return ['subtotal' => '0.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'total' => '0.00'];
        }

        try {
            $lines = array_map(fn (array $item): array => [
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'],
                'tax_type' => $item['tax_type'],
            ], $this->cart);

            $calc = app(InvoiceCalculator::class)->calculate($lines);

            return [
                'subtotal' => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'tax_total' => $calc['tax_total'],
                'total' => $calc['total'],
            ];
        } catch (\Throwable) {
            return ['subtotal' => '0.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'total' => '0.00'];
        }
    }

    // ─── Payment panel ────────────────────────────────────────────────────

    public function openPaymentPanel(): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        if (empty($this->cart)) {
            Flux::toast(variant: 'warning', text: 'Add at least one item first.');

            return;
        }

        $this->tenders = [['method' => 'cash', 'amount' => $this->totals()['total'], 'reference' => '']];
        $this->activePanel = 'payment';
    }

    public function addTender(): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);
        $this->tenders[] = ['method' => 'cash', 'amount' => '0.00', 'reference' => ''];
    }

    public function removeTender(int $index): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);
        unset($this->tenders[$index]);
        $this->tenders = array_values($this->tenders);
    }

    /** @return numeric-string */
    public function totalTendered(): string
    {
        return array_reduce(
            $this->tenders,
            fn (string $carry, array $t): string => bcadd($carry, $t['amount'], 2),
            '0.00'
        );
    }

    /** @return numeric-string */
    public function changeGiven(): string
    {
        $change = bcsub($this->totalTendered(), $this->totals()['total'], 2);

        return bccomp($change, '0', 2) > 0 ? $change : '0.00';
    }

    // ─── Complete sale ────────────────────────────────────────────────────

    public function completeSale(CompletePosSale $action): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        if ($this->sessionId === null) {
            Flux::toast(variant: 'danger', text: 'Open a session first.');

            return;
        }

        $customerId = $this->customerId ?? $this->walkInCustomerId();

        if ($customerId === null) {
            Flux::toast(variant: 'danger', text: 'No walk-in customer configured.');

            return;
        }

        // Filter tenders: skip empty amounts
        $tenders = array_values(array_filter(
            $this->tenders,
            fn (array $t): bool => bccomp($t['amount'], '0', 2) > 0
        ));

        try {
            $result = $action(
                items: $this->cart,
                customerId: $customerId,
                tenders: $tenders,
                sessionId: $this->sessionId,
                idempotencyKey: $this->posIdempotencyKey,
            );

            $this->lastInvoiceId = $result->invoice->id;
            $this->lastReceiptNumber = (string) ($result->invoice->number ?? '');
            $this->lastReceiptTotal = (string) $result->invoice->total;

            // Compute total cash change
            $cashTendered = array_reduce(
                array_filter($tenders, fn (array $t): bool => $t['method'] === 'cash'),
                fn (string $carry, array $t): string => bcadd($carry, $t['amount'], 2),
                '0.00'
            );
            $this->lastChangeGiven = bccomp($cashTendered, $this->lastReceiptTotal, 2) > 0
                ? bcsub($cashTendered, $this->lastReceiptTotal, 2)
                : '0.00';

            $this->activePanel = 'receipt';
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    // ─── Held sales ───────────────────────────────────────────────────────

    public function holdSale(HoldSale $action): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        if (empty($this->cart)) {
            Flux::toast(variant: 'warning', text: 'Cart is empty.');

            return;
        }

        $session = $this->loadSession();

        if ($session === null) {
            Flux::toast(variant: 'danger', text: 'No open session.');

            return;
        }

        $action([
            'location_id' => $session->location_id,
            'pos_session_id' => $session->id,
            'customer_id' => $this->customerId,
            'cart' => $this->cart,
        ]);

        $this->newSale();
        Flux::toast(variant: 'success', text: 'Sale held.');
    }

    public function resumeSale(ResumeSale $action, int $heldSaleId): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        try {
            $held = $action($heldSaleId);
            $this->cart = $held->cart;
            $this->customerId = $held->customer_id;
            $this->activePanel = 'sale';
            Flux::toast(variant: 'success', text: 'Sale resumed.');
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function voidHeldSale(int $heldSaleId): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);
        PosHeldSale::query()->findOrFail($heldSaleId)->delete();
        Flux::toast(variant: 'success', text: 'Held sale discarded.');
    }

    // ─── Misc ─────────────────────────────────────────────────────────────

    public function newSale(): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);
        $this->cart = [];
        $this->customerId = null;
        $this->customerSearch = '';
        $this->tenders = [];
        $this->lastInvoiceId = null;
        $this->lastReceiptNumber = '';
        $this->lastReceiptTotal = '';
        $this->lastChangeGiven = '0.00';
        $this->posIdempotencyKey = Str::uuid()->toString();
        $this->activePanel = 'sale';
        $this->resetValidation();
    }

    public function setCustomer(?int $customerId): void
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);
        $this->customerId = $customerId;
        $this->customerSearch = '';
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render(): View
    {
        abort_unless(auth()->user()?->can('pos.use'), 403);

        $session = $this->sessionId !== null
            ? PosSession::query()->with('location')->find($this->sessionId)
            : null;

        $productResults = $this->productSearch !== ''
            ? Product::query()
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where(function (Builder $q): void {
                    $pattern = '%'.$this->productSearch.'%';
                    $q->where('name', 'ILIKE', $pattern)
                        ->orWhere('sku', 'ILIKE', $pattern)
                        ->orWhere('barcode', 'ILIKE', $pattern);
                })
                ->limit(20)
                ->get()
            : collect();

        $heldSales = $this->sessionId !== null
            ? PosHeldSale::query()
                ->where('pos_session_id', $this->sessionId)
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        $customerResults = $this->customerSearch !== ''
            ? Customer::query()
                ->where('is_active', true)
                ->where(function (Builder $q): void {
                    $pattern = '%'.$this->customerSearch.'%';
                    $q->where('name', 'ILIKE', $pattern)
                        ->orWhere('code', 'ILIKE', $pattern)
                        ->orWhere('phone', 'ILIKE', $pattern);
                })
                ->limit(10)
                ->get()
            : collect();

        $selectedCustomer = $this->customerId !== null
            ? Customer::query()->find($this->customerId)
            : null;

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();

        $currency = app(Settings::class)->get('business.currency', 'TZS');

        return view('livewire.sales.pos.index', compact(
            'session', 'productResults', 'heldSales', 'customerResults', 'selectedCustomer', 'locations', 'currency'
        ))->layout('layouts.pos');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function loadSession(): ?PosSession
    {
        if ($this->sessionId === null) {
            return null;
        }

        return PosSession::query()->find($this->sessionId);
    }

    private function walkInCustomerId(): ?int
    {
        return Customer::query()->where('is_walk_in', true)->value('id');
    }
}
