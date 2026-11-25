<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Access\LocationAccess;
use App\Services\Settings\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    /**
     * Render and stream a payment receipt PDF.
     *
     * Payment receipts are always printed on thermal paper at the configured
     * receipt width (pos.receipt_width from Business Settings).
     *
     * Query parameters:
     *   mode – 'inline' | 'download' (defaults to 'inline')
     */
    public function __invoke(Payment $payment, Settings $settings): Response
    {
        $this->authorize('view', $payment);

        $access = app(LocationAccess::class);
        if ($payment->location_id !== null) {
            $access->ensure(auth()->user(), $payment->location_id);
        } else {
            // Null-location payments (unallocated/multi-location) are Admin-only.
            abort_unless($access->allowedLocationIds(auth()->user()) === null, 403);
        }

        $mode = request()->input('mode') === 'download' ? 'download' : 'inline';

        $receiptWidth = (string) $settings->get('pos.receipt_width', '80mm');
        $widthPt = $receiptWidth === '58mm' ? 164.41 : 226.77;

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

        $pdf = Pdf::loadView('payments.receipt-thermal', $data);

        $heightPt = max(300.0, 120.0 + (count($allocations) * 22.0) + 100.0);
        $pdf->setPaper([0, 0, $widthPt, $heightPt], 'portrait');

        $filename = $payment->number.'-receipt.pdf';
        $disposition = $mode === 'download' ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
