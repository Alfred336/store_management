<?php

namespace App\Livewire\Payments;

use App\Livewire\Actions\Payments\RecordPayment;
use App\Livewire\Actions\Payments\ReversePayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Money\Money;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Payments screen: list, receive payment, details, reverse. */
#[Title('Payments')]
class Index extends Component
{
    use WithPagination;

    // ─── List filters & sorting ───────────────────────────────────────────
    #[Url(as: 'q')]
    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $methodFilter = 'all';

    public string $customerFilter = '';

    public string $statusFilter = 'all';

    public string $sortBy = 'received_at';

    public string $sortDirection = 'desc';

    // ─── Modal visibility ─────────────────────────────────────────────────
    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showReverseConfirmation = false;

    // ─── Locked record id ─────────────────────────────────────────────────
    #[Locked]
    public ?int $paymentId = null;

    // ─── Form: customer picker ────────────────────────────────────────────
    public string $formCustomerSearch = '';

    #[Locked]
    public ?int $formCustomerId = null;

    public string $formCustomerName = '';

    // ─── Form: payment details ────────────────────────────────────────────
    public string $formAmount = '';

    public string $formMethod = 'cash';

    public string $formProvider = '';

    public string $formReference = '';

    public string $formReceivedAt = '';

    public string $formNotes = '';

    // ─── Form: allocations ────────────────────────────────────────────────
    /**
     * @var array<int, array{invoice_id: int, number: string, total: string, balance: string, allocated: string}>
     */
    public array $formAllocations = [];

    // ─── Idempotency ──────────────────────────────────────────────────────
    #[Locked]
    public string $idempotencyKey = '';

    // ─── Pre-fill from invoice context ───────────────────────────────────
    #[Locked]
    public ?int $contextInvoiceId = null;

    // ─── Reverse ──────────────────────────────────────────────────────────
    public string $reverseReason = '';

    public ?string $reverseError = null;

    public ?string $formError = null;

