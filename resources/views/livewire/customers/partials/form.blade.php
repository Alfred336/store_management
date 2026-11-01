<flux:modal name="customer-form" class="w-full max-w-2xl" variant="flyout" position="right" scroll="body">
    <form wire:submit="{{ $customerId ? 'update' : 'store' }}" class="space-y-6">
        <div><flux:heading size="lg">{{ $customerId ? 'Edit customer' : 'Create customer' }}</flux:heading><flux:subheading>Customer codes are generated automatically.</flux:subheading></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" label="Name" required />
            <flux:select wire:model="type" label="Type" required><option value="individual">Individual</option><option value="business">Business</option></flux:select>
            <flux:input wire:model="phone" label="Phone" type="tel" />
            <flux:input wire:model="email" label="Email" type="email" />
            <flux:input wire:model="tin" label="TIN" />
            <flux:input wire:model="creditLimit" label="Credit limit (TZS)" type="number" min="0" step="0.01" required />
            <flux:input wire:model="paymentTermsDays" label="Payment terms (days)" type="number" min="0" max="365" required />
            <flux:checkbox wire:model="isActive" label="Customer is active" />
            <flux:textarea wire:model="address" label="Address" rows="2" class="sm:col-span-2" />
            <flux:textarea wire:model="notes" label="Notes" rows="3" class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="closeForm">Cancel</flux:button>
            <flux:button type="submit" variant="primary">Save customer</flux:button>
        </div>
    </form>
</flux:modal>