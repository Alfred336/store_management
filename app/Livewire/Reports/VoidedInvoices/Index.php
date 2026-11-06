<?php

namespace App\Livewire\Reports\VoidedInvoices;

use App\Exports\Reports\VoidedInvoicesExport;
use App\Models\Location;
use App\Services\Reports\VoidedInvoicesService;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Voided Invoices Audit')]
class Index extends Component
{
    use WithPagination;

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

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function exportXlsx(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new VoidedInvoicesExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'voided-invoices.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new VoidedInvoicesExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'voided-invoices.csv',
            Excel::CSV
        );
    }

    public function render(VoidedInvoicesService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $tz = 'Africa/Dar_es_Salaam';
        $from = Carbon::parse($this->dateFrom, $tz)->startOfDay();
        $to = Carbon::parse($this->dateTo, $tz)->endOfDay();

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $invoices = $service->paginate($from, $to, $locationId);

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();

        return view('livewire.reports.voided-invoices.index', [
            'invoices' => $invoices,
            'locations' => $locations,
        ]);
    }
}
