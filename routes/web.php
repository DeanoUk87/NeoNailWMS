<?php

use App\Http\Controllers\AdminFoundationController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Admin foundation route — accessible only to role=admin (403 for other roles)
    Route::get('/admin/foundation', AdminFoundationController::class)->name('admin.foundation');

    // Warehouse detail route — scoped to authenticated user's fulfilment client
    // Cross-client access returns 404 (global scope hides the record) or 403 (policy check)
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
});

require __DIR__.'/settings.php';
