<?php

namespace App\Livewire\Customers;

use App\Livewire\Actions\Customers\CreateCustomer;
use App\Livewire\Actions\Customers\DeleteCustomer;
use App\Livewire\Actions\Customers\GetCustomerStatement;
use App\Livewire\Actions\Customers\ImportCustomers;
use App\Livewire\Actions\Customers\UpdateCustomer;
use App\Models\Customer;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/** Manage customers and view their calculated account statements. */
#[Title('Customers')]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $typeFilter = 'all';

    public string $statusFilter = 'active';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    #[Locked]
    public ?int $customerId = null;

    public string $name = '';

    public string $type = 'individual';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $tin = '';

    public string $vrn = '';

    public string $notes = '';

    public string $creditLimit = '0.00';

    public string $paymentTermsDays = '0';

    public bool $isActive = true;

    public ?TemporaryUploadedFile $importFile = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    public function updatedSearch(): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->resetPage();
    }

    /** Toggle sorting for an approved customer-list column. */
    public function sort(string $column): void
    {
        $this->authorize('viewAny', Customer::class);

        if (! in_array($column, ['code', 'name', 'type', 'created_at'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    /** Open a create form or load an editable customer. */
    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;

        if ($id === null) {
            $this->authorize('create', Customer::class);
            $this->clearFormState();
        } else {
            $customer = Customer::query()->findOrFail($id);
            $this->authorize('update', $customer);
            $this->customerId = $customer->id;
            $this->name = $customer->name;
            $this->type = $customer->type;
            $this->phone = $customer->phone ?? '';
            $this->email = $customer->email ?? '';
            $this->address = $customer->address ?? '';
            $this->tin = $customer->tin ?? '';
            $this->vrn = $customer->vrn ?? '';
            $this->notes = $customer->notes ?? '';
            $this->creditLimit = $customer->credit_limit;
            $this->paymentTermsDays = (string) $customer->payment_terms_days;
            $this->isActive = $customer->is_active;
        }

        $this->showForm = true;
        $this->dispatch('modal-show', name: 'customer-form');
    }

    public function store(CreateCustomer $createCustomer): void
    {
        $this->authorize('create', Customer::class);
        $createCustomer($this->validatedData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Customer created.');
    }

    public function update(UpdateCustomer $updateCustomer): void
    {
        $customer = Customer::query()->findOrFail($this->requiredCustomerId());
        $this->authorize('update', $customer);
        $updateCustomer($customer, $this->validatedData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Customer updated.');
    }

    public function show(int $id): void
    {
        $customer = Customer::query()->findOrFail($id);
        $this->authorize('view', $customer);
        $this->customerId = $customer->id;
        $this->showDetails = true;
        $this->showForm = false;
        $this->showDeleteConfirmation = false;
        $this->dispatch('modal-show', name: 'customer-details');
    }

    public function confirmDelete(int $id): void
    {
        $customer = Customer::query()->findOrFail($id);
        $this->authorize('delete', $customer);
        $this->customerId = $customer->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'customer-delete');
    }

    public function delete(DeleteCustomer $deleteCustomer): void
    {
        $customer = Customer::query()->findOrFail($this->requiredCustomerId());
        $this->authorize('delete', $customer);
        $deleteCustomer($customer);
        $this->closeDialogs();
        Flux::toast(variant: 'success', text: 'Customer deleted.');
    }

    public function importCustomers(ImportCustomers $importCustomers): void
    {
        $this->authorize('create', Customer::class);
        $validated = $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:10240'],
        ]);

        $count = $importCustomers($validated['importFile']);
        $this->importFile = null;
        $this->resetPage();
        Flux::toast(variant: 'success', text: "Imported {$count} customers.");
    }

    public function closeForm(): void
    {
        if ($this->customerId !== null) {
            $this->authorize('update', Customer::query()->findOrFail($this->customerId));
        } else {
            $this->authorize('create', Customer::class);
        }

        $this->showForm = false;
        $this->clearFormState();
        $this->dispatch('modal-close', name: 'customer-form');
    }

    public function closeDetails(): void
    {
        $customer = Customer::query()->findOrFail($this->requiredCustomerId());
        $this->authorize('view', $customer);
        $this->showDetails = false;
        $this->customerId = null;
        $this->dispatch('modal-close', name: 'customer-details');
    }

    public function closeDeleteConfirmation(): void
    {
        $customer = Customer::query()->findOrFail($this->requiredCustomerId());
        $this->authorize('delete', $customer);
        $this->showDeleteConfirmation = false;
        $this->customerId = null;
        $this->dispatch('modal-close', name: 'customer-delete');
    }

    private function clearFormState(): void
    {
        $this->customerId = null;
        $this->name = '';
        $this->type = 'individual';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->tin = '';
        $this->vrn = '';
        $this->notes = '';
        $this->creditLimit = '0.00';
        $this->paymentTermsDays = '0';
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render(GetCustomerStatement $getCustomerStatement): View
    {
        $this->authorize('viewAny', Customer::class);

        $sortBy = in_array($this->sortBy, ['code', 'name', 'type', 'created_at'], true) ? $this->sortBy : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';
        $typeFilter = in_array($this->typeFilter, ['all', 'individual', 'business'], true) ? $this->typeFilter : 'all';
        $statusFilter = in_array($this->statusFilter, ['all', 'active', 'inactive'], true) ? $this->statusFilter : 'active';

        $customers = Customer::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where('name', 'ILIKE', $term)
                    ->orWhere('code', 'ILIKE', $term)
                    ->orWhere('phone', 'ILIKE', $term)
                    ->orWhere('email', 'ILIKE', $term);
            }))
            ->when($typeFilter !== 'all', fn (Builder $query) => $query->where('type', $typeFilter))
            ->when($statusFilter !== 'all', fn (Builder $query) => $query->where('is_active', $statusFilter === 'active'))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);

        $selectedCustomer = $this->showDetails && $this->customerId !== null
            ? Customer::query()->findOrFail($this->customerId)
            : null;
        $statement = null;

        if ($selectedCustomer !== null) {
            $this->authorize('view', $selectedCustomer);
            $statement = $getCustomerStatement($selectedCustomer);
        }

        return view('livewire.customers.index', compact('customers', 'selectedCustomer', 'statement'));
    }

    /** @return array<string, mixed> */
    private function validatedData(): array
    {
        $this->name = trim($this->name);
        $this->email = strtolower(trim($this->email));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['individual', 'business'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:50'],
            'vrn' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'creditLimit' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'paymentTermsDays' => ['required', 'integer', 'between:0,365'],
            'isActive' => ['required', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'address' => $data['address'] ?: null,
            'tin' => $data['tin'] ?: null,
            'vrn' => $data['vrn'] ?: null,
            'notes' => $data['notes'] ?: null,
            'credit_limit' => $data['creditLimit'],
            'payment_terms_days' => (int) $data['paymentTermsDays'],
            'is_active' => (bool) $data['isActive'],
        ];
    }

    private function closeDialogs(): void
    {
        $this->showForm = false;
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;
        $this->dispatch('modal-close', name: 'customer-delete');
        $this->dispatch('modal-close', name: 'customer-details');
        $this->clearFormState();
    }

    private function requiredCustomerId(): int
    {
        abort_if($this->customerId === null, 404);

        return $this->customerId;
    }
}
