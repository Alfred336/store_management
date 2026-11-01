<?php

namespace App\Livewire\Products;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\UpdateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Manage product catalog records and packaging conversions in one Livewire screen. */
#[Title('Products')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $activeFilter = 'all';

    public bool $lowStockOnly = false;

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    #[Locked]
    public ?int $productId = null;

    public string $sku = '';

    public string $barcode = '';

    public string $taxType = 'standard';

    public string $name = '';

    public ?string $categoryId = null;

    public ?string $baseUnitId = null;

    public string $reorderLevel = '0.000';

    public bool $trackBatches = false;

    public bool $isActive = true;

    public string $description = '';

    /** @var array<int, mixed> */
    public array $packagingUnits = [];

    /** Reset pagination when the product search term changes. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Reset pagination when the product status filter changes. */
    public function updatedActiveFilter(): void
    {
        $this->resetPage();
    }

    /** Reset pagination when toggling the low-stock filter. */
    public function updatedLowStockOnly(): void
    {
        $this->resetPage();
    }

    /** Toggle sorting for an approved catalog column. */
    public function sort(string $column): void
    {
        $this->authorize('viewAny', Product::class);

        if (! in_array($column, ['sku', 'name', 'total_qty', 'reorder_level'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    /** Prepare a create form or load a product and its packaging conversions for editing. */
    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;

        if ($id === null) {
            $this->authorize('create', Product::class);
            $this->resetForm();
        } else {
            $product = Product::query()->with('productUnits')->findOrFail($id);
            $this->authorize('update', $product);

            $this->productId = $product->id;
            $this->sku = $product->sku;
            $this->barcode = $product->barcode ?? '';
            $this->taxType = $product->tax_type;
            $this->name = $product->name;
            $this->categoryId = $product->category_id === null ? null : (string) $product->category_id;
            $this->baseUnitId = (string) $product->base_unit_id;
            $this->reorderLevel = $product->reorder_level;
            $this->trackBatches = $product->track_batches;
            $this->isActive = $product->is_active;
            $this->description = $product->description ?? '';
            $this->packagingUnits = $product->productUnits
                ->map(fn ($row): array => ['unit_id' => (string) $row->unit_id, 'factor' => $row->factor])
                ->values()
                ->all();
        }

        $this->showForm = true;
        $this->dispatch('modal-show', name: 'product-form');
    }

    /** Create a product and its packaging factors through a dedicated Action. */
    public function store(CreateProduct $createProduct): void
    {
        $this->authorize('create', Product::class);
        $createProduct($this->validatedData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Product created.');
    }

    /** Update a product through its dedicated, transaction-protected Action. */
    public function update(UpdateProduct $updateProduct): void
    {
        $product = Product::query()->findOrFail($this->requiredProductId());
        $this->authorize('update', $product);

        try {
            $updateProduct($product, $this->validatedData());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $componentField = match ($field) {
                    'base_unit_id' => 'baseUnitId',
                    'track_batches' => 'trackBatches',
                    default => $field,
                };

                foreach ($messages as $message) {
                    $this->addError($componentField, $message);
                }
            }

            return;
        }

        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Product updated.');
    }

    /** Load and authorize product details before showing them. */
    public function show(int $id): void
    {
        $product = Product::query()
            ->with(['category', 'baseUnit', 'productUnits.unit'])
            ->withSum('stockLevels as total_qty', 'quantity')
            ->findOrFail($id);
        $this->authorize('view', $product);
        $this->productId = $product->id;
        $this->showDetails = true;
        $this->dispatch('modal-show', name: 'product-details');
    }

    /** Open the protected confirmation dialog for soft deletion. */
    public function confirmDelete(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $this->authorize('delete', $product);
        $this->productId = $product->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'product-delete');
    }

    /** Soft-delete a product only when it has no movement history. */
    public function delete(DeleteProduct $deleteProduct): void
    {
        $product = Product::query()->findOrFail($this->requiredProductId());
        $this->authorize('delete', $product);

        if (! $deleteProduct($product)) {
            Flux::toast(variant: 'danger', text: 'This product has stock history. Deactivate it instead.');

            return;
        }

        $this->closeDialogs();
        Flux::toast(variant: 'success', text: 'Product archived.');
    }

    /** Add an empty packaging row for the form. */
    public function addPackagingUnit(): void
    {
        $this->authorize($this->productId ? 'update' : 'create', $this->productId ? Product::query()->findOrFail($this->productId) : Product::class);
        $this->packagingUnits[] = ['unit_id' => '', 'factor' => ''];
    }

    /** Remove a packaging row by its current form index. */
    public function removePackagingUnit(int $index): void
    {
        $this->authorize($this->productId ? 'update' : 'create', $this->productId ? Product::query()->findOrFail($this->productId) : Product::class);

        if (array_key_exists($index, $this->packagingUnits)) {
            unset($this->packagingUnits[$index]);
            $this->packagingUnits = array_values($this->packagingUnits);
        }
    }

    /** Close the form modal and clear user-editable state. */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('modal-close', name: 'product-form');
    }

    /** Reset form values before another create or edit interaction. */
    /** Reset editable product fields and validation errors. */
    public function resetForm(): void
    {
        $this->productId = null;
        $this->sku = '';
        $this->barcode = '';
        $this->taxType = 'standard';
        $this->name = '';
        $this->categoryId = null;
        $this->baseUnitId = null;
        $this->reorderLevel = '0.000';
        $this->trackBatches = false;
        $this->isActive = true;
        $this->description = '';
        $this->packagingUnits = [];
        $this->resetValidation();
    }

    /** Render the permission-checked product catalog with totals and low-stock filtering. */
    public function render(): View
    {
        $this->authorize('viewAny', Product::class);

        $sortBy = in_array($this->sortBy, ['sku', 'name', 'total_qty', 'reorder_level'], true) ? $this->sortBy : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';
        $activeFilter = in_array($this->activeFilter, ['all', 'active', 'inactive'], true) ? $this->activeFilter : 'all';

        $products = Product::query()
            ->with(['category', 'baseUnit'])
            ->withSum('stockLevels as total_qty', 'quantity')
            ->when($this->search !== '', fn (Builder $query) => $query->search($this->search))
            ->when($activeFilter !== 'all', fn (Builder $query) => $query->where('is_active', $activeFilter === 'active'))
            ->when($this->lowStockOnly, fn (Builder $query) => $query->lowStock())
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);

        $lowStockIds = Product::query()
            ->whereKey($products->getCollection()->modelKeys())
            ->lowStock()
            ->pluck('id');

        $selected = $this->showDetails && $this->productId !== null
            ? Product::query()
                ->with(['category', 'baseUnit', 'productUnits.unit'])
                ->withSum('stockLevels as total_qty', 'quantity')
                ->findOrFail($this->productId)
            : null;

        if ($selected) {
            $this->authorize('view', $selected);
        }

        $canManageProducts = auth()->user()?->can('products.create')
            || auth()->user()?->can('products.update');
        $categories = $canManageProducts ? Category::query()->orderBy('name')->get() : collect();
        $units = $canManageProducts ? Unit::query()->orderBy('name')->get() : collect();
        $editingProduct = $this->showForm && $this->productId !== null
            ? Product::query()->with('baseUnit')->findOrFail($this->productId)
            : null;

        if ($editingProduct) {
            $this->authorize('update', $editingProduct);
        }

        $hasMovements = $editingProduct?->stockMovements()->exists() ?? false;

        return view('livewire.products.index', compact('products', 'lowStockIds', 'selected', 'categories', 'units', 'editingProduct', 'hasMovements'));
    }

    /**
     * Validate component form state and convert it into the Action payload.
     *
     * @return array<string, mixed>
     */
    private function validatedData(): array
    {
        $this->sku = strtoupper(trim($this->sku));
        $packagingRows = [];

        foreach ($this->packagingUnits as $row) {
            if (! is_array($row)) {
                $packagingRows[] = $row;

                continue;
            }

            if (($row['unit_id'] ?? '') === '' && ($row['factor'] ?? '') === '') {
                continue;
            }

            $packagingRows[] = $row;
        }

        $this->packagingUnits = $packagingRows;

        $data = $this->validate([
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($this->productId)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($this->productId)],
            'taxType' => ['required', Rule::in(['standard', 'exempt', 'zero_rated'])],
            'name' => ['required', 'string', 'max:255'],
            'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'baseUnitId' => ['required', 'integer', 'exists:units,id'],
            'reorderLevel' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'trackBatches' => ['required', 'boolean'],
            'isActive' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'packagingUnits' => ['array'],
            'packagingUnits.*.unit_id' => ['required', 'integer', 'exists:units,id', 'distinct:strict', Rule::notIn([(int) $this->baseUnitId])],
            'packagingUnits.*.factor' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
        ]);

        return [
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?: null,
            'tax_type' => $data['taxType'],
            'name' => $data['name'],
            'category_id' => $data['categoryId'] ?: null,
            'base_unit_id' => $data['baseUnitId'],
            'reorder_level' => $data['reorderLevel'],
            'track_batches' => $data['trackBatches'],
            'is_active' => $data['isActive'],
            'description' => $data['description'] ?: null,
            'packaging_units' => $this->validatedPackagingRows($data['packagingUnits'] ?? []),
        ];
    }

    /** @param array<int, mixed> $rows
     * @return array<int, array{unit_id: string, factor: string}>
     */
    private function validatedPackagingRows(array $rows): array
    {
        $validatedRows = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['unit_id'], $row['factor'])) {
                continue;
            }

            $validatedRows[] = [
                'unit_id' => (string) $row['unit_id'],
                'factor' => (string) $row['factor'],
            ];
        }

        return $validatedRows;
    }

    private function closeDialogs(): void
    {
        $this->showForm = false;
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;
        $this->productId = null;
        $this->dispatch('modal-close', name: 'product-delete');
        $this->dispatch('modal-close', name: 'product-details');
        $this->resetForm();
    }

    private function requiredProductId(): int
    {
        abort_if($this->productId === null, 404);

        return $this->productId;
    }
}
