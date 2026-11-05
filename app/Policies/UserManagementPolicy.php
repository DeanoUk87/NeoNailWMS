<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy for managing other users.
 * Only admins can manage users, and only within their own fulfilment client.
 */
class UserManagementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, User $target): bool
    {
        // Admin can only manage users of the same client
        // Admin cannot modify their own role (to prevent lock-out)
        return $user->role === 'admin'
            && $user->fulfilment_client_id === $target->fulfilment_client_id
            && $user->id !== $target->id;
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->role === 'admin'
            && $user->fulfilment_client_id === $target->fulfilment_client_id
            && $user->id !== $target->id; // Cannot deactivate yourself
    }
}
