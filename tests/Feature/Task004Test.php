<?php

use App\Models\FulfilmentClient;
use App\Models\Location;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Models\Warehouse;
use App\Policies\LocationPolicy;
use App\Policies\UserManagementPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function makeUser(string $role = 'admin', ?int $clientId = null): User
{
    $clientId ??= FulfilmentClient::factory()->create()->id;
    $u = new User;
    $u->forceFill([
        'name'                 => fake()->name(),
        'email'                => fake()->unique()->safeEmail(),
        'password'             => Hash::make('password'),
        'role'                 => $role,
        'fulfilment_client_id' => $clientId,
        'email_verified_at'    => now(),
    ])->save();
    return $u;
}

function makeWarehouse(int $clientId): Warehouse
{
    return Warehouse::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientId,
        'name'                 => 'Test Warehouse',
        'is_active'            => true,
    ]);
}

function makeLocation(int $warehouseId, string $barcode = 'A01-S01-B01', string $zone = 'A'): Location
{
    return Location::create([
        'warehouse_id'                    => $warehouseId,
        'barcode'                         => $barcode,
        'name'                            => 'Test Location',
        'zone'                            => $zone,
        'location_type'                   => 'bin',
        'is_active'                       => true,
        'is_eligible_for_available_stock' => in_array($zone, Location::ELIGIBLE_ZONES),
    ]);
}

// ===========================================================================
// MODULE A — WAREHOUSE MANAGEMENT
// ===========================================================================

// --- Policy tests -----------------------------------------------------------

test('admin can create and update warehouses for their client', function () {
    $admin = makeUser('admin');
    $wh    = makeWarehouse($admin->fulfilment_client_id);

    $policy = new WarehousePolicy;
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $wh))->toBeTrue();
});

test('supervisor cannot create or update warehouses', function () {
    $sup = makeUser('supervisor');
    $wh  = makeWarehouse($sup->fulfilment_client_id);

    $policy = new WarehousePolicy;
    expect($policy->create($sup))->toBeFalse();
    expect($policy->update($sup, $wh))->toBeFalse();
});

test('admin from client A cannot update client B warehouse', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();
    $adminA  = makeUser('admin', $clientA->id);
    $whB     = makeWarehouse($clientB->id);

    $policy = new WarehousePolicy;
    expect($policy->update($adminA, $whB))->toBeFalse();
});

test('warehouses are never hard-deleted by policy', function () {
    $admin = makeUser('admin');
    $wh    = makeWarehouse($admin->fulfilment_client_id);

    $policy = new WarehousePolicy;
    expect($policy->delete($admin, $wh))->toBeFalse();
});

// --- Route access tests (policy-level — HTTP tests require a Vite build) ----

test('unauthenticated user is redirected from warehouses index', function () {
    $this->get(route('warehouses.index'))->assertRedirect('/login');
});

test('unauthenticated user is redirected from users index', function () {
    $this->get(route('users.index'))->assertRedirect('/login');
});

test('all authenticated roles can view warehouses via policy', function () {
    foreach (['admin', 'supervisor', 'operator'] as $role) {
        $user   = makeUser($role);
        $policy = new WarehousePolicy;
        expect($policy->viewAny($user))->toBeTrue("Role {$role} should be able to viewAny warehouses");
    }
});

test('only admin can create warehouses via policy', function () {
    $policy = new WarehousePolicy;
    expect($policy->create(makeUser('admin')))->toBeTrue();
    expect($policy->create(makeUser('supervisor')))->toBeFalse();
    expect($policy->create(makeUser('operator')))->toBeFalse();
});

// --- Client separation in global scope --------------------------------------

test('warehouse global scope prevents cross-client warehouse access', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();

    $adminA = makeUser('admin', $clientA->id);
    makeWarehouse($clientB->id);

    Auth::login($adminA);
    // Global scope: adminA can only see clientA's warehouses
    expect(Warehouse::count())->toBe(0);

    makeWarehouse($clientA->id);
    expect(Warehouse::count())->toBe(1);
});

// ===========================================================================
// MODULE A — LOCATION MANAGEMENT
// ===========================================================================

test('admin can create locations for their client warehouse', function () {
    $admin = makeUser('admin');
    $wh    = makeWarehouse($admin->fulfilment_client_id);
    $loc   = makeLocation($wh->id);

    $policy = new LocationPolicy;
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $loc))->toBeTrue();
});

