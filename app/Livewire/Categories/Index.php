<?php

namespace App\Livewire\Categories;

use App\Livewire\Actions\Categories\CreateCategory;
use App\Livewire\Actions\Categories\DeleteCategory;
use App\Livewire\Actions\Categories\UpdateCategory;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Manage product categories in a single policy-protected Livewire component. */
#[Title('Categories')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    #[Locked]
    public ?int $recordId = null;

    public string $name = '';

    /** Reset pagination when the category search term changes. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Toggle sorting for an approved table column. */
    public function sort(string $column): void
    {
        $this->authorize('viewAny', Category::class);

        if (! in_array($column, ['name', 'products_count'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    /** Prepare a create form or load a category into the edit form. */
    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;

        if ($id === null) {
            $this->authorize('create', Category::class);
            $this->resetForm();
        } else {
            $category = Category::query()->findOrFail($id);
            $this->authorize('update', $category);
            $this->recordId = $category->id;
            $this->name = $category->name;
        }

        $this->showForm = true;
        $this->dispatch('modal-show', name: 'category-form');
    }

    /** Create a category through its dedicated Action. */
    public function store(CreateCategory $createCategory): void
    {
        $this->authorize('create', Category::class);
        $createCategory($this->validate($this->rules()));
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Category created.');
    }

    /** Update a category through its dedicated Action. */
    public function update(UpdateCategory $updateCategory): void
    {
        $category = Category::query()->findOrFail($this->requiredRecordId());
        $this->authorize('update', $category);
        $updateCategory($category, $this->validate($this->rules()));
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Category updated.');
    }

    /** Load and authorize category details before showing them. */
    public function show(int $id): void
    {
        $category = Category::query()->withCount('products')->findOrFail($id);
        $this->authorize('view', $category);
        $this->recordId = $category->id;
        $this->showDetails = true;
        $this->dispatch('modal-show', name: 'category-details');
    }

    /** Open the protected confirmation dialog for a category. */
    public function confirmDelete(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        $this->authorize('delete', $category);
        $this->recordId = $category->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'category-delete');
    }

    /** Delete the category only when it has no products. */
    public function delete(DeleteCategory $deleteCategory): void
    {
        $category = Category::query()->findOrFail($this->requiredRecordId());
        $this->authorize('delete', $category);

        if (! $deleteCategory($category)) {
            Flux::toast(variant: 'danger', text: 'This category has products and cannot be deleted.');

            return;
        }

        $this->closeDialogs();
        Flux::toast(variant: 'success', text: 'Category deleted.');
    }

    /** Close the form modal and clear user-editable state. */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('modal-close', name: 'category-form');
    }

    /** Reset form values before another create or edit interaction. */
    /** Reset the editable category fields and validation errors. */
    public function resetForm(): void
    {
        $this->recordId = null;
        $this->name = '';
        $this->resetValidation();
    }

    /** Render the permission-checked category list and selected details. */
    public function render(): View
    {
        $this->authorize('viewAny', Category::class);

        $sortBy = in_array($this->sortBy, ['name', 'products_count'], true) ? $this->sortBy : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';

        $categories = Category::query()
            ->withCount('products')
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'ILIKE', '%'.$this->search.'%'))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);

        $selected = $this->showDetails && $this->recordId !== null
            ? Category::query()->withCount('products')->findOrFail($this->recordId)
            : null;

        if ($selected) {
            $this->authorize('view', $selected);
        }

        return view('livewire.categories.index', compact('categories', 'selected'));
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($this->recordId)]];
    }

    private function closeDialogs(): void
    {
        $this->showForm = false;
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;
        $this->recordId = null;
        $this->dispatch('modal-close', name: 'category-delete');
        $this->dispatch('modal-close', name: 'category-details');
        $this->resetForm();
    }

    private function requiredRecordId(): int
    {
        abort_if($this->recordId === null, 404);

        return $this->recordId;
    }
}
