<?php

use App\Livewire\Setup\Index as SetupIndex;
use Illuminate\Support\Facades\Route;

Route::livewire('setup', SetupIndex::class)->name('setup.index');
