<flux:modal name="quotation-email" class="max-w-lg">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Email quotation</flux:heading>
            <flux:subheading>Send the quotation PDF to the customer or prospect.</flux:subheading>
        </div>

        <div>
            <flux:input wire:model="emailTo" label="Recipient email" type="email" required placeholder="customer@example.com" />
            @error('emailTo') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
        </div>

        <div>
            <flux:label>Template</flux:label>
            <flux:select wire:model="emailTemplate">
                @foreach($templates as $tpl)
                    <option value="{{ $tpl->value }}">{{ ucfirst($tpl->value) }}</option>
                @endforeach
            </flux:select>
        </div>

        <div>
            <flux:textarea wire:model="emailMessage" label="Message (optional)" rows="3" placeholder="Add a personal note…" />
        </div>

        @if($emailError)
            <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $emailError }}</flux:text>
        @endif

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="closeEmailPanel">Cancel</flux:button>
            <flux:button type="button" variant="primary" icon="envelope" wire:click="sendEmail">Send email</flux:button>
        </div>
    </div>
</flux:modal>
