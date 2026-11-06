<?php

namespace App\Livewire\Reports\Inventory;

use App\Exports\Reports\InventoryReportExport;
use App\Models\Category;
use App\Models\Location;
use App\Services\Reports\InventoryReportService;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Inventory Report')]
class Index extends Component
{
    use WithPagination;

    public string $locationFilter = '';

    public string $categoryFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function exportXlsx(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new InventoryReportExport($this->locationFilter !== '' ? (int) $this->locationFilter : null, $this->categoryFilter !== '' ? (int) $this->categoryFilter : null),
            'inventory-report.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new InventoryReportExport($this->locationFilter !== '' ? (int) $this->locationFilter : null, $this->categoryFilter !== '' ? (int) $this->categoryFilter : null),
            'inventory-report.csv',
            Excel::CSV
        );
    }

    public function render(InventoryReportService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $categoryId = $this->categoryFilter !== '' ? (int) $this->categoryFilter : null;

        $levels = $service->paginate($locationId, $categoryId);
        $totals = $service->totals($locationId, $categoryId);

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();
        $categories = Category::query()->orderBy('name')->get();

        return view('livewire.reports.inventory.index', [
            'levels' => $levels,
            'totals' => $totals,
            'locations' => $locations,
            'categories' => $categories,
        ]);
    }
}
