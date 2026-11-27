<?php

namespace App\Livewire\Reports\Sales;

use App\Exports\Reports\SalesSummaryExport;
use App\Services\Access\LocationAccess;
use App\Services\Reports\SalesSummaryService;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Sales Summary')]
class Index extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public string $locationFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
    }

    public function exportXlsx(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $allowedIds = app(LocationAccess::class)->allowedLocationIds(auth()->user());

        return \Maatwebsite\Excel\Facades\Excel::download(
            new SalesSummaryExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'sales-summary.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $allowedIds = app(LocationAccess::class)->allowedLocationIds(auth()->user());

        return \Maatwebsite\Excel\Facades\Excel::download(
            new SalesSummaryExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'sales-summary.csv',
            Excel::CSV
        );
    }

    public function render(SalesSummaryService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $access = app(LocationAccess::class);
        $user = auth()->user();
        $allowedIds = $access->allowedLocationIds($user);

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $data = $service->get($from, $to, $locationId, $allowedIds);

        $locations = $access->allowedLocations($user);

        return view('livewire.reports.sales.index', [
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'groupBy' => $data['group_by'],
            'locations' => $locations,
        ]);
    }
}