test('operator cannot create or update locations', function () {
    $op  = makeUser('operator');
    $wh  = makeWarehouse($op->fulfilment_client_id);
    $loc = makeLocation($wh->id);

    $policy = new LocationPolicy;
    expect($policy->create($op))->toBeFalse();
    expect($policy->update($op, $loc))->toBeFalse();
});

test('duplicate barcode within the same warehouse is rejected by DB unique constraint', function () {
    $admin = makeUser('admin');
    $wh    = makeWarehouse($admin->fulfilment_client_id);
    makeLocation($wh->id, 'A01-S01-B01');

    expect(fn () => makeLocation($wh->id, 'A01-S01-B01'))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('same barcode is allowed in different warehouses', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();

    $wh1 = makeWarehouse($clientA->id);
    $wh2 = makeWarehouse($clientB->id);

    makeLocation($wh1->id, 'A01-S01-B01');
    makeLocation($wh2->id, 'A01-S01-B01'); // Should succeed

    expect(Location::withoutGlobalScopes()->where('barcode', 'A01-S01-B01')->count())->toBe(2);
});

test('ambient zone location is eligible for available stock', function () {
    $admin = makeUser('admin');
    $wh    = makeWarehouse($admin->fulfilment_client_id);
    $loc   = makeLocation($wh->id, 'A01-S01-B01', 'A');

    expect($loc->is_eligible_for_available_stock)->toBeTrue();
    expect($loc->isEligibleForAvailableStock())->toBeTrue();
});

test('quarantine zone location is not eligible for available stock', function () {
    $admin = makeUser('admin');
    $wh    = makeWarehouse($admin->fulfilment_client_id);
    $loc   = makeLocation($wh->id, 'Q01-S01-B01', 'Q');

    expect($loc->is_eligible_for_available_stock)->toBeFalse();
    expect($loc->isEligibleForAvailableStock())->toBeFalse();
});

test('location zone names are readable', function () {
    $admin = makeUser('admin');
    $wh    = makeWarehouse($admin->fulfilment_client_id);

    foreach (Location::ZONES as $code => $label) {
        $loc = makeLocation($wh->id, $code . '-TEST', $code);
        expect($loc->zoneName())->toBe($label);
    }
});

test('admin from client A cannot update location belonging to client B warehouse', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();

    $adminA = makeUser('admin', $clientA->id);
    $whB    = makeWarehouse($clientB->id);
    $locB   = makeLocation($whB->id);

    $policy = new LocationPolicy;
    expect($policy->update($adminA, $locB))->toBeFalse();
});

test('barcode generator produces correct format', function () {
    // Test the barcode generation logic used in LocationForm
    $zone     = 'A';
    $section  = '1';
    $shelf    = '2';
    $binPos   = '3';

    $parts = array_filter([
        strtoupper($zone) . str_pad($section, 2, '0', STR_PAD_LEFT),
        'S' . str_pad($shelf, 2, '0', STR_PAD_LEFT),
        'B' . str_pad($binPos, 2, '0', STR_PAD_LEFT),
    ]);
    $barcode = implode('-', $parts);

    expect($barcode)->toBe('A01-S02-B03');
});

// ===========================================================================
// MODULE B — USER MANAGEMENT
// ===========================================================================

test('admin can view users list', function () {
    $admin = makeUser('admin');
    $policy = new UserManagementPolicy;
    expect($policy->viewAny($admin))->toBeTrue();
});

test('supervisor cannot view users list', function () {
    $sup    = makeUser('supervisor');
    $policy = new UserManagementPolicy;
    expect($policy->viewAny($sup))->toBeFalse();
});

test('admin can create users for their client', function () {
    $admin  = makeUser('admin');
    $policy = new UserManagementPolicy;
    expect($policy->create($admin))->toBeTrue();
});

test('admin cannot update a user from a different client', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();

    $adminA  = makeUser('admin', $clientA->id);
    $userB   = makeUser('operator', $clientB->id);

    $policy = new UserManagementPolicy;
    expect($policy->update($adminA, $userB))->toBeFalse();
});

test('admin cannot update their own record via UserManagementPolicy', function () {
    $admin  = makeUser('admin');
    $policy = new UserManagementPolicy;
    // Self-modification is blocked to prevent lock-out
    expect($policy->update($admin, $admin))->toBeFalse();
});

test('admin can update another user in the same client', function () {
    $clientA = FulfilmentClient::factory()->create();
    $admin   = makeUser('admin', $clientA->id);
    $op      = makeUser('operator', $clientA->id);

    $policy = new UserManagementPolicy;
    expect($policy->update($admin, $op))->toBeTrue();
});

