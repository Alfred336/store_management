<?php

namespace App\Livewire\Reports\Receivables;

use App\Exports\Reports\ReceivablesAgingExport;
use App\Services\Reports\ReceivablesAgingService;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Receivables Aging')]
class Index extends Component
{
    public string $asOf = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $this->asOf = now()->toDateString();
    }

    public function exportXlsx(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new ReceivablesAgingExport($this->asOf),
            'receivables-aging.xlsx',
            Excel::XLSX
        );
    }

    public function exportCsv(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new ReceivablesAgingExport($this->asOf),
            'receivables-aging.csv',
            Excel::CSV
        );
    }

    public function render(ReceivablesAgingService $service): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $asOf = Carbon::parse($this->asOf)->endOfDay();
        $data = $service->get($asOf);

        return view('livewire.reports.receivables.index', [
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }
}
