<?php

namespace App\Livewire\Dashboard;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Display live inventory KPIs without placeholder sales metrics. */
#[Title('Dashboard')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Product::class);

        return view('livewire.dashboard.index', [
            'activeProducts' => Product::query()->where('is_active', true)->count(),
            'lowStockProducts' => Product::query()->where('is_active', true)->lowStock()->count(),
            'outOfStockProducts' => Product::query()->where('is_active', true)->outOfStock()->count(),
            'activeLocations' => Location::query()->where('is_active', true)->count(),
            'movementsToday' => StockMovement::query()->whereDate('moved_at', today())->count(),
        ]);
    }
}
