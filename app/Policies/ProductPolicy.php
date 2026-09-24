<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Product authorization policy.
 *
 * Role matrix:
 *   admin      → view, create, edit, delete
 *   supervisor → view, create, edit (no delete)
 *   operator   → view only
 *
 * All methods additionally enforce client scoping:
 * the product must belong to the user's fulfilment client.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated roles can list
    }

    public function view(User $user, Product $product): bool
    {
        return $user->fulfilment_client_id === $product->fulfilment_client_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    public function update(User $user, Product $product): bool
    {
        return in_array($user->role, ['admin', 'supervisor'])
            && $user->fulfilment_client_id === $product->fulfilment_client_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->role === 'admin'
            && $user->fulfilment_client_id === $product->fulfilment_client_id;
    }
}
