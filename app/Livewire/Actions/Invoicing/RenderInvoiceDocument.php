<?php

namespace App\Livewire\Actions\Invoicing;

use App\DTOs\RenderInvoiceDocumentResult;
use App\Enums\InvoiceTemplate;
use App\Models\Invoice;
use App\Models\InvoiceDocument;
use App\Services\Invoicing\InvoiceDocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Render an invoice PDF, record the invoice_documents row,
 * and optionally store the file on the private local disk.
 *
 * Rules:
 *  - Drafts and void invoices may be previewed with their watermark but are
 *    not persisted in invoice_documents and never counted as "original" or
 *    "duplicate".
 *  - For issued (posted) invoices the entire render+record step is wrapped in
 *    a transaction with the invoice row locked so that two simultaneous print
 *    requests cannot both claim to be the original.
 */
class RenderInvoiceDocument
{
    public function __construct(
        private readonly InvoiceDocumentData $documentData,
    ) {}

    /**
     * @param  string  $action  'print' | 'download' | 'save'
     */
    public function __invoke(
        Invoice $invoice,
        InvoiceTemplate $template,
        string $paper,
        string $action = 'print',
    ): RenderInvoiceDocumentResult {
        Gate::authorize('print', $invoice);

        $isDuplicate = false;
        $documentId = null;
        $bytes = '';

        // load() returns $this — relations are attached in-place
        $invoice->load(['items.unit', 'location', 'customer']);

        if ($invoice->status->isPosted()) {
            // Determine original/duplicate status under a lock so two
            // simultaneous renders cannot both claim is_duplicate = false.
            DB::transaction(function () use ($invoice, $template, $paper, $action, &$isDuplicate, &$documentId, &$bytes): void {
                // Lock the invoice row
                Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                $isDuplicate = InvoiceDocument::query()
                    ->where('invoice_id', $invoice->id)
                    ->exists();

                $data = $this->documentData->build($invoice, $isDuplicate);
                $bytes = $this->renderBytes($template, $paper, $data, count($data['lines']));

                $path = null;
                $sizeBytes = null;
                $sha256 = null;

                if ($action === 'save') {
                    $year = now()->format('Y');
                    $safeName = Str::slug(($invoice->number ?? 'draft').'-'.$template->value.'-'.now()->format('YmdHis'));
                    $path = "invoices/{$year}/{$safeName}.pdf";
                    Storage::disk('local')->put($path, $bytes);
                    $sizeBytes = strlen($bytes);
                    $sha256 = hash('sha256', $bytes);
                }

                $doc = InvoiceDocument::create([
                    'invoice_id' => $invoice->id,
                    'template_key' => $template->value,
                    'paper' => $paper,
                    'action' => $action,
                    'is_duplicate' => $isDuplicate,
                    'path' => $path,
                    'size_bytes' => $sizeBytes,
                    'sha256' => $sha256,
                    'generated_by' => auth()->id(),
                ]);
                $documentId = $doc->id;
            });
        } else {
            // Draft or void — render with watermark, no DB record
            $data = $this->documentData->build($invoice, false);
            $bytes = $this->renderBytes($template, $paper, $data, count($data['lines']));
        }

        return new RenderInvoiceDocumentResult($bytes, $isDuplicate, $documentId);
    }

    // ─── Private helper ───────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderBytes(InvoiceTemplate $template, string $paper, array $data, int $lineCount): string
    {
        $view = 'invoices.templates.'.$template->value;
        $pdf = Pdf::loadView($view, $data);

        if ($template->isThermal()) {
            // 1 mm ≈ 2.8346 pt; compute height dynamically to avoid blank pages
            $widthPt = $paper === '58mm' ? 164.41 : 226.77;
            $heightPt = max(300.0, 120.0 + ($lineCount * 22.0) + 100.0);
            $pdf->setPaper([0, 0, $widthPt, $heightPt], 'portrait');
        } else {
            $pdf->setPaper(strtolower($paper), 'portrait');
        }

        return (string) $pdf->output();
    }
}
