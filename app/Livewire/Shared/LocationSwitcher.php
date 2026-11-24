<?php

namespace App\Livewire\Shared;

use App\Services\Access\LocationAccess;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Top-bar location switcher.
 *
 * Visible only when the user has two or more allowed locations.
 * Switching stores the choice in the session via LocationAccess::setCurrentLocationId()
 * and triggers a full-page redirect so all scoped queries immediately reflect the new location.
 */
class LocationSwitcher extends Component
{
    #[Locked]
    public int $currentId = 0;

    public function mount(LocationAccess $locationAccess): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }
        $this->currentId = $locationAccess->currentLocationId($user) ?? 0;
    }

    public function switchTo(int $locationId, LocationAccess $locationAccess): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $locationAccess->setCurrentLocationId($user, $locationId);
        $this->currentId = $locationId;

        // Redirect to the same page so all scoped reads start fresh.
        $this->redirect(request()->url(), navigate: true);
    }

    public function render(LocationAccess $locationAccess): View
    {
        $user = auth()->user();

        $locations = $user !== null ? $locationAccess->allowedLocations($user) : collect();

        return view('livewire.shared.location-switcher', [
            'locations' => $locations,
        ]);
    }
}
