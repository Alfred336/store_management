<?php

namespace App\Livewire\Reports\Payments;

use App\Exports\Reports\PaymentsByMethodExport;
use App\Models\Location;
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

        return \Maatwebsite\Excel\Facades\Excel::download(
            new PaymentsByMethodExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'payments-by-method.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new PaymentsByMethodExport($this->dateFrom, $this->dateTo, $this->locationFilter !== '' ? (int) $this->locationFilter : null),
            'payments-by-method.csv',
            Excel::CSV
        );
    }

    public function render(PaymentsByMethodService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $locationId = $this->locationFilter !== '' ? (int) $this->locationFilter : null;
        $data = $service->get($from, $to, $locationId);

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();

        return view('livewire.reports.payments.index', [
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'locations' => $locations,
        ]);
    }
}