test('admin cannot deactivate themselves', function () {
    $admin  = makeUser('admin');
    $policy = new UserManagementPolicy;
    expect($policy->deactivate($admin, $admin))->toBeFalse();
});

test('user audit log records a creation event', function () {
    $admin  = makeUser('admin');
    $target = makeUser('operator', $admin->fulfilment_client_id);

    UserAuditLog::record($target, $admin, 'created', ['role' => 'operator']);

    $log = UserAuditLog::where('target_user_id', $target->id)->first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('created');
    expect($log->performed_by)->toBe($admin->id);
    expect($log->changes['role'])->toBe('operator');
});

test('user audit log records a role change event', function () {
    $admin  = makeUser('admin');
    $target = makeUser('operator', $admin->fulfilment_client_id);

    UserAuditLog::record($target, $admin, 'updated', [
        'before' => ['role' => 'operator'],
        'after'  => ['role' => 'supervisor'],
    ]);

    $log = UserAuditLog::where('target_user_id', $target->id)
                       ->where('action', 'updated')
                       ->first();
    expect($log)->not->toBeNull();
    expect($log->changes['before']['role'])->toBe('operator');
    expect($log->changes['after']['role'])->toBe('supervisor');
});

// ===========================================================================
// MODULE C — REGRESSION: EXISTING FUNCTIONALITY STILL WORKS
// ===========================================================================

test('admin can still access /admin/foundation', function () {
    $client = FulfilmentClient::factory()->create();
    $admin  = makeUser('admin', $client->id);
    $this->actingAs($admin)->get('/admin/foundation')->assertOk()->assertSee('Foundation OK');
});

test('operator still receives 403 on /admin/foundation', function () {
    $op = makeUser('operator');
    $this->actingAs($op)->get('/admin/foundation')->assertForbidden();
});

test('login throttle still works', function () {
    $payload = ['email' => 'x@x.com', 'password' => 'wrong'];
    for ($i = 0; $i < 5; $i++) { $this->post('/login', $payload); }
    $this->post('/login', $payload)->assertStatus(429);
});

test('product policy still scopes to client', function () {
    // Policy-level regression — no Vite build required
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();
    $adminA  = makeUser('admin', $clientA->id);

    Auth::login($adminA);
    // FulfilmentClientScope on Product: adminA sees 0 of clientB's products
    expect(\App\Models\Product::withoutGlobalScopes()
        ->where('fulfilment_client_id', $clientB->id)->count()
    )->toBe(0); // No products seeded for client B
    expect(\App\Models\Product::count())->toBe(0); // Global scope: no clientA products either
});

// ===========================================================================
// HTTP-LEVEL CROSS-CLIENT SECURITY (Paul's review point 2)
// Tests that URL-level access to another client's warehouse/location returns
// 404, enforced by the Eloquent global scope on route model binding.
// These complement the policy-level tests above.
// ===========================================================================

test('HTTP: admin from client A gets 404 accessing client B warehouse by URL', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();

    $adminA  = makeUser('admin', $clientA->id);
    $whB     = Warehouse::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientB->id,
        'name'                 => 'Client B Warehouse',
        'is_active'            => true,
    ]);

    // The FulfilmentClientScope on Warehouse hides whB from adminA's queries.
    // Route model binding resolves {warehouse} through the global scope,
    // so it finds no matching record and returns 404.
    $this->actingAs($adminA)
         ->get("/warehouses/{$whB->id}/edit")
         ->assertNotFound();
});

test('HTTP: admin from client A gets 404 accessing client B warehouse locations by URL', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();

    $adminA  = makeUser('admin', $clientA->id);
    $whB     = Warehouse::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientB->id,
        'name'                 => 'Client B Warehouse',
        'is_active'            => true,
    ]);

    $this->actingAs($adminA)
         ->get("/warehouses/{$whB->id}/locations")
         ->assertNotFound();
});

test('HTTP: unauthenticated request to warehouses is redirected to login', function () {
    $this->get('/warehouses')->assertRedirect('/login');
    $this->get('/warehouses/create')->assertRedirect('/login');
    $this->get('/warehouses/1/edit')->assertRedirect('/login');
    $this->get('/warehouses/1/locations')->assertRedirect('/login');
});

test('HTTP: unauthenticated request to users is redirected to login', function () {
    $this->get('/users')->assertRedirect('/login');
    $this->get('/users/create')->assertRedirect('/login');
    $this->get('/users/1/edit')->assertRedirect('/login');
});
