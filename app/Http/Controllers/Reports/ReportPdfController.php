<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\PaymentsByMethodService;
use App\Services\Reports\PosSessionsService;
use App\Services\Reports\ReceivablesAgingService;
use App\Services\Reports\SalesSummaryService;
use App\Services\Reports\VatSummaryService;
use App\Services\Reports\VoidedInvoicesService;
use App\Services\Settings\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Render any report as a PDF.
 *
 * Route: GET /reports/{report}/pdf
 * Query params: dateFrom, dateTo, locationId, categoryId (inventory only), asOf (aging only)
 * Accepts report slugs: sales, vat, receivables, payments, pos-sessions, inventory, voided-invoices
 */
class ReportPdfController extends Controller
{
    private const REPORTS = ['sales', 'vat', 'receivables', 'payments', 'pos-sessions', 'inventory', 'voided-invoices'];

    public function __invoke(
        string $report,
        Request $request,
        Settings $settings,
        SalesSummaryService $salesSummary,
        VatSummaryService $vatSummary,
        ReceivablesAgingService $receivablesAging,
        PaymentsByMethodService $paymentsByMethod,
        PosSessionsService $posSessions,
        InventoryReportService $inventoryReport,
        VoidedInvoicesService $voidedInvoices,
    ): Response {
        Gate::authorize('reports.view');
        abort_unless(in_array($report, self::REPORTS, true), 404);

        $tz = 'Africa/Dar_es_Salaam';
        $from = Carbon::parse($request->input('dateFrom', now($tz)->startOfMonth()->toDateString()), $tz)->startOfDay();
        $to = Carbon::parse($request->input('dateTo', now($tz)->endOfMonth()->toDateString()), $tz)->endOfDay();
        $locationId = $request->filled('locationId') ? (int) $request->input('locationId') : null;

        $company = [
            'name' => (string) $settings->get('business.name', ''),
            'phone' => (string) $settings->get('business.phone', ''),
            'address' => (string) $settings->get('business.address', ''),
            'tin' => (string) $settings->get('business.tin', ''),
            'vrn' => (string) $settings->get('business.vrn', ''),
            'currency' => (string) $settings->get('business.currency', 'TZS'),
        ];

        $filters = [
            'date_from' => $from->format('d M Y'),
            'date_to' => $to->format('d M Y'),
            'location_id' => $locationId,
        ];

        $data = match ($report) {
            'sales' => array_merge(
                $salesSummary->get($from, $to, $locationId),
                ['company' => $company, 'filters' => $filters, 'title' => 'Sales Summary']
            ),
            'vat' => array_merge(
                $vatSummary->get($from, $to, $locationId),
                ['company' => $company, 'filters' => $filters, 'title' => 'VAT Summary']
            ),
            'receivables' => (function () use ($request, $tz, $receivablesAging, $company): array {
                $asOf = Carbon::parse($request->input('asOf', now($tz)->toDateString()), $tz)->endOfDay();
                $result = $receivablesAging->get($asOf);

                return array_merge($result, [
                    'company' => $company,
                    'filters' => ['as_of' => $asOf->format('d M Y')],
                    'title' => 'Receivables Aging',
                ]);
            })(),
            'payments' => array_merge(
                $paymentsByMethod->get($from, $to, $locationId),
                ['company' => $company, 'filters' => $filters, 'title' => 'Payments by Method']
            ),
            'pos-sessions' => [
                'sessions' => $posSessions->paginate($from, $to, $locationId, 500),
                'company' => $company,
                'filters' => $filters,
                'title' => 'POS Sessions',
            ],
            'inventory' => (function () use ($request, $inventoryReport, $company, $filters): array {
                $categoryId = $request->filled('categoryId') ? (int) $request->input('categoryId') : null;
                $locationId = $filters['location_id'];

                return [
                    'levels' => $inventoryReport->paginate($locationId, $categoryId, 500),
                    'totals' => $inventoryReport->totals($locationId, $categoryId),
                    'company' => $company,
                    'filters' => $filters,
                    'title' => 'Inventory Report',
                ];
            })(),
            'voided-invoices' => [
                'invoices' => $voidedInvoices->paginate($from, $to, $locationId, 500),
                'company' => $company,
                'filters' => $filters,
                'title' => 'Voided Invoices Audit',
            ],
        };

        $view = 'reports.pdf.'.str_replace('-', '_', $report);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $filename = $report.'-report.pdf';
        $mode = $request->input('mode') === 'download' ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$mode}; filename=\"{$filename}\"",
        ]);
    }
}
