<flux:modal name="invoice-issue" class="w-full max-w-lg">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Issue invoice?</flux:heading>
            <flux:subheading>This will deduct stock and assign a document number. The action cannot be undone without voiding.</flux:subheading>
        </div>

        @if($issuingInvoice)
            <dl class="grid gap-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">Customer</dt><dd class="font-medium">{{ $issuingInvoice->customer?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">Location</dt><dd>{{ $issuingInvoice->location?->name ?? '—' }}</dd></div>
                <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700"><dt class="font-semibold">Total</dt><dd class="font-mono font-semibold">{{ $issuingInvoice->total }}</dd></div>
            </dl>
        @endif

        {{-- Credit warning --}}
        @if($creditWarning)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-600 dark:bg-amber-950/30 dark:text-amber-300">
                <strong>Credit limit exceeded:</strong> {{ $creditWarning }}
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="primary" wire:click="dismissCreditWarning">Acknowledge &amp; close</flux:button>
            </div>
        @else
            {{-- Issue error --}}
            @if($issueError)
                <div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-600 dark:bg-red-950/30 dark:text-red-300">
                    {{ $issueError }}
                </div>
            @endif
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeIssueConfirmation">Cancel</flux:button>
                <flux:button variant="primary" wire:click="issueInvoice" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="issueInvoice">Issue invoice</span>
                    <span wire:loading wire:target="issueInvoice">Issuing…</span>
                </flux:button>
            </div>
        @endif
    </div>
</flux:modal>
