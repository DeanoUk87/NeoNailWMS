<?php

use App\Models\FulfilmentClient;
use App\Models\Product;
use App\Models\ShopifyProductVariant;
use App\Models\User;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Set up a writable fake private disk in the system temp directory.
 * storage/framework/testing/ is not writable in this git repo on Windows.
 */
function fakePrivateDisk(): void
{
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neonailwms_test_private_' . getmypid();
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    config(['filesystems.disks.private' => [
        'driver' => 'local',
        'root'   => $tmpDir,
    ]]);
    // Clear resolved disks so the new config is picked up
    Storage::forgetDisk('private');
}

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper: create a client + user pair
// ---------------------------------------------------------------------------
function makeClientUser(string $role = 'admin'): array
{
    $client = FulfilmentClient::factory()->create();
    $user = User::factory()->create([
        'role'                 => $role,
        'fulfilment_client_id' => $client->id,
    ]);
    return [$client, $user];
}

// ---------------------------------------------------------------------------
// 1. Client separation — operator from client B cannot see client A products
// ---------------------------------------------------------------------------
test('product global scope restricts queries to authenticated user client', function () {
    [$clientA, $userA] = makeClientUser('admin');
    [$clientB, $userB] = makeClientUser('operator');

    // Create product for client A (bypass scope)
    $product = Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientA->id,
        'internal_sku'         => 'SKU-A1',
        'name'                 => 'Client A Product',
        'unit_of_measure'      => 'each',
    ]);

    // Authenticate as client B user — global scope applies
    Auth::login($userB);
    expect(Product::count())->toBe(0); // Client B sees nothing

    // Authenticate as client A — sees their own product
    Auth::login($userA);
    expect(Product::count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 2. Role permissions — operator cannot create or edit products
//    We test via ProductPolicy directly (HTTP tests would require a built
//    Vite manifest; policy tests are faster and more precise here).
// ---------------------------------------------------------------------------
test('product policy denies create for operator', function () {
    [, $operator] = makeClientUser('operator');
    $policy = new \App\Policies\ProductPolicy;
    expect($policy->create($operator))->toBeFalse();
});

test('product policy allows create for supervisor', function () {
    [, $supervisor] = makeClientUser('supervisor');
    $policy = new \App\Policies\ProductPolicy;
    expect($policy->create($supervisor))->toBeTrue();
});

test('product policy allows create for admin', function () {
    [, $admin] = makeClientUser('admin');
    $policy = new \App\Policies\ProductPolicy;
    expect($policy->create($admin))->toBeTrue();
});

// ---------------------------------------------------------------------------
// 3. Uniqueness — internal_sku unique within a client, allowed across clients
// ---------------------------------------------------------------------------
test('same internal_sku is rejected for same client', function () {
    [$clientA] = makeClientUser('admin');

    Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientA->id,
        'internal_sku'         => 'DUPE-001',
        'name'                 => 'First',
        'unit_of_measure'      => 'each',
    ]);

    expect(fn () => Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientA->id,
        'internal_sku'         => 'DUPE-001',
        'name'                 => 'Second',
        'unit_of_measure'      => 'each',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('same internal_sku is allowed for different clients', function () {
    [$clientA] = makeClientUser('admin');
    [$clientB] = makeClientUser('admin');

    Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientA->id,
        'internal_sku'         => 'SHARED-SKU',
        'name'                 => 'Client A version',
        'unit_of_measure'      => 'each',
    ]);

    // Should not throw
    Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientB->id,
        'internal_sku'         => 'SHARED-SKU',
        'name'                 => 'Client B version',
        'unit_of_measure'      => 'each',
    ]);

    expect(Product::withoutGlobalScopes()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// 4. Null manufacturer barcode — allowed, does not conflict
// ---------------------------------------------------------------------------
test('multiple products with null manufacturer barcode are allowed', function () {
    [$client] = makeClientUser('admin');

    Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $client->id,
        'internal_sku'         => 'NO-EAN-1',
        'name'                 => 'Nail file A',
        'unit_of_measure'      => 'each',
        'manufacturer_barcode' => null,
    ]);

    Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $client->id,
        'internal_sku'         => 'NO-EAN-2',
        'name'                 => 'Nail file B',
        'unit_of_measure'      => 'each',
        'manufacturer_barcode' => null,
    ]);

    expect(Product::withoutGlobalScopes()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// 5. Manual override preservation — CSV import must not overwrite manual mapping
// ---------------------------------------------------------------------------
test('csv import does not overwrite a manual variant mapping', function () {
    fakePrivateDisk();
    [$client, $user] = makeClientUser('admin');

    $product = Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $client->id,
        'internal_sku'         => 'MANUAL-SKU',
        'name'                 => 'Manual Product',
        'unit_of_measure'      => 'each',
    ]);

    // Create a manual mapping
    $variant = ShopifyProductVariant::create([
        'fulfilment_client_id' => $client->id,
        'shop_domain'          => 'test.myshopify.com',
        'variant_gid'          => 'gid://shopify/ProductVariant/111',
        'product_id'           => $product->id,
        'mapping_status'       => 'mapped',
        'provenance'           => 'manual',
    ]);

    // Build a CSV that tries to map the same variant_gid to nothing (source_sku missing)
    $csv = "shop_domain,variant_gid,source_sku\ntest.myshopify.com,gid://shopify/ProductVariant/111,DIFFERENT-SKU\n";

    $service = app(ProductImportService::class);
    $import = $service->store($user, 'variant_mappings', 'test.csv', $csv, [
        'shop_domain' => 'shop_domain',
        'variant_gid' => 'variant_gid',
        'source_sku'  => 'source_sku',
    ]);
    $import = $service->confirm($import);

    // Manual mapping must be preserved
    $variant->refresh();
    expect($variant->provenance)->toBe('manual');
    expect($variant->product_id)->toBe($product->id);
    expect($import->skipped_rows)->toBe(1);
});

