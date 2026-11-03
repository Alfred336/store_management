<section class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
    <flux:heading size="sm">Print / Download</flux:heading>

    {{-- Template and paper pickers --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="printTemplate" label="Template" size="sm">
            @foreach($templates as $tmpl)
                <option value="{{ $tmpl->value }}">{{ $tmpl->label() }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model="printPaper" label="Paper" size="sm">
            @php
                $supportedPapers = \App\Enums\InvoiceTemplate::tryFrom($printTemplate)?->supportedPapers() ?? ['A4'];
            @endphp
            @foreach($supportedPapers as $sz)
                <option value="{{ $sz }}">{{ $sz }}</option>
            @endforeach
        </flux:select>
    </div>

    {{-- Preview iframe + action buttons --}}
    @if($inv->status->isPosted())
    <div>
        <iframe
            src="{{ route('invoices.pdf', ['invoice' => $inv->id, 'template' => $printTemplate, 'paper' => $printPaper, 'mode' => 'inline']) }}"
            class="h-64 w-full rounded-lg border border-zinc-200 dark:border-zinc-700"
            title="Invoice preview"
        ></iframe>
    </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <flux:button
            as="a"
            href="{{ route('invoices.pdf', ['invoice' => $inv->id, 'template' => $printTemplate, 'paper' => $printPaper, 'mode' => 'inline']) }}"
            target="_blank"
            variant="primary"
            size="sm"
            icon="printer"
        >
            Print
        </flux:button>

        <flux:button
            as="a"
            href="{{ route('invoices.pdf', ['invoice' => $inv->id, 'template' => $printTemplate, 'paper' => $printPaper, 'mode' => 'download']) }}"
            variant="ghost"
            size="sm"
            icon="arrow-down-tray"
        >
            Download PDF
        </flux:button>

        <flux:button
            as="a"
            href="{{ route('invoices.pdf', ['invoice' => $inv->id, 'template' => $printTemplate, 'paper' => $printPaper, 'mode' => 'save']) }}"
            variant="ghost"
            size="sm"
            icon="folder-arrow-down"
        >
            Save PDF
        </flux:button>
    </div>

    {{-- Saved documents list --}}
    @if(count($printDocuments) > 0)
    <div>
        <flux:heading size="sm" class="mb-2">Saved documents</flux:heading>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th class="py-1 text-left font-medium text-zinc-500">Date</th>
                    <th class="py-1 text-left font-medium text-zinc-500">Template</th>
                    <th class="py-1 text-left font-medium text-zinc-500">Paper</th>
                    <th class="py-1 text-left font-medium text-zinc-500">Type</th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($printDocuments as $doc)
                <tr class="border-b border-zinc-100 dark:border-zinc-800">
                    <td class="py-1 text-zinc-600 dark:text-zinc-400">{{ $doc->created_at?->format('d M Y H:i') }}</td>
                    <td class="py-1">{{ $doc->template_key }}</td>
                    <td class="py-1">{{ $doc->paper }}</td>
                    <td class="py-1">
                        @if($doc->is_duplicate)
                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">DUPLICATE</span>
                        @else
                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Original</span>
                        @endif
                    </td>
                    <td class="py-1 text-right">
                        @if($doc->path)
                        <flux:button
                            as="a"
                            href="{{ route('invoices.document.download', ['invoice' => $inv->id, 'document' => $doc->id]) }}"
                            variant="ghost"
                            size="xs"
                            icon="arrow-down-tray"
                        >
                            Download
                        </flux:button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>
