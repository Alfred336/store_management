<flux:modal name="quotation-send" class="max-w-md">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Send quotation?</flux:heading>
            <flux:subheading>Sending will assign a quotation number and lock the document from further edits.</flux:subheading>
        </div>
        @if($selectedQuotation)
            <div class="rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                <div class="flex justify-between"><span class="text-zinc-500">Recipient</span><span class="font-medium">{{ $selectedQuotation->recipientName() }}</span></div>
                <div class="flex justify-between mt-1"><span class="text-zinc-500">Total</span><span class="font-mono">{{ $selectedQuotation->total }}</span></div>
                <div class="flex justify-between mt-1"><span class="text-zinc-500">Valid until</span><span>{{ $selectedQuotation->valid_until->format('d M Y') }}</span></div>
            </div>
        @endif
        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="closeSendConfirmation">Cancel</flux:button>
            <flux:button type="button" variant="primary" icon="paper-airplane" wire:click="sendQuotation">Send</flux:button>
        </div>
    </div>
</flux:modal>
