<flux:modal name="invoice-void" class="w-full max-w-lg">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Void invoice?</flux:heading>
            <flux:subheading>All stock will be returned to inventory. This cannot be undone. A reason is required for the audit trail.</flux:subheading>
        </div>

        <flux:textarea wire:model="voidReason" label="Reason" rows="3" placeholder="Explain why this invoice is being voided…" required />

        @if($voidError)
            <div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-600 dark:bg-red-950/30 dark:text-red-300">
                {{ $voidError }}
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="closeVoidConfirmation">Cancel</flux:button>
            <flux:button variant="danger" wire:click="voidInvoice" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="voidInvoice">Void invoice</span>
                <span wire:loading wire:target="voidInvoice">Voiding…</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
