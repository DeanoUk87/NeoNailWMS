<?php

use App\Http\Controllers\AdminFoundationController;
use App\Http\Controllers\WarehouseController;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Admin foundation route — accessible only to role=admin (403 for other roles)
    Route::get('/admin/foundation', AdminFoundationController::class)->name('admin.foundation');

    // Warehouse detail route — scoped to authenticated user's fulfilment client
    // Cross-client access returns 404 (global scope hides the record) or 403 (policy check)
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');

    // -------------------------------------------------------------------------
    // Product catalogue & variant mapping routes (Phase 2)
    // -------------------------------------------------------------------------
    Route::prefix('products')->name('products.')->group(function () {

        // List — all roles
        Route::get('/', fn () => view('products.index'))->name('index');

        // Create / edit — admin & supervisor only (policy enforced in Livewire component)
        Route::get('/create', fn () => view('products.create'))->name('create');
        Route::get('/{product}/edit', function (Product $product) {
            return view('products.edit', compact('product'));
        })->name('edit');

        // Variant mappings list
        Route::get('/mappings', fn () => view('products.mappings'))->name('mappings');

        // CSV import wizard — admin & supervisor only (enforced in component)
        Route::get('/import', fn () => view('products.import'))->name('import');
    });

    // -------------------------------------------------------------------------
    // Warehouse and Location Management (Task 004 — Module A)
    // -------------------------------------------------------------------------
    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        Route::get('/', fn () => view('warehouses.index'))->name('index');
        Route::get('/create', fn () => view('warehouses.create'))->name('create');
        Route::get('/{warehouse}/edit', function (Warehouse $warehouse) {
            return view('warehouses.edit', compact('warehouse'));
        })->name('edit');

        // Locations within a warehouse
        Route::get('/{warehouse}/locations', function (Warehouse $warehouse) {
            return view('warehouses.locations', compact('warehouse'));
        })->name('locations');
        Route::get('/{warehouse}/locations/create', function (Warehouse $warehouse) {
            return view('warehouses.location-create', compact('warehouse'));
        })->name('locations.create');
        Route::get('/{warehouse}/locations/{location}/edit', function (Warehouse $warehouse, Location $location) {
            return view('warehouses.location-edit', compact('warehouse', 'location'));
        })->name('locations.edit');
    });

    // -------------------------------------------------------------------------
    // User Management (Task 004 — Module B) — admin only (enforced in Livewire)
    // -------------------------------------------------------------------------
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', fn () => view('users.index'))->name('index');
        Route::get('/create', fn () => view('users.create'))->name('create');
        Route::get('/{user}/edit', function (User $user) {
            return view('users.edit', compact('user'));
        })->name('edit');
    });
});

require __DIR__.'/settings.php';