// ---------------------------------------------------------------------------
// 6. Retry same import — no duplicate products created
// ---------------------------------------------------------------------------
test('retrying the same products csv does not create duplicate products', function () {
    fakePrivateDisk();
    [$client, $user] = makeClientUser('admin');

    $csv = "internal_sku,name,unit_of_measure\nRETRY-001,Retry Product,each\n";
    $mapping = [
        'internal_sku'    => 'internal_sku',
        'name'            => 'name',
        'unit_of_measure' => 'unit_of_measure',
    ];

    $service = app(ProductImportService::class);

    // First import
    $import1 = $service->store($user, 'products', 'retry.csv', $csv, $mapping);
    $service->confirm($import1);

    // Second import (same file)
    $import2 = $service->store($user, 'products', 'retry.csv', $csv, $mapping);
    $service->confirm($import2);

    // Only one product should exist
    expect(Product::withoutGlobalScopes()
        ->where('fulfilment_client_id', $client->id)
        ->where('internal_sku', 'RETRY-001')
        ->count()
    )->toBe(1);
});

// ---------------------------------------------------------------------------
// 7. Conflicting SKU/barcode — same barcode, different SKU goes to quarantine
// ---------------------------------------------------------------------------
test('conflicting manufacturer barcode is quarantined not silently merged', function () {
    fakePrivateDisk();
    [$client, $user] = makeClientUser('admin');

    // First product has barcode 1234567890123
    Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $client->id,
        'internal_sku'         => 'ORIG-SKU',
        'name'                 => 'Original',
        'unit_of_measure'      => 'each',
        'manufacturer_barcode' => '1234567890123',
    ]);

    // CSV imports a DIFFERENT SKU with the same barcode
    $csv = "internal_sku,name,unit_of_measure,manufacturer_barcode\n" .
           "CONFLICT-SKU,Conflicting Product,each,1234567890123\n";

    $service = app(ProductImportService::class);
    $import = $service->store($user, 'products', 'conflict.csv', $csv, [
        'internal_sku'         => 'internal_sku',
        'name'                 => 'name',
        'unit_of_measure'      => 'unit_of_measure',
        'manufacturer_barcode' => 'manufacturer_barcode',
    ]);
    $import = $service->confirm($import);

    // Conflicting row must be in error — the conflicting product must NOT be created
    expect($import->error_rows)->toBe(1);
    expect(Product::withoutGlobalScopes()
        ->where('internal_sku', 'CONFLICT-SKU')
        ->exists()
    )->toBeFalse();
});

