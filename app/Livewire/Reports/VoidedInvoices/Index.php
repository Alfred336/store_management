<?php

namespace App\Livewire\Reports\VoidedInvoices;

use App\Exports\Reports\VoidedInvoicesExport;
use App\Services\Access\LocationAccess;
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

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
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

        $allowedIds = app(LocationAccess::class)->allowedLocationIds(auth()->user());

        return \Maatwebsite\Excel\Facades\Excel::download(
            new VoidedInvoicesExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'voided-invoices.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $allowedIds = app(LocationAccess::class)->allowedLocationIds(auth()->user());

        return \Maatwebsite\Excel\Facades\Excel::download(
            new VoidedInvoicesExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'voided-invoices.csv',
            Excel::CSV
        );
    }

    public function render(VoidedInvoicesService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $access = app(LocationAccess::class);
        $user = auth()->user();
        $allowedIds = $access->allowedLocationIds($user);

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $invoices = $service->paginate($from, $to, $locationId, 50, $allowedIds);

        $locations = $access->allowedLocations($user);

        return view('livewire.reports.voided-invoices.index', [
            'invoices' => $invoices,
            'locations' => $locations,
        ]);
    }
}
