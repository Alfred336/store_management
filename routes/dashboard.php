<?php

use App\Livewire\Dashboard\Index as DashboardIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');
});
