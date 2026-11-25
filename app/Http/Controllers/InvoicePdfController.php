<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceTemplate;
use App\Livewire\Actions\Invoicing\RenderInvoiceDocument;
use App\Models\Invoice;
use App\Models\InvoiceDocument;
use App\Services\Access\LocationAccess;
use App\Services\Settings\Settings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePdfController extends Controller
{
    /**
     * Render and stream an invoice PDF.
     *
     * Template and paper are read from Business Settings and cannot be
     * overridden via request params.  POS-channel invoices always use the
     * thermal layout at the configured receipt width.
     *
     * Query parameters:
     *   mode – 'inline' | 'download' | 'save' (defaults to 'inline')
     */
    public function render(Invoice $invoice, Settings $settings, RenderInvoiceDocument $renderInvoiceDocument): Response
    {
        $this->authorize('print', $invoice);
        app(LocationAccess::class)->ensure(auth()->user(), $invoice->location_id);

        // Determine template and paper from settings; POS channel is always thermal.
        if ($invoice->channel === 'pos') {
            $template = InvoiceTemplate::Thermal;
            $paper = (string) $settings->get('pos.receipt_width', '80mm');
        } else {
            $templateKey = (string) $settings->get('invoicing.invoice_template', InvoiceTemplate::default()->value);
            $template = InvoiceTemplate::tryFrom($templateKey) ?? InvoiceTemplate::default();
            if ($template->isThermal()) {
                $template = InvoiceTemplate::default();
            }
            $paper = (string) $settings->get('invoicing.invoice_paper', $template->defaultPaper());
            if (! in_array($paper, $template->supportedPapers(), true)) {
                $paper = $template->defaultPaper();
            }
        }

        $mode = request()->input('mode');
        $action = match ($mode) {
            'download' => 'download',
            'save' => 'save',
            default => 'print',
        };

        $result = $renderInvoiceDocument($invoice, $template, $paper, $action);
        $filename = ($invoice->number ?? 'draft').'-'.$template->value.'.pdf';
        $disposition = $mode === 'inline' ? 'inline' : 'attachment';

        return response($result->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Content-Length' => strlen($result->bytes),
            'X-Is-Duplicate' => $result->isDuplicate ? '1' : '0',
        ]);
    }

    /**
     * Download a previously saved PDF from the private local disk.
     */
    public function download(Invoice $invoice, InvoiceDocument $document): StreamedResponse
    {
        $this->authorize('print', $invoice);
        app(LocationAccess::class)->ensure(auth()->user(), $invoice->location_id);

        abort_if($document->invoice_id !== $invoice->id, 404);
        abort_if($document->path === null, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $filename = basename($document->path);

        return Storage::disk('local')->download($document->path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
