<?php

namespace App\Providers;

use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\LocationPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserManagementPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Warehouse::class => WarehousePolicy::class,
        Product::class   => ProductPolicy::class,
        Location::class  => LocationPolicy::class,
        User::class      => UserManagementPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
