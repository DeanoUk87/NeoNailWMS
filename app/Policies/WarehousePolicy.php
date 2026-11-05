<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated roles can list their client's warehouses
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->fulfilment_client_id === $warehouse->fulfilment_client_id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->role === 'admin'
            && $user->fulfilment_client_id === $warehouse->fulfilment_client_id;
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return false; // Warehouses are deactivated, not deleted
    }
}
