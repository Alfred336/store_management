<?php

namespace App\Livewire\Invoicing;

use App\Enums\InvoiceTemplate;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Actions\Invoicing\CreateInvoiceDraft;
use App\Livewire\Actions\Invoicing\DeleteInvoiceDraft;
use App\Livewire\Actions\Invoicing\IssueInvoice;
use App\Livewire\Actions\Invoicing\UpdateInvoiceDraft;
use App\Livewire\Actions\Invoicing\VoidInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceDocument;
use App\Models\Location;
use App\Models\Product;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Settings\Settings;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Invoicing screen: list, draft creation/editing, issue, void. */
#[Title('Invoices')]
class Index extends Component
{
    use WithPagination;

    // ─── List filters & sorting ───────────────────────────────────────────
    #[Url(as: 'q')]
    public string $search = '';

    public string $statusFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $customerFilter = '';

    public ?string $locationFilter = null;

    public bool $overdueOnly = false;

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    // ─── Modal visibility ─────────────────────────────────────────────────
    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    public bool $showIssueConfirmation = false;

    public bool $showVoidConfirmation = false;

    // ─── Locked record id ─────────────────────────────────────────────────
    #[Locked]
    public ?int $invoiceId = null;

    // ─── Form: header ─────────────────────────────────────────────────────
    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public string $selectedCustomerName = '';

    public int $selectedCustomerPaymentTermsDays = 0;

    public ?int $locationId = null;

    public string $issueDate = '';

    public string $dueDate = '';

    public string $notes = '';

    public string $terms = '';

    // ─── Form: line items ─────────────────────────────────────────────────
    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public string $productSearch = '';

    // ─── Issue ────────────────────────────────────────────────────────────
    #[Locked]
    public string $idempotencyKey = '';

    public ?string $creditWarning = null;

    public ?string $issueError = null;

    // ─── Void ─────────────────────────────────────────────────────────────
    public string $voidReason = '';

    public ?string $voidError = null;

    // ─── Print / PDF ──────────────────────────────────────────────────────
    public string $printTemplate = 'classic';

    public string $printPaper = 'A4';

    // ─────────────────────────────────────────────────────────────────────

    public function mount(Settings $settings): void
    {
        $this->authorize('viewAny', Invoice::class);
        $this->issueDate = now()->toDateString();
        $this->printTemplate = (string) $settings->get('invoicing.default_template', InvoiceTemplate::default()->value);
        $this->printPaper = (string) $settings->get('invoicing.default_paper', InvoiceTemplate::default()->defaultPaper());
    }

    public function updatedPrintTemplate(): void
    {
        $template = InvoiceTemplate::tryFrom($this->printTemplate) ?? InvoiceTemplate::default();
        $this->printPaper = $template->defaultPaper();
    }

    public function updatedSearch(): void
    {
        $this->authorize('viewAny', Invoice::class);
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->authorize('viewAny', Invoice::class);
        $this->search = '';
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->authorize('viewAny', Invoice::class);
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

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOverdueOnly(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        $this->authorize('viewAny', Invoice::class);
        $allowed = ['issue_date', 'number', 'total', 'status', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    // ─── Form ─────────────────────────────────────────────────────────────

    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->clearFormState();

        if ($id === null) {
            $this->authorize('create', Invoice::class);
        } else {
            $invoice = Invoice::query()->findOrFail($id);
            $this->authorize('update', $invoice);
            $this->invoiceId = $invoice->id;
            $this->selectedCustomerId = $invoice->customer_id;
            $this->selectedCustomerName = $invoice->customer->name ?? '';
            $this->selectedCustomerPaymentTermsDays = $invoice->customer->payment_terms_days ?? 0;
            $this->locationId = $invoice->location_id;
            $this->issueDate = $invoice->issue_date?->toDateString() ?? now()->toDateString();
            $this->dueDate = $invoice->due_date?->toDateString() ?? '';
            $this->notes = $invoice->notes ?? '';
            $this->terms = $invoice->terms ?? '';

            $this->items = $invoice->items()
                ->with(['product.baseUnit', 'product.productUnits.unit'])
                ->get()
                ->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->description,
                    'product_sku' => $item->sku ?? '',
                    'tax_type' => $item->tax_type,
                    'unit_id' => $item->unit_id,
                    'available_units' => $this->buildAvailableUnits($item->product),
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'discount_amount' => (string) $item->discount_amount,
                ])
                ->values()
                ->all();
        }

        $this->showForm = true;
        $this->dispatch('modal-show', name: 'invoice-form');
    }

