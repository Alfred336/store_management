<?php

use App\Livewire\Products\Index as ProductsIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('products', ProductsIndex::class)->name('products.index');
});
