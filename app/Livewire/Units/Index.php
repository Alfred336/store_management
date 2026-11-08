<?php

namespace App\Livewire\Units;

use App\Livewire\Actions\Units\CreateUnit;
use App\Livewire\Actions\Units\DeleteUnit;
use App\Livewire\Actions\Units\UpdateUnit;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Manage units using a single searchable, policy-protected Livewire screen. */
#[Title('Units')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    #[Locked]
    public ?int $recordId = null;

    public string $name = '';

    public string $abbreviation = '';

    /** Reset pagination when the unit search term changes. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    /** Toggle sorting for an approved table column. */
    public function sort(string $column): void
    {
        $this->authorize('viewAny', Unit::class);

        if (! in_array($column, ['name', 'abbreviation'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    /** Prepare a create form or load a record into the edit form. */
    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;

        if ($id === null) {
            $this->authorize('create', Unit::class);
            $this->resetForm();
        } else {
            $unit = Unit::query()->findOrFail($id);
            $this->authorize('update', $unit);
            $this->recordId = $unit->id;
            $this->name = $unit->name;
            $this->abbreviation = $unit->abbreviation;
        }

        $this->showForm = true;
        $this->dispatch('modal-show', name: 'unit-form');
    }

    /** Create a unit through its dedicated Action. */
    public function store(CreateUnit $createUnit): void
    {
        $this->authorize('create', Unit::class);
        $data = $this->validate($this->rules());
        $createUnit($data);
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Unit created.');
    }

    /** Update a unit through its dedicated Action. */
    public function update(UpdateUnit $updateUnit): void
    {
        $unit = Unit::query()->findOrFail($this->requiredRecordId());
        $this->authorize('update', $unit);
        $updateUnit($unit, $this->validate($this->rules()));
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Unit updated.');
    }

    /** Load and authorize unit details before showing them. */
    public function show(int $id): void
    {
        $unit = Unit::query()->withCount('products')->findOrFail($id);
        $this->authorize('view', $unit);
        $this->recordId = $unit->id;
        $this->showDetails = true;
        $this->dispatch('modal-show', name: 'unit-details');
    }

    /** Open the protected confirmation dialog for a unit. */
    public function confirmDelete(int $id): void
    {
        $unit = Unit::query()->findOrFail($id);
        $this->authorize('delete', $unit);
        $this->recordId = $unit->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'unit-delete');
    }

    /** Delete the selected unit if no product depends on it. */
    public function delete(DeleteUnit $deleteUnit): void
    {
        $unit = Unit::query()->findOrFail($this->requiredRecordId());
        $this->authorize('delete', $unit);

        if (! $deleteUnit($unit)) {
            Flux::toast(variant: 'danger', text: 'This unit is used by a product and cannot be deleted.');

            return;
        }

        $this->closeDialogs();
        Flux::toast(variant: 'success', text: 'Unit deleted.');
    }

    /** Close the form modal and clear user-editable state. */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('modal-close', name: 'unit-form');
    }

    /** Reset form values before another create or edit interaction. */
    /** Reset the editable unit fields and validation errors. */
    public function resetForm(): void
    {
        $this->recordId = null;
        $this->name = '';
        $this->abbreviation = '';
        $this->resetValidation();
    }

    /** Render the permission-checked unit list and selected detail record. */
    public function render(): View
    {
        $this->authorize('viewAny', Unit::class);

        $sortBy = in_array($this->sortBy, ['name', 'abbreviation', 'products_count'], true) ? $this->sortBy : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';

        $units = Unit::query()
            ->withCount('products')
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('name', 'ILIKE', '%'.$this->search.'%')
                    ->orWhere('abbreviation', 'ILIKE', '%'.$this->search.'%');
            }))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);

        $selected = $this->showDetails && $this->recordId !== null
            ? Unit::query()->withCount('products')->findOrFail($this->recordId)
            : null;

        if ($selected) {
            $this->authorize('view', $selected);
        }

        return view('livewire.units.index', compact('units', 'selected'));
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')->ignore($this->recordId)],
            'abbreviation' => ['required', 'string', 'max:10'],
        ];
    }

    private function closeDialogs(): void
    {
        $this->showForm = false;
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;
        $this->recordId = null;
        $this->dispatch('modal-close', name: 'unit-delete');
        $this->dispatch('modal-close', name: 'unit-details');
        $this->resetForm();
    }

    private function requiredRecordId(): int
    {
        abort_if($this->recordId === null, 404);

        return $this->recordId;
    }
}
