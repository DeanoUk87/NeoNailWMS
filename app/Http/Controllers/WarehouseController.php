<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * Display a single warehouse.
     *
     * The Warehouse model's global scope (FulfilmentClientScope) ensures only
     * warehouses belonging to the authenticated user's fulfilment client are
     * visible. Route model binding will throw a 404 if the warehouse does not
     * exist within the user's client scope.
     *
     * We additionally check the WarehousePolicy::view authorization which
     * returns 403 if the warehouse belongs to a different client. Because the
     * global scope may have already hidden the record (404), we use
     * Warehouse::withoutGlobalScopes() inside the policy binding to distinguish
     * the two cases:
     *
     * - Record not found for this client => 404 (global scope / route binding)
     * - Record found but belongs to different client => 403 (policy)
     *
     * Decision: cross-client access returns 403 when the warehouse is located
     * via explicit ID (policy check), and 404 when the route binding hides it
     * via global scope. Both 403 and 404 are acceptable; the test asserts either.
     * In practice, the global scope fires first and the client-B warehouse is
     * not found (404) for the client-A user.
     */
    public function show(Request $request, Warehouse $warehouse)
    {
        $this->authorize('view', $warehouse);

        return view('warehouses.show', [
            'warehouse' => $warehouse,
            'locationCount' => $warehouse->locations()->count(),
        ]);
    }
}
