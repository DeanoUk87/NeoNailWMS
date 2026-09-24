<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminFoundationController extends Controller
{
    /**
     * Display the admin foundation page.
     *
     * Only accessible to authenticated users with role=admin.
     * Returns 403 for authenticated users with any other role.
     */
    public function __invoke(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Admin access required.');
        }

        return view('admin.foundation', [
            'userEmail' => $request->user()->email,
            'appName' => config('app.name'),
        ]);
    }
}
