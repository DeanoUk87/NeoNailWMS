<?php

use App\Models\FulfilmentClient;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin user can access /admin/foundation', function () {
    $client = FulfilmentClient::factory()->create();

    $admin = User::factory()->create([
        'role' => 'admin',
        'fulfilment_client_id' => $client->id,
    ]);

    $response = $this->actingAs($admin)->get('/admin/foundation');

    $response->assertStatus(200);
    $response->assertSee('Foundation OK');
    $response->assertSee($admin->email);
});

test('operator user receives 403 on /admin/foundation', function () {
    $client = FulfilmentClient::factory()->create();

    $operator = User::factory()->create([
        'role' => 'operator',
        'fulfilment_client_id' => $client->id,
    ]);

    $response = $this->actingAs($operator)->get('/admin/foundation');

    $response->assertStatus(403);
});

test('admin from client A cannot access client B warehouse', function () {
    $clientA = FulfilmentClient::factory()->create();
    $clientB = FulfilmentClient::factory()->create();

    $adminA = User::factory()->create([
        'role' => 'admin',
        'fulfilment_client_id' => $clientA->id,
    ]);

    // Create warehouse for client B — bypass global scope so factory can insert freely
    $warehouseB = Warehouse::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientB->id,
        'name' => 'Client B Warehouse',
    ]);

    // The Phase 1 /warehouses/{warehouse} show route is superseded by Task 004.
    // Cross-client isolation is now enforced at policy level (also tested in Task004Test).
    // Verify via policy: admin A cannot view or update warehouse B.
    $policy = new \App\Policies\WarehousePolicy;
    expect($policy->view($adminA, $warehouseB))->toBeFalse();
    expect($policy->update($adminA, $warehouseB))->toBeFalse();

    // Also verify via global scope: admin A sees 0 warehouses (client B's are hidden)
    \Illuminate\Support\Facades\Auth::login($adminA);
    expect(Warehouse::count())->toBe(0);
});

test('login is throttled after repeated failures', function () {
    // The FortifyServiceProvider configures login rate limiting at 5 per minute.
    // Attempt login 6 times (threshold + 1) to trigger the 429 response.
    $throttleLimit = 5; // Limit::perMinute(5) in FortifyServiceProvider::configureRateLimiting()

    $payload = [
        'email' => 'nonexistent@example.com',
        'password' => 'wrong-password',
    ];

    for ($i = 0; $i < $throttleLimit; $i++) {
        $this->post('/login', $payload);
    }

    // The (throttleLimit + 1)th attempt should be throttled
    $response = $this->post('/login', $payload);

    $response->assertStatus(429);
});
