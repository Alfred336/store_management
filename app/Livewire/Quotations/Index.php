<?php

namespace App\Livewire\Quotations;

use App\Enums\InvoiceTemplate;
use App\Enums\QuotationStatus;
use App\Livewire\Actions\Quotations\AcceptQuotation;
use App\Livewire\Actions\Quotations\ConvertQuotationToInvoice;
use App\Livewire\Actions\Quotations\CreateQuotationDraft;
use App\Livewire\Actions\Quotations\DeleteQuotationDraft;
use App\Livewire\Actions\Quotations\RejectQuotation;
use App\Livewire\Actions\Quotations\ReviseQuotation;
use App\Livewire\Actions\Quotations\SendQuotation;
use App\Livewire\Actions\Quotations\UpdateQuotationDraft;
use App\Mail\QuotationMail;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockLevel;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Quotations\QuotationDocumentData;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Quotations screen: list, draft creation/editing, send, accept, reject, revise, convert. */
#[Title('Quotations')]
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

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    // ─── Modal visibility ─────────────────────────────────────────────────
    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    public bool $showSendConfirmation = false;

    public bool $showStatusPanel = false;

    public bool $showEmailPanel = false;

    public bool $showConvertPanel = false;

    // ─── Locked record id ─────────────────────────────────────────────────
    #[Locked]
    public ?int $quotationId = null;

    // ─── Form: mode (registered customer vs prospect) ─────────────────────
    public string $recipientMode = 'customer'; // 'customer' | 'prospect'

    // ─── Form: customer picker ────────────────────────────────────────────
    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public string $selectedCustomerName = '';

    // ─── Form: prospect ───────────────────────────────────────────────────
    public string $prospectName = '';

    public string $prospectPhone = '';

    public string $prospectEmail = '';

    public string $prospectAddress = '';

    // ─── Form: header ─────────────────────────────────────────────────────
    public ?int $locationId = null;

    public string $quoteDate = '';

    public string $validUntil = '';

    public string $notes = '';

    public string $terms = '';

    // ─── Form: line items ─────────────────────────────────────────────────
    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public string $productSearch = '';

    // ─── Status actions ───────────────────────────────────────────────────
    public string $statusAction = ''; // 'accept' | 'reject'

    public string $rejectReason = '';

    public ?string $statusError = null;

    // ─── Convert ──────────────────────────────────────────────────────────
    public string $convertCustomerSearch = '';

    public ?int $convertCustomerId = null;

    public string $convertCustomerName = '';

    public ?string $convertError = null;

    // ─── Email ────────────────────────────────────────────────────────────
    public string $emailTo = '';

    public string $emailMessage = '';

    public string $emailTemplate = 'classic';

    public ?string $emailError = null;

    // ─── Print ────────────────────────────────────────────────────────────
    public string $printTemplate = 'classic';

    public string $printPaper = 'A4';

    // ─────────────────────────────────────────────────────────────────────

    public function mount(Settings $settings): void
    {
        $this->authorize('viewAny', Quotation::class);
        $this->quoteDate = now()->toDateString();
        $this->validUntil = now()->addDays((int) $settings->get('quotations.validity_days', 14))->toDateString();
        $this->terms = (string) $settings->get('quotations.default_terms', '');
        $this->printTemplate = (string) $settings->get('invoicing.default_template', InvoiceTemplate::default()->value);
        $this->printPaper = (string) $settings->get('invoicing.default_paper', InvoiceTemplate::default()->defaultPaper());
    }

    public function updatedSearch(): void
    {
        $this->authorize('viewAny', Quotation::class);
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->authorize('viewAny', Quotation::class);
        $this->search = '';
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->authorize('viewAny', Quotation::class);
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

    public function sort(string $column): void
    {
        $this->authorize('viewAny', Quotation::class);
        $allowed = ['number', 'quote_date', 'valid_until', 'total', 'status', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    // ─── Form open/close ──────────────────────────────────────────────────

    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->clearFormState();

        if ($id === null) {
            $this->authorize('create', Quotation::class);
        } else {
            $quotation = Quotation::query()->findOrFail($id);
            $this->authorize('update', $quotation);
            $this->quotationId = $quotation->id;

            // Recipient mode
            if ($quotation->customer_id !== null) {
                $this->recipientMode = 'customer';
                $this->selectedCustomerId = $quotation->customer_id;
                $this->selectedCustomerName = $quotation->customer->name ?? '';
            } else {
                $this->recipientMode = 'prospect';
                $this->prospectName = $quotation->prospect_name ?? '';
                $this->prospectPhone = $quotation->prospect_phone ?? '';
                $this->prospectEmail = $quotation->prospect_email ?? '';
                $this->prospectAddress = $quotation->prospect_address ?? '';
            }

            $this->locationId = $quotation->location_id;
            $this->quoteDate = $quotation->quote_date->toDateString();
            $this->validUntil = $quotation->valid_until->toDateString();
            $this->notes = $quotation->notes ?? '';
            $this->terms = $quotation->terms ?? '';

            $this->items = $quotation->items()
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
        $this->dispatch('modal-show', name: 'quotation-form');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->clearFormState();
        $this->dispatch('modal-close', name: 'quotation-form');
    }

    // ─── Customer picker ──────────────────────────────────────────────────

    public function selectCustomer(int $customerId): void
    {
        if ($this->quotationId !== null) {
            $this->authorize('update', Quotation::query()->findOrFail($this->quotationId));
        } else {
            $this->authorize('create', Quotation::class);
        }

        $customer = Customer::query()
            ->whereKey($customerId)
            ->where('is_active', true)
            ->firstOrFail();

        $this->selectedCustomerId = $customer->id;
        $this->selectedCustomerName = $customer->name;
        $this->customerSearch = '';
    }

    // ─── Line items ───────────────────────────────────────────────────────

    public function addLineItem(int $productId): void
    {
        if ($this->quotationId !== null) {
            $this->authorize('update', Quotation::query()->findOrFail($this->quotationId));
        } else {
            $this->authorize('create', Quotation::class);
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

    public function store(
        CreateQuotationDraft $create,
        Settings $settings,
    ): void {
        $this->authorize('create', Quotation::class);
        $create($this->buildQuotationData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Draft quotation saved.');
    }

    public function update(UpdateQuotationDraft $update): void
    {
        $quotation = Quotation::query()->findOrFail($this->requiredId());
        $this->authorize('update', $quotation);
        $update($quotation, $this->buildQuotationData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Draft quotation updated.');
    }

    // ─── Details ──────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $quotation = Quotation::query()->findOrFail($id);
        $this->authorize('view', $quotation);
        $this->quotationId = $quotation->id;
        $this->showDetails = true;
        $this->showForm = false;
        $this->dispatch('modal-show', name: 'quotation-details');
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->quotationId = null;
        $this->dispatch('modal-close', name: 'quotation-details');
    }

    // ─── Delete draft ─────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $quotation = Quotation::query()->findOrFail($id);
        $this->authorize('delete', $quotation);
        $this->quotationId = $quotation->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'quotation-delete');
    }

    public function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmation = false;
        $this->quotationId = null;
        $this->dispatch('modal-close', name: 'quotation-delete');
    }

    public function deleteDraft(DeleteQuotationDraft $delete): void
    {
        $quotation = Quotation::query()->findOrFail($this->requiredId());
        $this->authorize('delete', $quotation);
        $delete($quotation);
        $this->closeDeleteConfirmation();
        Flux::toast(variant: 'success', text: 'Draft quotation deleted.');
    }

    // ─── Send ─────────────────────────────────────────────────────────────

    public function openSendConfirmation(int $id): void
    {
        $quotation = Quotation::query()->findOrFail($id);
        $this->authorize('send', $quotation);
        $this->quotationId = $quotation->id;
        $this->showSendConfirmation = true;
        $this->dispatch('modal-show', name: 'quotation-send');
    }

    public function closeSendConfirmation(): void
    {
        $this->showSendConfirmation = false;
        $this->quotationId = null;
        $this->dispatch('modal-close', name: 'quotation-send');
    }

    public function sendQuotation(SendQuotation $send): void
    {
        $quotation = Quotation::query()->findOrFail($this->requiredId());
        $this->authorize('send', $quotation);

        try {
            $sent = $send($quotation);
            $this->closeSendConfirmation();
            Flux::toast(variant: 'success', text: "Quotation {$sent->number} sent.");
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());
        }
    }

    // ─── Accept / Reject ──────────────────────────────────────────────────

    public function openStatusPanel(int $id, string $action): void
    {
        $quotation = Quotation::query()->findOrFail($id);

        if ($action === 'accept') {
            $this->authorize('accept', $quotation);
        } else {
            $this->authorize('reject', $quotation);
        }

        $this->quotationId = $quotation->id;
        $this->statusAction = $action;
        $this->rejectReason = '';
        $this->statusError = null;
        $this->showStatusPanel = true;
        $this->dispatch('modal-show', name: 'quotation-status');
    }

    public function closeStatusPanel(): void
    {
        $this->showStatusPanel = false;
        $this->statusAction = '';
        $this->rejectReason = '';
        $this->statusError = null;
        $this->quotationId = null;
        $this->dispatch('modal-close', name: 'quotation-status');
    }

    public function applyStatus(
        AcceptQuotation $accept,
        RejectQuotation $reject,
    ): void {
        $quotation = Quotation::query()->findOrFail($this->requiredId());
        $this->statusError = null;

        try {
            if ($this->statusAction === 'accept') {
                $this->authorize('accept', $quotation);
                $accept($quotation);
                $this->closeStatusPanel();
                Flux::toast(variant: 'success', text: 'Quotation accepted.');
            } else {
                $this->authorize('reject', $quotation);
                $reject($quotation, $this->rejectReason);
                $this->closeStatusPanel();
                Flux::toast(variant: 'success', text: 'Quotation rejected.');
            }
        } catch (ValidationException $e) {
            $this->statusError = collect($e->errors())->flatten()->first();
        }
    }

    // ─── Revise ───────────────────────────────────────────────────────────

    public function revise(int $id, ReviseQuotation $revise): void
    {
        $quotation = Quotation::query()->findOrFail($id);
        $this->authorize('revise', $quotation);

        try {
            $newDraft = $revise($quotation);
            Flux::toast(variant: 'success', text: "Revision {$newDraft->revision} created as a draft.");
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());
        }
    }

    // ─── Convert to invoice ───────────────────────────────────────────────

    public function openConvertPanel(int $id): void
    {
        $quotation = Quotation::query()->findOrFail($id);
        $this->authorize('convert', $quotation);
        $this->quotationId = $quotation->id;
        $this->convertCustomerId = $quotation->customer_id;
        $this->convertCustomerName = $quotation->customer->name ?? '';
        $this->convertCustomerSearch = '';
        $this->convertError = null;
        $this->showConvertPanel = true;
        $this->dispatch('modal-show', name: 'quotation-convert');
    }

    public function closeConvertPanel(): void
    {
        $this->showConvertPanel = false;
        $this->convertCustomerId = null;
        $this->convertCustomerName = '';
        $this->convertCustomerSearch = '';
        $this->convertError = null;
        $this->quotationId = null;
        $this->dispatch('modal-close', name: 'quotation-convert');
    }

    public function selectConvertCustomer(int $customerId): void
    {
        $customer = Customer::query()
            ->whereKey($customerId)
            ->where('is_active', true)
            ->where('is_walk_in', false)
            ->firstOrFail();

        $this->convertCustomerId = $customer->id;
        $this->convertCustomerName = $customer->name;
        $this->convertCustomerSearch = '';
    }

    public function convertToInvoice(ConvertQuotationToInvoice $convert): void
    {
        $quotation = Quotation::query()->findOrFail($this->requiredId());
        $this->authorize('convert', $quotation);
        $this->convertError = null;

        try {
            $invoice = $convert($quotation, $this->convertCustomerId);
            $this->closeConvertPanel();
            Flux::toast(variant: 'success', text: "Invoice draft created from quotation {$quotation->number}. Invoice ID: {$invoice->id}.");
        } catch (ValidationException $e) {
            $this->convertError = collect($e->errors())->flatten()->first();
        }
    }

    // ─── Email ────────────────────────────────────────────────────────────

    public function openEmailPanel(int $id): void
    {
        $quotation = Quotation::query()->findOrFail($id);
        $this->authorize('print', $quotation);
        $this->quotationId = $quotation->id;
        $this->emailError = null;

        // Pre-fill recipient email.
        $this->emailTo = $quotation->customer->email
            ?? $quotation->prospect_email
            ?? '';
        $this->emailMessage = '';
        $this->emailTemplate = 'classic';
        $this->showEmailPanel = true;
        $this->dispatch('modal-show', name: 'quotation-email');
    }

    public function closeEmailPanel(): void
    {
        $this->showEmailPanel = false;
        $this->emailError = null;
        $this->emailTo = '';
        $this->emailMessage = '';
        $this->quotationId = null;
        $this->dispatch('modal-close', name: 'quotation-email');
    }

    public function sendEmail(QuotationDocumentData $documentData): void
    {
        $quotation = Quotation::query()
            ->with(['items.unit', 'location', 'customer'])
            ->findOrFail($this->requiredId());
        $this->authorize('print', $quotation);

        $validatedEmail = trim($this->emailTo);
        if ($validatedEmail === '' || ! filter_var($validatedEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addError('emailTo', 'A valid email address is required.');

            return;
        }

        $template = InvoiceTemplate::tryFrom($this->emailTemplate) ?? InvoiceTemplate::Classic;
        if ($template->isThermal()) {
            $this->emailError = 'Thermal template cannot be used for quotations.';

            return;
        }

        $this->emailError = null;

        $data = $documentData->build($quotation);
        $pdf = Pdf::loadView('quotations.templates.'.$template->value, $data);
        $pdf->setPaper(strtolower($template->defaultPaper()), 'portrait');
        $bytes = (string) $pdf->output();

        $filename = ($quotation->number ?? 'quotation-draft').'-'.$template->value.'.pdf';

        try {
            Mail::to($validatedEmail)->send(
                new QuotationMail($quotation, $validatedEmail, $this->emailMessage, $bytes, $filename)
            );

            // Record who sent it and when.
            $quotation->update([
                'sent_by' => $quotation->sent_by ?? auth()->id(),
                'sent_at' => $quotation->sent_at ?? now(),
            ]);

            $this->closeEmailPanel();
            Flux::toast(variant: 'success', text: "Quotation emailed to {$validatedEmail}.");
        } catch (\Throwable $e) {
            report($e);
            $this->emailError = 'Failed to send email: '.$e->getMessage().'. Please check your mail settings.';
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render(InvoiceCalculator $calculator, Settings $settings, StockService $stockService): View
    {
        $this->authorize('viewAny', Quotation::class);

        $sortBy = in_array($this->sortBy, ['number', 'quote_date', 'valid_until', 'total', 'status', 'created_at'], true)
            ? $this->sortBy : 'created_at';
        $sortDir = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        $today = now()->toDateString();

        $quotations = Quotation::query()
            ->with(['customer:id,name,code', 'location:id,name'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('number', 'ILIKE', $term)
                        ->orWhere('prospect_name', 'ILIKE', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', $term));
                });
            })
            ->when($this->statusFilter === 'expired', function (Builder $q) use ($today): void {
                $q->where('status', QuotationStatus::Sent)->whereDate('valid_until', '<', $today);
            })
            ->when($this->statusFilter !== 'all' && $this->statusFilter !== 'expired', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('quote_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('quote_date', '<=', $this->dateTo))
            ->when($this->customerFilter !== '', fn (Builder $q) => $q->where(function (Builder $q2): void {
                $term = '%'.$this->customerFilter.'%';
                $q2->where('prospect_name', 'ILIKE', $term)
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'ILIKE', $term));
            }))
            ->when($this->locationFilter !== null, fn (Builder $q) => $q->where('location_id', $this->locationFilter))
            ->orderBy($sortBy, $sortDir)
            ->paginate(20);

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Compute live totals for the form.
        $liveTotals = null;
        if (! empty($this->items)) {
            try {
                $calculatorLines = array_map(fn ($item): array => [
                    'quantity' => $item['quantity'] ?? '1',
                    'unit_price' => $item['unit_price'] ?? '0',
                    'discount_amount' => $item['discount_amount'] ?? '0',
                    'tax_type' => $item['tax_type'] ?? 'standard',
                ], $this->items);
                $result = $calculator->calculate($calculatorLines);
                $liveTotals = [
                    'subtotal' => $result['subtotal'],
                    'discount_total' => $result['discount_total'],
                    'tax_total' => $result['tax_total'],
                    'total' => $result['total'],
                ];
            } catch (\Throwable) {
                // Ignore invalid partial input while typing.
            }
        }

        // Stock hints for line items.
        $stockHints = [];
        if ($this->locationId !== null && ! empty($this->items)) {
            foreach ($this->items as $item) {
                $pid = (int) ($item['product_id'] ?? 0);
                if ($pid > 0 && ! isset($stockHints[$pid])) {
                    $stockHints[$pid] = StockLevel::query()
                        ->where('product_id', $pid)
                        ->where('location_id', $this->locationId)
                        ->sum('quantity');
                }
            }
        }

        // Customer search results.
        $customerResults = [];
        if (strlen($this->customerSearch) >= 2) {
            $term = '%'.$this->customerSearch.'%';
            $customerResults = Customer::query()
                ->where('is_active', true)
                ->where(function (Builder $q) use ($term): void {
                    $q->where('name', 'ILIKE', $term)->orWhere('code', 'ILIKE', $term);
                })
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'code'])
                ->toArray();
        }

        // Product search results.
        $productResults = [];
        if (strlen($this->productSearch) >= 2) {
            $term = '%'.$this->productSearch.'%';
            $productResults = Product::query()
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where(function (Builder $q) use ($term): void {
                    $q->where('name', 'ILIKE', $term)
                        ->orWhere('sku', 'ILIKE', $term)
                        ->orWhere('barcode', 'ILIKE', $term);
                })
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'sku'])
                ->toArray();
        }

        // Convert customer search.
        $convertCustomerResults = [];
        if (strlen($this->convertCustomerSearch) >= 2) {
            $term = '%'.$this->convertCustomerSearch.'%';
            $convertCustomerResults = Customer::query()
                ->where('is_active', true)
                ->where('is_walk_in', false)
                ->where(function (Builder $q) use ($term): void {
                    $q->where('name', 'ILIKE', $term)->orWhere('code', 'ILIKE', $term);
                })
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'code'])
                ->toArray();
        }

        // Selected quotation for details / status panels.
        $selectedQuotation = null;
        if (($this->showDetails || $this->showStatusPanel || $this->showSendConfirmation
            || $this->showDeleteConfirmation || $this->showConvertPanel || $this->showEmailPanel)
            && $this->quotationId !== null
        ) {
            $selectedQuotation = Quotation::query()
                ->with(['items.unit', 'items.product', 'customer', 'location', 'revisedFrom', 'revisions', 'convertedInvoice', 'creator', 'sender'])
                ->find($this->quotationId);
        }

        $templates = array_filter(InvoiceTemplate::cases(), fn ($t) => ! $t->isThermal());

        return view('livewire.quotations.index', compact(
            'quotations',
            'locations',
            'liveTotals',
            'stockHints',
            'customerResults',
            'productResults',
            'convertCustomerResults',
            'selectedQuotation',
            'templates',
        ));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function clearFormState(): void
    {
        $this->quotationId = null;
        $this->recipientMode = 'customer';
        $this->customerSearch = '';
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = '';
        $this->prospectName = '';
        $this->prospectPhone = '';
        $this->prospectEmail = '';
        $this->prospectAddress = '';
        $this->locationId = null;
        $this->quoteDate = now()->toDateString();
        $this->validUntil = '';
        $this->notes = '';
        $this->terms = '';
        $this->items = [];
        $this->productSearch = '';
        $this->resetValidation();
    }

    private function requiredId(): int
    {
        abort_if($this->quotationId === null, 404);

        return $this->quotationId;
    }

    /** @return array<string, mixed> */
    private function buildQuotationData(): array
    {
        return [
            'customer_id' => $this->recipientMode === 'customer' ? $this->selectedCustomerId : null,
            'prospect_name' => $this->recipientMode === 'prospect' ? $this->prospectName : null,
            'prospect_phone' => $this->recipientMode === 'prospect' ? $this->prospectPhone : null,
            'prospect_email' => $this->recipientMode === 'prospect' ? $this->prospectEmail : null,
            'prospect_address' => $this->recipientMode === 'prospect' ? $this->prospectAddress : null,
            'location_id' => $this->locationId,
            'quote_date' => $this->quoteDate,
            'valid_until' => $this->validUntil,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'terms' => $this->terms !== '' ? $this->terms : null,
            'items' => $this->items,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAvailableUnits(Product $product): array
    {
        $units = [[
            'id' => $product->base_unit_id,
            'name' => $product->baseUnit->name ?? '',
            'abbreviation' => $product->baseUnit->abbreviation ?? '',
        ]];

        foreach ($product->productUnits as $pu) {
            $units[] = [
                'id' => $pu->unit_id,
                'name' => $pu->unit->name ?? '',
                'abbreviation' => $pu->unit->abbreviation ?? '',
            ];
        }

        return $units;
    }
}
