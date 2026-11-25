<?php

namespace App\Services\Access;

use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central service for location-based access control.
 *
 * Rules:
 *  - Admin role: unrestricted access to every location (allowedLocationIds returns null).
 *  - All other roles: access only to ACTIVE locations in their location_user pivot.
 *  - A non-admin with no assigned locations can still log in but sees no data.
 *
 * The service is resolved once per request by the IoC container, so the
 * in-memory cache ($cache) is per-request only — no cross-request leakage.
 */
class LocationAccess
{
    /** @var array<int, list<int>|null> Per-request cache: user_id => allowed location ids, or null for Admin */
    private array $cache = [];

    /**
     * Return the IDs of locations the user may access.
     *
     * Returns null for Admin (unrestricted — do NOT add a whereIn filter).
     * Returns an empty array when the user has no assigned locations.
     *
     * @return list<int>|null
     */
    public function allowedLocationIds(User $user): ?array
    {
        if ($user->hasRole('Admin')) {
            return null;
        }

        if (! isset($this->cache[$user->id])) {
            $this->cache[$user->id] = array_values(
                $user->locations()
                    ->where('locations.is_active', true)
                    ->pluck('locations.id')
                    ->map(fn ($id) => (int) $id)
                    ->all()
            );
        }

        return $this->cache[$user->id];
    }

    public function canAccess(User $user, int $locationId): bool
    {
        $ids = $this->allowedLocationIds($user);

        return $ids === null || in_array($locationId, $ids, true);
    }

    /**
     * Throw an AuthorizationException if the user may not access the location.
     *
     * @throws AuthorizationException
     */
    public function ensure(User $user, int $locationId): void
    {
        if (! $this->canAccess($user, $locationId)) {
            throw new AuthorizationException('You do not have access to that location.');
        }
    }

    /**
     * Return the current working location stored in the session.
     *
     * Defaults to the user's only assigned location when they have exactly one.
     * Returns null when the user has no locations or has multiple but hasn't chosen yet.
     */
    public function currentLocationId(User $user): ?int
    {
        $ids = $this->allowedLocationIds($user);

        // Admin or unrestricted: use the session value directly
        if ($ids === null) {
            $stored = session('location.current_id');

            return $stored !== null ? (int) $stored : null;
        }

        if (empty($ids)) {
            return null;
        }

        $stored = session('location.current_id');

        // Auto-select the only location
        if (count($ids) === 1) {
            if ($stored !== (int) $ids[0]) {
                session(['location.current_id' => $ids[0]]);
            }

            return $ids[0];
        }

        // Multi-location: use stored value only if it is still allowed
        if ($stored !== null && in_array((int) $stored, $ids, true)) {
            return (int) $stored;
        }

        return null;
    }

    /**
     * Persist the user's chosen location in the session.
     *
     * @throws AuthorizationException if the user may not access the requested location.
     */
    public function setCurrentLocationId(User $user, int $locationId): void
    {
        $this->ensure($user, $locationId);
        session(['location.current_id' => $locationId]);
    }

    /**
     * Drop the per-request cache for a user so the next call re-reads from the DB.
     * Call this after changing a user's location assignments.
     */
    public function flush(int $userId): void
    {
        unset($this->cache[$userId]);
    }

    /**
     * Return all active Location models visible to this user.
     * Admins see all; others see their assigned active locations.
     *
     * @return Collection<int, Location>
     */
    public function allowedLocations(User $user): Collection
    {
        $ids = $this->allowedLocationIds($user);

        $q = Location::query()->where('is_active', true)->orderBy('name');

        if ($ids !== null) {
            $q->whereIn('id', $ids);
        }

        return $q->get();
    }
}
