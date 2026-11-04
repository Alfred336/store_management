<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Settings\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    /**
     * Render and stream a payment receipt PDF.
     *
     * Query parameters:
     *   paper  – 'a4' | 'thermal' (defaults to 'a4')
     *   mode   – 'inline' | 'download' (defaults to 'inline')
     */
    public function __invoke(Payment $payment, Request $request, Settings $settings): Response
    {
        $this->authorize('view', $payment);

        $paper = in_array($request->input('paper'), ['a4', 'thermal'], true)
            ? $request->input('paper')
            : 'a4';

        $mode = $request->input('mode') === 'download' ? 'download' : 'inline';

        $company = [
            'name' => (string) $settings->get('business.name', ''),
            'phone' => (string) $settings->get('business.phone', ''),
            'address' => (string) $settings->get('business.address', ''),
            'tin' => (string) $settings->get('business.tin', ''),
            'vrn' => (string) $settings->get('business.vrn', ''),
            'footer_terms' => (string) $settings->get('business.footer_terms', ''),
            'currency' => (string) $settings->get('business.currency', 'TZS'),
        ];

        $payment->loadMissing(['customer', 'receiver', 'allocations.invoice']);

        $allocations = $payment->allocations->map(fn ($a) => [
            'invoice_number' => $a->invoice->number ?? "INV #{$a->invoice_id}",
            'amount' => number_format((float) $a->amount, 2),
        ])->all();

        $data = [
            'company' => $company,
            'payment' => [
                'number' => $payment->number,
                'amount' => number_format((float) $payment->amount, 2),
                'method' => ucwords(str_replace('_', ' ', $payment->method)),
                'provider' => $payment->provider,
                'reference' => $payment->reference,
                'received_at' => $payment->received_at->format('d M Y'),
                'notes' => $payment->notes,
                'status' => $payment->status,
            ],
            'customer' => [
                'name' => $payment->customer->name ?? '—',
            ],
            'receiver' => $payment->receiver->name ?? '—',
            'allocations' => $allocations,
        ];

        $template = $paper === 'thermal' ? 'payments.receipt-thermal' : 'payments.receipt';
        $paperSize = $paper === 'thermal' ? [80, 200] : 'a4';

        $pdf = Pdf::loadView($template, $data);

        if ($paper === 'thermal') {
            $pdf->setPaper($paperSize, 'portrait');
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        $filename = $payment->number.'-receipt.pdf';
        $disposition = $mode === 'download' ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
