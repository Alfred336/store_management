<?php

use App\Livewire\Locations\Index as LocationsIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('locations', LocationsIndex::class)->name('locations.index');
});
