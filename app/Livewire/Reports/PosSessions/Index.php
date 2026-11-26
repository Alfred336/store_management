<?php

namespace App\Livewire\Reports\PosSessions;

use App\Exports\Reports\PosSessionsExport;
use App\Services\Access\LocationAccess;
use App\Services\Reports\PosSessionsService;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('POS Sessions')]
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
            new PosSessionsExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'pos-sessions.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $allowedIds = app(LocationAccess::class)->allowedLocationIds(auth()->user());

        return \Maatwebsite\Excel\Facades\Excel::download(
            new PosSessionsExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'pos-sessions.csv',
            Excel::CSV
        );
    }

    public function render(PosSessionsService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $access = app(LocationAccess::class);
        $user = auth()->user();
        $allowedIds = $access->allowedLocationIds($user);

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $sessions = $service->paginate($from, $to, $locationId, 50, $allowedIds);

        $locations = $access->allowedLocations($user);

        return view('livewire.reports.pos-sessions.index', [
            'sessions' => $sessions,
            'locations' => $locations,
        ]);
    }
}
