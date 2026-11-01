<?php

use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Units\Index as UnitsIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('units', UnitsIndex::class)->name('units.index');
    Route::livewire('categories', CategoriesIndex::class)->name('categories.index');
    Route::livewire('locations', LocationsIndex::class)->name('locations.index');
    Route::livewire('products', ProductsIndex::class)->name('products.index');
});
