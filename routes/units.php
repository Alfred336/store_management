<?php

use App\Livewire\Units\Index as UnitsIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('units', UnitsIndex::class)->name('units.index');
});
