<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Location $location): bool
    {
        // User must belong to the same client as the warehouse owning this location
        return $user->fulfilment_client_id === $location->warehouse->fulfilment_client_id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Location $location): bool
    {
        return $user->role === 'admin'
            && $user->fulfilment_client_id === $location->warehouse->fulfilment_client_id;
    }

    public function delete(User $user, Location $location): bool
    {
        return false; // Locations are deactivated, not deleted
    }
}
