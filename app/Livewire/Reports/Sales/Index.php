<?php

namespace App\Livewire\Reports\Sales;

use App\Exports\Reports\SalesSummaryExport;
use App\Models\Location;
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

        $tz = 'Africa/Dar_es_Salaam';
        $this->dateFrom = now($tz)->startOfMonth()->toDateString();
        $this->dateTo = now($tz)->endOfMonth()->toDateString();
    }

    public function exportXlsx(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new SalesSummaryExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'sales-summary.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new SalesSummaryExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'sales-summary.csv',
            Excel::CSV
        );
    }

    public function render(SalesSummaryService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $tz = 'Africa/Dar_es_Salaam';
        $from = Carbon::parse($this->dateFrom, $tz)->startOfDay();
        $to = Carbon::parse($this->dateTo, $tz)->endOfDay();

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $data = $service->get($from, $to, $locationId);

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();

        return view('livewire.reports.sales.index', [
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'groupBy' => $data['group_by'],
            'locations' => $locations,
        ]);
    }
}
