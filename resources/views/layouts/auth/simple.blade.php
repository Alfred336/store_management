<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-6">
                {{-- Brand mark --}}
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3" wire:navigate>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent text-accent-foreground shadow-md">
                        <x-app-logo-icon class="size-7 fill-current text-white" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ $companyName ?? config('app.name') }}
                    </span>
                </a>

                {{-- Card --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
