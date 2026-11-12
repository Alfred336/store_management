<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceTemplate;
use App\Models\Quotation;
use App\Services\Quotations\QuotationDocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuotationPdfController extends Controller
{
    /**
     * Render and stream a quotation PDF inline or as a download.
     *
     * Query params:
     *   template – InvoiceTemplate value (defaults to classic; thermal excluded)
     *   paper    – paper size (defaults to template default)
     *   mode     – 'inline' | 'download'
     */
    public function __invoke(
        Quotation $quotation,
        Request $request,
        QuotationDocumentData $documentData,
    ): Response {
        $this->authorize('print', $quotation);

        $templateKey = $request->input('template', 'classic');
        $template = InvoiceTemplate::tryFrom((string) $templateKey);
        abort_if($template === null || $template->isThermal(), 422, 'Invalid template for quotation.');

        $paper = (string) $request->input('paper', $template->defaultPaper());
        abort_unless(in_array($paper, $template->supportedPapers(), true), 422, 'Invalid paper size for this template.');

        $mode = in_array($request->input('mode'), ['inline', 'download'], true)
            ? $request->input('mode')
            : 'inline';

        $quotation->load(['items.unit', 'location', 'customer']);
        $data = $documentData->build($quotation);

        $view = 'quotations.templates.'.$template->value;
        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper(strtolower($paper), 'portrait');
        $bytes = (string) $pdf->output();

        $filename = ($quotation->number ?? 'quotation-draft').'-'.$template->value.'.pdf';
        $disposition = $mode === 'inline' ? 'inline' : 'attachment';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Content-Length' => strlen($bytes),
        ]);
    }
}
