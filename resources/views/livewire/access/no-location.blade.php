<div class="flex min-h-screen items-center justify-center bg-zinc-50 dark:bg-zinc-900">
    <div class="mx-auto max-w-md px-6 py-12 text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
            <flux:icon name="map-pin" class="h-8 w-8 text-amber-600 dark:text-amber-400" />
        </div>

        <flux:heading size="xl" class="mb-2">No location assigned</flux:heading>

        <flux:text class="mb-8 text-zinc-500 dark:text-zinc-400">
            Your account has not been assigned to any location yet.
            Please contact your administrator to have a location assigned before you can access the system.
        </flux:text>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle">
                Sign out
            </flux:button>
        </form>
    </div>
</div>
