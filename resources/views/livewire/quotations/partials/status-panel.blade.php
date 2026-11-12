<flux:modal name="quotation-status" class="max-w-md">
    <div class="space-y-5">
        <div>
            @if($statusAction === 'accept')
                <flux:heading size="lg">Accept quotation?</flux:heading>
                <flux:subheading>Mark this quotation as accepted by the customer.</flux:subheading>
            @else
                <flux:heading size="lg">Reject quotation?</flux:heading>
                <flux:subheading>Mark this quotation as rejected. You can optionally provide a reason.</flux:subheading>
            @endif
        </div>

        @if($statusAction === 'reject')
            <div>
                <flux:textarea wire:model="rejectReason" label="Reason (optional)" rows="3" placeholder="Why was the quotation rejected?" />
                @error('reject_reason') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
            </div>
        @endif

        @if($statusError)
            <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $statusError }}</flux:text>
        @endif

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="closeStatusPanel">Cancel</flux:button>
            @if($statusAction === 'accept')
                <flux:button type="button" variant="primary" icon="check" wire:click="applyStatus">Accept</flux:button>
            @else
                <flux:button type="button" variant="danger" wire:click="applyStatus">Reject</flux:button>
            @endif
        </div>
    </div>
</flux:modal>
