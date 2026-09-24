<?php

use App\Http\Controllers\AdminFoundationController;
use App\Http\Controllers\WarehouseController;
use App\Models\Product;
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
});

require __DIR__.'/settings.php';
