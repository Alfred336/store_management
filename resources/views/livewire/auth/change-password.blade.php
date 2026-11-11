<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Change your password')"
        :description="__('Your account requires a new password before you can continue.')"
    />

    {{-- General error (e.g. mail failure) --}}
    @error('error')
        <flux:callout variant="danger" icon="x-circle">{{ $message }}</flux:callout>
    @enderror

    <form wire:submit="updatePassword" class="flex flex-col gap-6">
        <flux:field>
            <flux:label>
                {{ auth()->user()?->must_change_password ? __('Temporary password') : __('Current password') }}
            </flux:label>
            <flux:input
                wire:model="current_password"
                type="password"
                viewable
                required
                autocomplete="current-password"
            />
            <flux:error name="current_password" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('New password') }}</flux:label>
            @php
                $passwordHint = \Illuminate\Validation\Rules\Password::defaults() instanceof \Illuminate\Validation\Rules\Password
                    ? __('Minimum 12 characters. Must include uppercase and lowercase letters, numbers, and symbols.')
                    : null;
            @endphp
            @if($passwordHint)
                <flux:description>{{ $passwordHint }}</flux:description>
            @endif
            <flux:input
                wire:model="password"
                type="password"
                viewable
                required
                autocomplete="new-password"
            />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Confirm new password') }}</flux:label>
            <flux:input
                wire:model="password_confirmation"
                type="password"
                viewable
                required
                autocomplete="new-password"
            />
            <flux:error name="password_confirmation" />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Update password') }}
        </flux:button>
    </form>
</div>
