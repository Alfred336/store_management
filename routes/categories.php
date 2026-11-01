<?php

use App\Livewire\Categories\Index as CategoriesIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('categories', CategoriesIndex::class)->name('categories.index');
});