    public function closeForm(): void
    {
        if ($this->invoiceId !== null) {
            $invoice = Invoice::query()->find($this->invoiceId);
            if ($invoice) {
                $this->authorize('update', $invoice);
            }
        } else {
            $this->authorize('create', Invoice::class);
        }
        $this->showForm = false;
        $this->clearFormState();
        $this->dispatch('modal-close', name: 'invoice-form');
    }

    // ─── Customer picker ──────────────────────────────────────────────────

    public function selectCustomer(int $customerId): void
    {
        if ($this->invoiceId !== null) {
            $this->authorize('update', Invoice::query()->findOrFail($this->invoiceId));
        } else {
            $this->authorize('create', Invoice::class);
        }

        $customer = Customer::query()
            ->whereKey($customerId)
            ->where('is_active', true)
            ->where('is_walk_in', false)
            ->firstOrFail();

        $this->selectedCustomerId = $customer->id;
        $this->selectedCustomerName = $customer->name;
        $this->selectedCustomerPaymentTermsDays = $customer->payment_terms_days;
        $this->customerSearch = '';

        if ($this->issueDate !== '' && $customer->payment_terms_days > 0) {
            $this->dueDate = Carbon::parse($this->issueDate)->addDays($customer->payment_terms_days)->toDateString();
        }
    }

    public function updatedIssueDate(): void
    {
        if ($this->selectedCustomerPaymentTermsDays > 0 && $this->issueDate !== '') {
            $this->dueDate = Carbon::parse($this->issueDate)->addDays($this->selectedCustomerPaymentTermsDays)->toDateString();
        }
    }

    // ─── Line items ───────────────────────────────────────────────────────

    public function addLineItem(int $productId): void
    {
        if ($this->invoiceId !== null) {
            $this->authorize('update', Invoice::query()->findOrFail($this->invoiceId));
        } else {
            $this->authorize('create', Invoice::class);
        }

        $product = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with(['baseUnit', 'productUnits.unit'])
            ->firstOrFail();

        $this->items[] = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku ?? '',
            'tax_type' => $product->tax_type,
            'unit_id' => (int) $product->base_unit_id,
            'available_units' => $this->buildAvailableUnits($product),
            'quantity' => '1.000',
            'unit_price' => number_format((float) $product->selling_price, 2, '.', ''),
            'discount_amount' => '0.00',
        ];
        $this->productSearch = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    // ─── Save draft ───────────────────────────────────────────────────────