// ---------------------------------------------------------------------------
// 8. Ambiguous pack quantity — pack_qty_confirmed is always false after import
// ---------------------------------------------------------------------------
test('imported pack quantity is always marked unverified regardless of csv value', function () {
    fakePrivateDisk();
    [$client, $user] = makeClientUser('admin');

    // CSV provides a pack_qty — simulating item 4504 nail files scenario
    $csv = "internal_sku,name,unit_of_measure,pack_qty\n4504,Nail Files,pack,25\n";

    $service = app(ProductImportService::class);
    $import = $service->store($user, 'products', 'nailfiles.csv', $csv, [
        'internal_sku'    => 'internal_sku',
        'name'            => 'name',
        'unit_of_measure' => 'unit_of_measure',
        'pack_qty'        => 'pack_qty',
    ]);
    $service->confirm($import);

    $product = Product::withoutGlobalScopes()
        ->where('internal_sku', '4504')
        ->where('fulfilment_client_id', $client->id)
        ->first();

    expect($product)->not->toBeNull();
    expect($product->pack_qty)->toBe(25);
    expect($product->pack_qty_confirmed)->toBeFalse(); // Must never be auto-confirmed
    expect($product->packLabel())->toContain('unverified');
});

// ---------------------------------------------------------------------------
// 9. Cross-client product_id — DB-level multi-column FK blocks the association
//    at the database engine, independent of application code.
// ---------------------------------------------------------------------------
test('database rejects variant product_id pointing to a different client product', function () {
    [$clientA] = makeClientUser('admin');
    [$clientB] = makeClientUser('admin');

    // Create a product belonging to Client A
    $productA = Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $clientA->id,
        'internal_sku'         => 'CLIENT-A-PROD',
        'name'                 => 'Client A Product',
        'unit_of_measure'      => 'each',
    ]);

    // Attempt to create a variant for Client B pointing at Client A's product.
    // The multi-column FK (product_id, fulfilment_client_id) -> products(id, fulfilment_client_id)
    // requires both columns to match the same products row.
    // Client B variant (client_id = clientB->id) + productA->id (belongs to clientA) = FK violation.
    expect(fn () => ShopifyProductVariant::create([
        'fulfilment_client_id' => $clientB->id,   // Client B
        'shop_domain'          => 'test.myshopify.com',
        'variant_gid'          => 'gid://shopify/ProductVariant/99999',
        'product_id'           => $productA->id,  // Product belongs to Client A — mismatch
        'mapping_status'       => 'mapped',
        'provenance'           => 'csv_import',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    // Verify the row was NOT written
    expect(
        ShopifyProductVariant::where('variant_gid', 'gid://shopify/ProductVariant/99999')->exists()
    )->toBeFalse();

    // Confirm same-client association still works correctly
    ShopifyProductVariant::create([
        'fulfilment_client_id' => $clientA->id,   // Same client as productA
        'shop_domain'          => 'test.myshopify.com',
        'variant_gid'          => 'gid://shopify/ProductVariant/11111',
        'product_id'           => $productA->id,  // Same client — allowed
        'mapping_status'       => 'mapped',
        'provenance'           => 'csv_import',
    ]);
    expect(
        ShopifyProductVariant::where('variant_gid', 'gid://shopify/ProductVariant/11111')->exists()
    )->toBeTrue();
});
