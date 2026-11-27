<?php

namespace App\Livewire\Reports\Payments;

use App\Exports\Reports\PaymentsByMethodExport;
use App\Services\Access\LocationAccess;
use App\Services\Reports\PaymentsByMethodService;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Payments by Method')]
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
            new PaymentsByMethodExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'payments-by-method.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $allowedIds = app(LocationAccess::class)->allowedLocationIds(auth()->user());

        return \Maatwebsite\Excel\Facades\Excel::download(
            new PaymentsByMethodExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null, $allowedIds),
            'payments-by-method.csv',
            Excel::CSV
        );
    }

    public function render(PaymentsByMethodService $service): View
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

        return view('livewire.reports.payments.index', [
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'locations' => $locations,
        ]);
    }
}
