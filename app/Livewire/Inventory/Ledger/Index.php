<?php

namespace App\Livewire\Inventory\Ledger;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only stock movement history with filters and pagination. */
#[Title('Stock Ledger')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $productFilter = '';

    public string $locationFilter = '';

    public string $typeFilter = '';

    public string $userFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('inventory.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function updatedProductFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
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

    public function render(): View
    {
        abort_unless(auth()->user()?->can('inventory.view'), 403);

        $movements = StockMovement::query()
            ->with(['product.baseUnit', 'location', 'batch', 'user'])
            ->when($this->search !== '', function (Builder $q): void {
                $pattern = '%'.$this->search.'%';
                $q->whereHas('product', fn (Builder $p) => $p
                    ->where('name', 'ILIKE', $pattern)
                    ->orWhere('sku', 'ILIKE', $pattern)
                );
            })
            ->when($this->productFilter !== '', fn (Builder $q) => $q->where('product_id', (int) $this->productFilter))
            ->when($this->locationFilter !== '', fn (Builder $q) => $q->where('location_id', (int) $this->locationFilter))
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('type', $this->typeFilter))
            ->when($this->userFilter !== '', fn (Builder $q) => $q->where('user_id', (int) $this->userFilter))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('moved_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('moved_at', '<=', $this->dateTo))
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate(50);

        $products = Product::query()->whereNull('deleted_at')->orderBy('name')->get();
        $locations = Location::query()->orderBy('name')->get();
        $users = User::query()->orderBy('name')->get();

        $movementTypes = [
            'receipt' => 'Receipt',
            'sale' => 'Sale',
            'issue' => 'Issue',
            'damage' => 'Damage',
            'adjustment' => 'Adjustment',
            'transfer_in' => 'Transfer In',
            'transfer_out' => 'Transfer Out',
            'sale_return' => 'Sale Return',
        ];

        return view('livewire.inventory.ledger.index', compact(
            'movements', 'products', 'locations', 'users', 'movementTypes'
        ));
    }
}
