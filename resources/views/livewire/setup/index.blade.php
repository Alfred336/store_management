<div class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl">Set up your business</flux:heading>
            <flux:subheading class="mt-2">Create the business profile and first administrator account.</flux:subheading>
        </div>

        @error('setup')
            <flux:callout variant="danger" icon="x-circle">{{ $message }}</flux:callout>
        @enderror

        <form wire:submit="complete" class="flex flex-col gap-6">
            <section class="space-y-4">
                <flux:heading size="lg">Business details</flux:heading>
                <flux:input wire:model="businessName" label="Business name" required autofocus />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="businessEmail" label="Business email" type="email" />
                    <flux:input wire:model="phone" label="Phone" type="tel" />
                    <flux:input wire:model="businessTin" label="TIN" />
                    <flux:input wire:model="businessVrn" label="VRN" />
                </div>
                <flux:textarea wire:model="address" label="Address" rows="3" />
                <flux:input label="Currency" value="TZS" disabled />
            </section>

            <section class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:heading size="lg">Administrator account</flux:heading>
                <flux:input wire:model="adminName" label="Full name" required autocomplete="name" />
                <flux:input wire:model="adminEmail" label="Email address" type="email" required autocomplete="email" />
                <flux:input wire:model="password" label="Password (at least 12 characters, letters and numbers)" type="password" required autocomplete="new-password" viewable />
                <flux:input wire:model="passwordConfirmation" label="Confirm password" type="password" required autocomplete="new-password" viewable />
            </section>

            <flux:button variant="primary" type="submit" class="w-full">Complete setup</flux:button>
        </form>
</div>