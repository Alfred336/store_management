<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceTemplate;
use App\Livewire\Actions\Invoicing\RenderInvoiceDocument;
use App\Models\Invoice;
use App\Models\InvoiceDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePdfController extends Controller
{
    /**
     * Render and stream an invoice PDF.
     *
     * Query parameters:
     *   template  – InvoiceTemplate value (defaults to invoice's template_key or 'classic')
     *   paper     – paper size; must be in template->supportedPapers() (defaults to template default)
     *   mode      – 'inline' | 'download' | 'save' (defaults to 'inline')
     */
    public function render(Invoice $invoice, Request $request, RenderInvoiceDocument $renderInvoiceDocument): Response
    {
        $this->authorize('print', $invoice);

        $templateKey = $request->input('template', $invoice->template_key ?? InvoiceTemplate::default()->value);
        $template = InvoiceTemplate::tryFrom((string) $templateKey);
        abort_if($template === null, 422, 'Invalid template.');

        $paper = (string) $request->input('paper', $template->defaultPaper());
        abort_unless(in_array($paper, $template->supportedPapers(), true), 422, 'Invalid paper size for this template.');

        $mode = in_array($request->input('mode'), ['inline', 'download', 'save'], true)
            ? $request->input('mode')
            : 'inline';
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

        abort_if($document->invoice_id !== $invoice->id, 404);
        abort_if($document->path === null, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $filename = basename($document->path);

        return Storage::disk('local')->download($document->path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
