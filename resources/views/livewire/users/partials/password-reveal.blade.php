<flux:modal name="user-password-reveal" class="w-full max-w-md">
    <div class="space-y-4 p-6">
        <flux:heading size="lg">Temporary password</flux:heading>
        <flux:text>
            Share this password with the user. It will not be shown again and is not stored in plain text.
            The user will be asked to change it on first login.
        </flux:text>
        <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
            <code class="flex-1 select-all font-mono text-sm font-semibold tracking-wide">{{ $revealedPassword }}</code>
        </div>
        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="closePasswordReveal">Done</flux:button>
        </div>
    </div>
</flux:modal>
