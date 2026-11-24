<?php

namespace App\Livewire\Access;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Shown to authenticated users who have no active location assigned. */
#[Title('No Location Assigned')]
#[Layout('layouts.auth')]
class NoLocation extends Component
{
    public function render(): View
    {
        return view('livewire.access.no-location');
    }
}