    // ─────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);
        $this->formReceivedAt = now()->toDateString();
        $this->idempotencyKey = (string) Str::uuid();
    }

    // ─── Filters ──────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->authorize('viewAny', Payment::class);
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->authorize('viewAny', Payment::class);
        $this->search = '';
        $this->resetPage();
    }

    public function updatedMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerFilter(): void
    {
        $this->resetPage();
    }

    // ─── Sorting ──────────────────────────────────────────────────────────

    public function sort(string $column): void
    {
        $this->authorize('viewAny', Payment::class);
        $allowed = ['received_at', 'number', 'amount', 'status'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    // ─── Form ─────────────────────────────────────────────────────────────

    /**
     * Open the "Receive Payment" form, optionally pre-filled with a specific invoice.
     */
    public function openForm(?int $invoiceId = null): void
    {
        $this->authorize('create', Payment::class);
        $this->resetValidation();
        $this->clearFormState();
        $this->idempotencyKey = (string) Str::uuid();
        $this->formReceivedAt = now()->toDateString();

        if ($invoiceId !== null) {
            $invoice = Invoice::query()->with('customer')->find($invoiceId);
            if ($invoice && $invoice->customer) {
                $this->contextInvoiceId = $invoiceId;
                $this->formCustomerId = $invoice->customer_id;
                $this->formCustomerName = $invoice->customer->name;
                $this->loadOpenInvoices();
                $this->setAllocationForInvoice($invoiceId);
            }
        }

        $this->showForm = true;
        $this->dispatch('modal-show', name: 'payment-form');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->clearFormState();
        $this->dispatch('modal-hide', name: 'payment-form');
    }

    public function selectCustomer(int $customerId): void
    {
        $this->authorize('create', Payment::class);
        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            return;
        }
        $this->formCustomerId = $customer->id;
        $this->formCustomerName = $customer->name;
        $this->formCustomerSearch = '';
        $this->contextInvoiceId = null;
        $this->loadOpenInvoices();
    }

    public function updatedFormAmount(): void
    {
        $this->autoAllocate();
    }

    /**
     * Distribute the entered amount to open invoices, oldest-issued-date first.
     */
    public function autoAllocate(): void
    {
        $formAmount = $this->formAmount;
        $remaining = app(Money::class)->decimal(
            $formAmount !== '' && is_numeric($formAmount) ? $formAmount : '0.00'
        );

        foreach ($this->formAllocations as $i => $alloc) {
            if (bccomp($remaining, '0.00', 2) <= 0) {
                $this->formAllocations[$i]['allocated'] = '0.00';

                continue;
            }

            $balance = app(Money::class)->decimal($alloc['balance']);
            $apply = bccomp($remaining, $balance, 2) >= 0 ? $balance : $remaining;
            $this->formAllocations[$i]['allocated'] = $apply;
            $remaining = bcsub($remaining, $apply, 2);
        }
    }

    public function store(RecordPayment $recordPayment): void
    {
        $this->authorize('create', Payment::class);
        $this->formError = null;

        $allocations = [];
        foreach ($this->formAllocations as $alloc) {
            if (bccomp(app(Money::class)->decimal($alloc['allocated']), '0.00', 2) > 0) {
                $allocations[] = ['invoice_id' => $alloc['invoice_id'], 'amount' => $alloc['allocated']];
            }
        }

        try {
            $recordPayment([
                'customer_id' => $this->formCustomerId,
                'method' => $this->formMethod,
                'provider' => $this->formProvider ?: null,
                'reference' => $this->formReference ?: null,
                'amount' => $this->formAmount,
                'received_at' => $this->formReceivedAt,
                'notes' => $this->formNotes ?: null,
                'allocations' => $allocations,
            ], $this->idempotencyKey);

            $this->closeForm();
            Flux::toast(variant: 'success', text: 'Payment recorded.');
        } catch (ValidationException $e) {
            $this->formError = collect($e->errors())->flatten()->implode(' ');
        }
    }

    // ─── Details ──────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $payment = Payment::query()->findOrFail($id);
        $this->authorize('view', $payment);
        $this->paymentId = $payment->id;
        $this->reverseReason = '';
        $this->reverseError = null;
        $this->showDetails = true;
        $this->dispatch('modal-show', name: 'payment-details');
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->paymentId = null;
        $this->dispatch('modal-hide', name: 'payment-details');
    }

    // ─── Reverse ──────────────────────────────────────────────────────────

    public function openReverseConfirmation(): void
    {
        $payment = Payment::query()->findOrFail($this->requiredPaymentId());
        $this->authorize('reverse', $payment);
        $this->reverseReason = '';
        $this->reverseError = null;
        $this->showReverseConfirmation = true;
    }

    public function reverse(ReversePayment $reversePayment): void
    {
        $payment = Payment::query()->findOrFail($this->requiredPaymentId());
        $this->authorize('reverse', $payment);
        $this->reverseError = null;

        try {
            $reversePayment($payment, $this->reverseReason);
            $this->showReverseConfirmation = false;
            $this->closeDetails();
            Flux::toast(variant: 'success', text: 'Payment reversed.');
        } catch (ValidationException $e) {
            $this->reverseError = collect($e->errors())->flatten()->implode(' ');
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render(): View
    {
        $this->authorize('viewAny', Payment::class);

        $sortBy = in_array($this->sortBy, ['received_at', 'number', 'amount', 'status'], true) ? $this->sortBy : 'received_at';
        $sortDir = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        $payments = Payment::query()
            ->with(['customer:id,name,code', 'receiver:id,name'])
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('number', 'ILIKE', $term)
                        ->orWhere('reference', 'ILIKE', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', $term));
                });
            })
            ->when($this->methodFilter !== 'all', fn (Builder $q) => $q->where('method', $this->methodFilter))
            ->when($this->statusFilter !== 'all', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('received_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('received_at', '<=', $this->dateTo))
            ->when($this->customerFilter !== '', fn (Builder $q) => $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', '%'.$this->customerFilter.'%')))
            ->orderBy($sortBy, $sortDir)
            ->paginate(20);

        // Filtered totals
        $totalsQuery = Payment::query()
            ->where('status', 'active')
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('number', 'ILIKE', $term)
                        ->orWhere('reference', 'ILIKE', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', $term));
                });
            })
            ->when($this->methodFilter !== 'all', fn (Builder $q) => $q->where('method', $this->methodFilter))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('received_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('received_at', '<=', $this->dateTo))
            ->when($this->customerFilter !== '', fn (Builder $q) => $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', '%'.$this->customerFilter.'%')));

        $filteredTotal = number_format((float) $totalsQuery->sum('amount'), 2);
        $filteredCount = $totalsQuery->count();

        // Customer search results for the form
        $customerResults = [];
        if (strlen($this->formCustomerSearch) >= 2) {
            $customerResults = Customer::query()
                ->where('is_active', true)
                ->where(function (Builder $q): void {
                    $term = '%'.$this->formCustomerSearch.'%';
                    $q->where('name', 'ILIKE', $term)->orWhere('code', 'ILIKE', $term);
                })
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'code'])
                ->toArray();
        }

        // Details panel
        $detailPayment = null;
        $detailCredit = null;
        if ($this->paymentId !== null) {
            $detailPayment = Payment::query()
                ->with(['customer', 'receiver', 'reverser', 'allocations.invoice'])
                ->find($this->paymentId);
            if ($detailPayment) {
                $detailCredit = $detailPayment->unallocatedCredit();
            }
        }

        return view('livewire.payments.index', [
            'payments' => $payments,
            'filteredTotal' => $filteredTotal,
            'filteredCount' => $filteredCount,
            'customerResults' => $customerResults,
            'detailPayment' => $detailPayment,
            'detailCredit' => $detailCredit,
            'methods' => ['cash', 'mobile_money', 'bank_transfer', 'cheque', 'card'],
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    private function requiredPaymentId(): int
    {
        if ($this->paymentId === null) {
            abort(422, 'No payment selected.');
        }

        return $this->paymentId;
    }

    private function clearFormState(): void
    {
        $this->formCustomerSearch = '';
        $this->formCustomerId = null;
        $this->formCustomerName = '';
        $this->formAmount = '';
        $this->formMethod = 'cash';
        $this->formProvider = '';
        $this->formReference = '';
        $this->formNotes = '';
        $this->formAllocations = [];
        $this->contextInvoiceId = null;
        $this->formError = null;
    }

    private function loadOpenInvoices(): void
    {
        if ($this->formCustomerId === null) {
            $this->formAllocations = [];

            return;
        }

        $this->formAllocations = Invoice::query()
            ->where('customer_id', $this->formCustomerId)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get(['id', 'number', 'total', 'amount_paid'])
            ->map(fn (Invoice $inv) => [
                'invoice_id' => $inv->id,
                'number' => $inv->number ?? '—',
                'total' => (string) $inv->total,
                'balance' => $inv->balance(),
                'allocated' => '0.00',
            ])
            ->values()
            ->all();
    }

    /**
     * Pre-fill the allocation row for a specific invoice with its full balance.
     */
    private function setAllocationForInvoice(int $invoiceId): void
    {
        foreach ($this->formAllocations as $i => $alloc) {
            if ($alloc['invoice_id'] === $invoiceId) {
                $this->formAllocations[$i]['allocated'] = $alloc['balance'];
                break;
            }
        }
    }
}
