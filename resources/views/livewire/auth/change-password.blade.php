<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Change your password')"
        :description="__('Your account requires a new password before you can continue.')"
    />

    <form wire:submit="updatePassword" class="flex flex-col gap-6">
        <flux:input
            wire:model="current_password"
            :label="__('Current password')"
            type="password"
            required
            autocomplete="current-password"
        />

        <flux:input
            wire:model="password"
            :label="__('New password')"
            type="password"
            required
            autocomplete="new-password"
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm new password')"
            type="password"
            required
            autocomplete="new-password"
        />

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Update password') }}
        </flux:button>
    </form>
</div>
