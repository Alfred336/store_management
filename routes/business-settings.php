<?php

use App\Livewire\Settings\Index as BusinessSettingsIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('business-settings', BusinessSettingsIndex::class)->name('settings.index');
});