    public function store(CreateInvoiceDraft $createInvoiceDraft): void
    {
        $this->authorize('create', Invoice::class);
        $createInvoiceDraft($this->buildInvoiceData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Draft invoice saved.');
    }

    public function update(UpdateInvoiceDraft $updateInvoiceDraft): void
    {
        $invoice = Invoice::query()->findOrFail($this->requiredInvoiceId());
        $this->authorize('update', $invoice);
        $updateInvoiceDraft($invoice, $this->buildInvoiceData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Draft invoice updated.');
    }

    // ─── Details ──────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $invoice = Invoice::query()->findOrFail($id);
        $this->authorize('view', $invoice);
        $this->invoiceId = $invoice->id;
        $this->showDetails = true;
        $this->showForm = false;
        $this->dispatch('modal-show', name: 'invoice-details');
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->invoiceId = null;
        $this->dispatch('modal-close', name: 'invoice-details');
    }

    // ─── Delete draft ─────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $invoice = Invoice::query()->findOrFail($id);
        $this->authorize('delete', $invoice);
        $this->invoiceId = $invoice->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'invoice-delete');
    }

    public function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmation = false;
        $this->invoiceId = null;
        $this->dispatch('modal-close', name: 'invoice-delete');
    }

    public function deleteDraft(DeleteInvoiceDraft $deleteInvoiceDraft): void
    {
        $invoice = Invoice::query()->findOrFail($this->requiredInvoiceId());
        $this->authorize('delete', $invoice);
        $deleteInvoiceDraft($invoice);
        $this->closeDialogs();
        Flux::toast(variant: 'success', text: 'Draft invoice deleted.');
    }

    // ─── Issue ────────────────────────────────────────────────────────────

    public function openIssueConfirmation(int $id): void
    {
        $invoice = Invoice::query()->findOrFail($id);
        $this->authorize('issue', $invoice);
        $this->invoiceId = $invoice->id;
        $this->idempotencyKey = Str::uuid()->toString();
        $this->issueError = null;
        $this->creditWarning = null;
        $this->showIssueConfirmation = true;
        $this->dispatch('modal-show', name: 'invoice-issue');
    }

    public function closeIssueConfirmation(): void
    {
        $this->showIssueConfirmation = false;
        $this->issueError = null;
        $this->creditWarning = null;
        $this->invoiceId = null;
        $this->dispatch('modal-close', name: 'invoice-issue');
    }

    public function issueInvoice(IssueInvoice $issueInvoice): void
    {
        $invoice = Invoice::query()->findOrFail($this->requiredInvoiceId());
        $this->authorize('issue', $invoice);
        $this->issueError = null;

        try {
            $result = $issueInvoice($invoice, $this->idempotencyKey);
            $this->creditWarning = $result->creditWarning;
            if ($this->creditWarning === null) {
                $number = $result->invoice->number;
                $this->closeDialogs();
                Flux::toast(variant: 'success', text: "Invoice {$number} issued.");
            }
            // Credit warning: modal stays open so operator can acknowledge it.
        } catch (InsufficientStockException $e) {
            $this->issueError = $e->getMessage();
        } catch (ValidationException $e) {
            $this->issueError = collect($e->errors())->flatten()->implode(' ');
        }
    }

    public function dismissCreditWarning(): void
    {
        $number = $this->invoiceId !== null
            ? (Invoice::query()->find($this->invoiceId)->number ?? '')
            : '';
        $this->closeDialogs();
        Flux::toast(variant: 'warning', text: "Invoice {$number} issued. Credit limit exceeded — review the customer's account.");
    }

    // ─── Void ─────────────────────────────────────────────────────────────

    public function openVoidConfirmation(int $id): void
    {
        $invoice = Invoice::query()->findOrFail($id);
        $this->authorize('void', $invoice);
        $this->invoiceId = $invoice->id;
        $this->voidReason = '';
        $this->voidError = null;
        $this->showVoidConfirmation = true;
        $this->dispatch('modal-show', name: 'invoice-void');
    }

    public function closeVoidConfirmation(): void
    {
        $this->showVoidConfirmation = false;
        $this->voidError = null;
        $this->voidReason = '';
        $this->invoiceId = null;
        $this->dispatch('modal-close', name: 'invoice-void');
    }

    public function voidInvoice(VoidInvoice $voidInvoice): void
    {
        $invoice = Invoice::query()->findOrFail($this->requiredInvoiceId());
        $this->authorize('void', $invoice);
        $this->voidError = null;

        try {
            $voidInvoice($invoice, $this->voidReason);
            $this->closeDialogs();
            Flux::toast(variant: 'success', text: 'Invoice voided.');
        } catch (ValidationException $e) {
            $this->voidError = collect($e->errors())->flatten()->implode(' ');
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render(InvoiceCalculator $calculator): View
    {
        $this->authorize('viewAny', Invoice::class);

        $sortBy = in_array($this->sortBy, ['issue_date', 'number', 'total', 'status', 'created_at'], true)
            ? $this->sortBy : 'created_at';
        $sortDir = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        $invoices = Invoice::query()
            ->where('channel', 'invoice')
            ->with(['customer:id,name,code', 'location:id,name'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('number', 'ILIKE', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', $term));
                });
            })
            ->when($this->statusFilter !== 'all', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('issue_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('issue_date', '<=', $this->dateTo))
            ->when($this->customerFilter !== '', fn (Builder $q) => $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', '%'.$this->customerFilter.'%')))
            ->when($this->locationFilter !== null, fn (Builder $q) => $q->where('location_id', $this->locationFilter))
            ->when($this->overdueOnly, function (Builder $q): void {
                $q->whereIn('status', ['issued', 'partially_paid'])
                    ->where('due_date', '<', now()->toDateString())
                    ->whereRaw('total > amount_paid');
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate(20);

        // Customer search results (form picker)
        $customerResults = [];
        if (strlen($this->customerSearch) >= 2) {
            $customerResults = Customer::query()
                ->where('is_active', true)
                ->where('is_walk_in', false)
                ->where(function (Builder $q): void {
                    $term = '%'.$this->customerSearch.'%';
                    $q->where('name', 'ILIKE', $term)->orWhere('code', 'ILIKE', $term);
                })
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'code'])
                ->toArray();
        }

        // Product search results (add line item)
        $productResults = [];
        if (strlen($this->productSearch) >= 2) {
            $productResults = Product::query()
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where(function (Builder $q): void {
                    $term = '%'.$this->productSearch.'%';
                    $q->where('name', 'ILIKE', $term)
                        ->orWhere('sku', 'ILIKE', $term)
                        ->orWhere('barcode', 'ILIKE', $term);
                })
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'sku'])
                ->toArray();
        }

        // Live totals for the form
        /** @var array{subtotal: string, discount_total: string, tax_total: string, total: string, lines: array<int, array<string, mixed>>}|null */
        $liveTotals = null;
        if ($this->showForm && $this->items !== []) {
            try {
                $lines = array_map(fn (array $item): array => [
                    'quantity' => (string) ($item['quantity'] ?? '0'),
                    'unit_price' => (string) ($item['unit_price'] ?? '0'),
                    'discount_amount' => (string) ($item['discount_amount'] ?? '0.00'),
                    'tax_type' => (string) ($item['tax_type'] ?? 'standard'),
                ], $this->items);
                $liveTotals = $calculator->calculate($lines);
            } catch (\Throwable) {
                // Skip live totals when items have invalid mid-edit values.
            }
        }

        // Non-expired stock at the chosen location per product
        /** @var array<int, string> */
        $stockHints = [];
        if ($this->locationId !== null && $this->items !== []) {
            $productIds = array_values(array_filter(
                array_map(fn (array $i): ?int => isset($i['product_id']) ? (int) $i['product_id'] : null, $this->items)
            ));
            if ($productIds !== []) {
                $rows = DB::table('stock_levels')
                    ->selectRaw('stock_levels.product_id, SUM(stock_levels.quantity) as total_qty')
                    ->leftJoin('batches', 'stock_levels.batch_id', '=', 'batches.id')
                    ->whereIn('stock_levels.product_id', $productIds)
                    ->where('stock_levels.location_id', $this->locationId)
                    ->where(function (QueryBuilder $q): void {
                        $q->whereNull('stock_levels.batch_id')
                            ->orWhereNull('batches.expiry_date')
                            ->orWhere('batches.expiry_date', '>=', now()->toDateString());
                    })
                    ->groupBy('stock_levels.product_id')
                    ->get();
                foreach ($rows as $row) {
                    $stockHints[(int) $row->product_id] = (string) $row->total_qty;
                }
            }
        }

        // Active locations for the form location picker
        $locations = Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Invoice details (eager-loaded only when details panel is open)
        $selectedInvoice = null;
        if ($this->showDetails && $this->invoiceId !== null) {
            $selectedInvoice = Invoice::query()->with([
                'customer',
                'location',
                'items.unit',
                'items.product',
                'items.allocations.batch',
                'creator',
                'issuer',
                'voider',
            ])->findOrFail($this->invoiceId);
        }

        // Invoice being confirmed for issue (needed to show the total in the modal)
        $issuingInvoice = null;
        if ($this->showIssueConfirmation && $this->invoiceId !== null) {
            $issuingInvoice = Invoice::query()->find($this->invoiceId);
        }

        // Saved PDF documents for the details panel
        $printDocuments = [];
        if ($this->showDetails && $this->invoiceId !== null) {
            $printDocuments = InvoiceDocument::query()
                ->where('invoice_id', $this->invoiceId)
                ->where('action', 'save')
                ->latest()
                ->get()
                ->all();
        }

        // Template options for the picker
        $templates = InvoiceTemplate::cases();

        return view('livewire.invoicing.index', compact(
            'invoices', 'customerResults', 'productResults',
            'liveTotals', 'stockHints', 'locations',
            'selectedInvoice', 'issuingInvoice',
            'printDocuments', 'templates'
        ));
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function buildInvoiceData(): array
    {
        return [
            'customer_id' => $this->selectedCustomerId,
            'location_id' => $this->locationId,
            'issue_date' => $this->issueDate ?: null,
            'due_date' => $this->dueDate ?: null,
            'notes' => $this->notes ?: null,
            'terms' => $this->terms ?: null,
            'items' => array_map(fn (array $item): array => [
                'product_id' => $item['product_id'] ?? null,
                'unit_id' => isset($item['unit_id']) && $item['unit_id'] !== '' ? (int) $item['unit_id'] : null,
                'quantity' => (string) ($item['quantity'] ?? '0'),
                'unit_price' => (string) ($item['unit_price'] ?? '0'),
                'discount_amount' => (string) ($item['discount_amount'] ?? '0.00'),
            ], $this->items),
        ];
    }

    /**
     * Build the available unit options for a product line item.
     *
     * @return array<int, array{id: int, name: string, abbreviation: string}>
     */
    private function buildAvailableUnits(Product $product): array
    {
        $units = [[
            'id' => (int) $product->base_unit_id,
            'name' => $product->baseUnit->name ?? '',
            'abbreviation' => $product->baseUnit->abbreviation ?? '',
        ]];
        foreach ($product->productUnits as $pu) {
            $units[] = [
                'id' => (int) $pu->unit_id,
                'name' => $pu->unit->name ?? '',
                'abbreviation' => $pu->unit->abbreviation ?? '',
            ];
        }

        return $units;
    }

    private function clearFormState(): void
    {
        $this->invoiceId = null;
        $this->customerSearch = '';
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = '';
        $this->selectedCustomerPaymentTermsDays = 0;
        $this->locationId = null;
        $this->issueDate = now()->toDateString();
        $this->dueDate = '';
        $this->notes = '';
        $this->terms = '';
        $this->items = [];
        $this->productSearch = '';
        $this->resetValidation();
    }

    private function closeDialogs(): void
    {
        $this->showForm = false;
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;
        $this->showIssueConfirmation = false;
        $this->showVoidConfirmation = false;
        $this->issueError = null;
        $this->creditWarning = null;
        $this->voidError = null;
        $this->voidReason = '';
        $this->invoiceId = null;
        foreach (['invoice-form', 'invoice-details', 'invoice-delete', 'invoice-issue', 'invoice-void'] as $modal) {
            $this->dispatch('modal-close', name: $modal);
        }
    }

    private function requiredInvoiceId(): int
    {
        abort_if($this->invoiceId === null, 404);

        return $this->invoiceId;
    }
}
