<?php

namespace App\Livewire\Reports\PosSessions;

use App\Exports\Reports\PosSessionsExport;
use App\Models\Location;
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
            new PosSessionsExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'pos-sessions.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new PosSessionsExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'pos-sessions.csv',
            Excel::CSV
        );
    }

    public function render(PosSessionsService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $tz = 'Africa/Dar_es_Salaam';
        $from = Carbon::parse($this->dateFrom, $tz)->startOfDay();
        $to = Carbon::parse($this->dateTo, $tz)->endOfDay();

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $sessions = $service->paginate($from, $to, $locationId);

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();

        return view('livewire.reports.pos-sessions.index', [
            'sessions' => $sessions,
            'locations' => $locations,
        ]);
    }
}
