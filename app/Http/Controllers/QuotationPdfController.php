<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceTemplate;
use App\Models\Quotation;
use App\Services\Access\LocationAccess;
use App\Services\Quotations\QuotationDocumentData;
use App\Services\Settings\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QuotationPdfController extends Controller
{
    /**
     * Render and stream a quotation PDF inline or as a download.
     *
     * Template and paper are read from Business Settings and cannot be
     * overridden via request params.
     *
     * Query params:
     *   mode – 'inline' | 'download'
     */
    public function __invoke(
        Quotation $quotation,
        Settings $settings,
        QuotationDocumentData $documentData,
    ): Response {
        $this->authorize('print', $quotation);
        app(LocationAccess::class)->ensure(auth()->user(), $quotation->location_id);

        $templateKey = (string) $settings->get('invoicing.quotation_template', InvoiceTemplate::default()->value);
        $template = InvoiceTemplate::tryFrom($templateKey) ?? InvoiceTemplate::default();
        if ($template->isThermal()) {
            $template = InvoiceTemplate::default();
        }

        $paper = (string) $settings->get('invoicing.quotation_paper', $template->defaultPaper());
        if (! in_array($paper, $template->supportedPapers(), true)) {
            $paper = $template->defaultPaper();
        }

        $mode = in_array(request()->input('mode'), ['inline', 'download'], true)
            ? request()->input('mode')
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
