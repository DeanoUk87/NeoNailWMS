<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    /**
     * Determine whether the user can view the warehouse.
     *
     * Returns true only if the authenticated user belongs to the same
     * fulfilment client as the warehouse. This provides explicit
     * tenant-level authorization on top of the Warehouse model's
     * global scope.
     *
     * Cross-client access receives a 403 Forbidden response (not 404),
     * making the access-denial explicit to the caller.
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->fulfilment_client_id === $warehouse->fulfilment_client_id;
    }
}
