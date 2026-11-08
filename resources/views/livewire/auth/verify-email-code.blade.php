<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Check your email')"
        :description="__('We sent a 6-digit verification code to your email address. Enter it below to continue.')"
    />

    {{-- Masked email hint --}}
    <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
        Code sent to
        <span class="font-medium text-zinc-700 dark:text-zinc-200">
            {{ mb_substr(auth()->user()->email, 0, 2) }}***@{{ Str::after(auth()->user()->email, '@') }}
        </span>
    </p>

    <form wire:submit="verify" class="flex flex-col gap-6">
        <flux:input
            wire:model="code"
            :label="__('Verification code')"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            placeholder="000000"
            autocomplete="one-time-code"
            autofocus
        />

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Verify email') }}
        </flux:button>
    </form>

    <div class="flex items-center justify-between gap-4 text-sm">
        @if ($resendCooldown > 0)
            <span class="text-zinc-500 dark:text-zinc-400" wire:poll.1000ms="$set('resendCooldown', max(0, $resendCooldown - 1))">
                {{ __('Resend in') }} {{ $resendCooldown }}s
            </span>
        @else
            <flux:button variant="ghost" size="sm" wire:click="resend">
                {{ __('Resend code') }}
            </flux:button>
        @endif

        <flux:button variant="ghost" size="sm" wire:click="logout">
            {{ __('Log out') }}
        </flux:button>
    </div>
</div>
