<?php

namespace App\Livewire\Locations;

use App\Livewire\Actions\Locations\CreateLocation;
use App\Livewire\Actions\Locations\DeleteLocation;
use App\Livewire\Actions\Locations\UpdateLocation;
use App\Models\Location;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Manage inventory locations using one policy-protected Livewire screen. */
#[Title('Locations')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    #[Locked]
    public ?int $recordId = null;

    public string $name = '';

    public string $type = 'store';

    public bool $isActive = true;

    /** Reset pagination when the location search term changes. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Reset pagination when the location status filter changes. */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /** Toggle sorting for an approved table column. */
    public function sort(string $column): void
    {
        $this->authorize('viewAny', Location::class);

        if (! in_array($column, ['name', 'type', 'is_active', 'stock_levels_count', 'stock_movements_count'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    /** Prepare a create form or load a location into the edit form. */
    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;

        if ($id === null) {
            $this->authorize('create', Location::class);
            $this->resetForm();
        } else {
            $location = Location::query()->findOrFail($id);
            $this->authorize('update', $location);
            $this->recordId = $location->id;
            $this->name = $location->name;
            $this->type = $location->type;
            $this->isActive = $location->is_active;
        }

        $this->showForm = true;
        $this->dispatch('modal-show', name: 'location-form');
    }

    /** Create a location through its dedicated Action. */
    public function store(CreateLocation $createLocation): void
    {
        $this->authorize('create', Location::class);
        $createLocation($this->validatedData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Location created.');
    }

    /** Update a location through its dedicated Action. */
    public function update(UpdateLocation $updateLocation): void
    {
        $location = Location::query()->findOrFail($this->requiredRecordId());
        $this->authorize('update', $location);
        $updateLocation($location, $this->validatedData());
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'Location updated.');
    }

    /** Load and authorize location details before showing them. */
    public function show(int $id): void
    {
        $location = Location::query()->withCount(['stockLevels', 'stockMovements'])->findOrFail($id);
        $this->authorize('view', $location);
        $this->recordId = $location->id;
        $this->showDetails = true;
        $this->dispatch('modal-show', name: 'location-details');
    }

    /** Open the protected confirmation dialog for a location. */
    public function confirmDelete(int $id): void
    {
        $location = Location::query()->findOrFail($id);
        $this->authorize('delete', $location);
        $this->recordId = $location->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'location-delete');
    }

    /** Delete a location only when it has no stock history. */
    public function delete(DeleteLocation $deleteLocation): void
    {
        $location = Location::query()->findOrFail($this->requiredRecordId());
        $this->authorize('delete', $location);

        if (! $deleteLocation($location)) {
            Flux::toast(variant: 'danger', text: 'This location has stock history. Deactivate it instead.');

            return;
        }

        $this->closeDialogs();
        Flux::toast(variant: 'success', text: 'Location deleted.');
    }

    /** Close the form modal and clear user-editable state. */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('modal-close', name: 'location-form');
    }

    /** Reset form values before another create or edit interaction. */
    /** Reset the editable location fields and validation errors. */
    public function resetForm(): void
    {
        $this->recordId = null;
        $this->name = '';
        $this->type = 'store';
        $this->isActive = true;
        $this->resetValidation();
    }

    /** Render the permission-checked location list and selected details. */
    public function render(): View
    {
        $this->authorize('viewAny', Location::class);

        $sortBy = in_array($this->sortBy, ['name', 'type', 'is_active', 'stock_levels_count', 'stock_movements_count'], true) ? $this->sortBy : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';

        $locations = Location::query()
            ->withCount(['stockLevels', 'stockMovements'])
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'ILIKE', '%'.$this->search.'%'))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);

        $selected = $this->showDetails && $this->recordId !== null
            ? Location::query()->withCount(['stockLevels', 'stockMovements'])->findOrFail($this->recordId)
            : null;

        if ($selected) {
            $this->authorize('view', $selected);
        }

        return view('livewire.locations.index', compact('locations', 'selected'));
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('locations', 'name')->ignore($this->recordId)],
            'type' => ['required', Rule::in(['store', 'warehouse', 'branch'])],
            'isActive' => ['required', 'boolean'],
        ];
    }

    /**
     * Validate and map component state to the location model's field names.
     *
     * @return array{name: string, type: string, is_active: bool}
     */
    private function validatedData(): array
    {
        $data = $this->validate($this->rules());

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'is_active' => (bool) $data['isActive'],
        ];
    }

    private function closeDialogs(): void
    {
        $this->showForm = false;
        $this->showDetails = false;
        $this->showDeleteConfirmation = false;
        $this->recordId = null;
        $this->dispatch('modal-close', name: 'location-delete');
        $this->dispatch('modal-close', name: 'location-details');
        $this->resetForm();
    }

    private function requiredRecordId(): int
    {
        abort_if($this->recordId === null, 404);

        return $this->recordId;
    }
}
