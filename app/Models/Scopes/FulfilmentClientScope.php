<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope that restricts Warehouse queries to the authenticated
 * user's fulfilment_client_id. This ensures tenant isolation at the
 * query level, independent of route middleware.
 */
class FulfilmentClientScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $builder->where(
                $model->getTable().'.fulfilment_client_id',
                Auth::user()->fulfilment_client_id
            );
        }
    }
}
