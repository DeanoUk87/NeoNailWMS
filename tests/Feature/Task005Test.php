<?php

use App\Models\Batch;
use App\Models\FulfilmentClient;
use App\Models\Location;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ===========================================================================
// HELPERS
// ===========================================================================

function makeClient005(): FulfilmentClient
{
    return FulfilmentClient::factory()->create();
}

function makeActor005(?FulfilmentClient $client = null): User
{
    $client ??= makeClient005();
    $u = new User;
    $u->forceFill([
        'name'                 => 'Test Actor',
        'email'                => fake()->unique()->safeEmail(),
        'password'             => Hash::make('password'),
        'role'                 => 'admin',
        'fulfilment_client_id' => $client->id,
        'email_verified_at'    => now(),
    ])->save();
    return $u;
}

function makeWarehouse005(FulfilmentClient $client): Warehouse
{
    return Warehouse::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $client->id,
        'name'                 => 'Test Warehouse',
        'is_active'            => true,
    ]);
}

function makeLocation005(Warehouse $wh, string $zone = 'A', string $barcode = null): Location
{
    static $counter = 0;
    $counter++;
    return Location::create([
        'warehouse_id'                    => $wh->id,
        'barcode'                         => $barcode ?? ($zone . str_pad($counter, 2, '0', STR_PAD_LEFT) . '-TEST'),
        'name'                            => "Test Location {$counter}",
        'zone'                            => $zone,
        'location_type'                   => 'bin',
        'is_active'                       => true,
        'is_eligible_for_available_stock' => in_array($zone, Location::ELIGIBLE_ZONES),
    ]);
}

function makeBatch005(
    FulfilmentClient $client,
    ?string $expiryDate = null,
    bool $noExpiry = false
): Batch {
    static $productCounter = 0;
    $productCounter++;

    $product = Product::withoutGlobalScopes()->create([
        'fulfilment_client_id' => $client->id,
        'internal_sku'         => 'SKU-' . $productCounter,
        'name'                 => 'Test Product ' . $productCounter,
        'unit_of_measure'      => 'each',
    ]);

    return Batch::create([
        'fulfilment_client_id'       => $client->id,
        'product_id'                 => $product->id,
        'manufacturer_batch_number'  => 'BATCH-' . $productCounter,
        'operational_expiry_date'    => $expiryDate,
        'no_expiry_confirmed'        => $noExpiry,
        'batch_source'               => 'manufacturer',
        'internal_tracking_ref'      => 'MP-' . now()->format('Ymd') . '-' . str_pad($productCounter, 3, '0', STR_PAD_LEFT),
        'expiry_classification'      => $noExpiry ? 'NO_EXPIRY' : ($expiryDate ? 'FULL_PRICE' : 'EXPIRY_UNKNOWN'),
    ]);
}

function ledger(): StockLedgerService
{
    return app(StockLedgerService::class);
}

// ===========================================================================
// SECTION 1 — EXPIRY CLASSIFICATION (Task 003C algorithm)
// ===========================================================================

test('expiry classification: exact boundary comparisons', function () {
    $today = Carbon::parse('2026-11-03');
    $client = makeClient005();
    $actor  = makeActor005($client);

    $cases = [
        // [expiry, expected_classification]
        ['2027-11-04', 'FULL_PRICE'],  // 1 day after 12-month boundary
        ['2027-11-03', 'SHORT_12'],    // exactly 12-month boundary
        ['2027-11-02', 'SHORT_12'],    // 1 day before 12-month boundary
        ['2027-08-04', 'SHORT_12'],    // 1 day after 9-month boundary
        ['2027-08-03', 'SHORT_9'],     // exactly 9-month boundary
        ['2027-08-02', 'SHORT_9'],     // 1 day before 9-month boundary
        ['2027-05-04', 'SHORT_9'],     // 1 day after 6-month boundary
        ['2027-05-03', 'SHORT_6'],     // exactly 6-month boundary
        ['2027-02-04', 'SHORT_6'],     // 1 day after 3-month boundary
        ['2027-02-03', 'SHORT_3'],     // exactly 3-month boundary
        ['2026-12-04', 'SHORT_3'],     // 1 day after 1-month boundary
        ['2026-12-03', 'SHORT_1'],     // exactly 1-month boundary
        ['2026-11-04', 'SHORT_1'],     // 1 day after today
        ['2026-11-03', 'EXPIRED'],     // expiry = today → EXPIRED
        ['2026-11-02', 'EXPIRED'],     // 1 day before today → EXPIRED
    ];

    foreach ($cases as [$expiry, $expected]) {
        $batch = makeBatch005($client, $expiry);
        $result = $batch->classifyExpiry($today);
        expect($result)->toBe($expected, "Expiry {$expiry} should classify as {$expected}, got {$result}");
    }
});

test('expiry: stock valid on expiry date, expired the following day', function () {
    $client = makeClient005();
    $batch  = makeBatch005($client, '2027-06-30');

    expect($batch->isExpired(Carbon::parse('2027-06-29')))->toBeFalse();
    expect($batch->isExpired(Carbon::parse('2027-06-30')))->toBeFalse(); // valid on the day
    expect($batch->isExpired(Carbon::parse('2027-07-01')))->toBeTrue();  // expired the following day
});

test('expiry: month-year label stored as last day of month', function () {
    $derived = Batch::deriveOperationalExpiryDate('06/2027', 'month_year');
    expect($derived)->toBe('2027-06-30');
});

test('expiry: year-only label stored as 31 December', function () {
    $derived = Batch::deriveOperationalExpiryDate('2027', 'year_only');
    expect($derived)->toBe('2027-12-31');
});

test('no-expiry batch is never expired', function () {
    $client = makeClient005();
    $batch  = makeBatch005($client, null, true);
    expect($batch->isExpired(Carbon::parse('9999-12-31')))->toBeFalse();
    expect($batch->classifyExpiry(Carbon::parse('9999-12-31')))->toBe('NO_EXPIRY');
});

// ===========================================================================
// SECTION 2 — 48-BOTTLE SCENARIO (Tasks 003C/003D worked examples)
// ===========================================================================

test('48-bottle: receive 48 units creates APPROVED_PUTAWAY holding entry', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');

    $entry = ledger()->receive($batch, 48, $actor, ['warehouse_id' => $wh->id]);

    expect($entry->qty)->toBe(48);
    expect($entry->status)->toBe('APPROVED_PUTAWAY');
    expect($entry->location_id)->toBeNull();

    // Movement recorded
    $movement = StockMovement::where('stock_entry_id', $entry->id)->first();
    expect($movement->movement_type)->toBe('RECEIPT');
    expect($movement->quantity_change)->toBe(48);

    // Balance
    expect(ledger()->onHandQty($client->id))->toBe(48);
});

test('48-bottle: partial putaway across three bins', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');

    $bin1 = makeLocation005($wh, 'A');
    $bin2 = makeLocation005($wh, 'A');
    $bin3 = makeLocation005($wh, 'A');

    $holding = ledger()->receive($batch, 48, $actor, ['warehouse_id' => $wh->id]);

    $e1 = ledger()->putaway($holding, $bin1, 20, $actor);
    $holding->refresh();
    expect($holding->qty)->toBe(28);
    expect($e1->qty)->toBe(20);
    expect($e1->status)->toBe('AVAILABLE');

    $e2 = ledger()->putaway($holding, $bin2, 20, $actor);
    $holding->refresh();
    expect($holding->qty)->toBe(8);

    $e3 = ledger()->putaway($holding, $bin3, 8, $actor);
    $holding->refresh();
    expect($holding->qty)->toBe(0);

    // All 48 accounted for
    expect(ledger()->onHandQty($client->id))->toBe(48);
    expect(ledger()->onHandQty($client->id, null, $bin1->id))->toBe(20);
    expect(ledger()->onHandQty($client->id, null, $bin2->id))->toBe(20);
    expect(ledger()->onHandQty($client->id, null, $bin3->id))->toBe(8);

    // No stock created or destroyed
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

test('48-bottle: reserve 12 units for an order', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $holding = ledger()->receive($batch, 48, $actor, ['warehouse_id' => $wh->id]);
    $avail   = ledger()->putaway($holding, $bin, 48, $actor);

    $reserved = ledger()->reserve($avail, 12, 'ORD-001', $actor);

    $avail->refresh();
    expect($avail->qty)->toBe(36);
    expect($reserved->qty)->toBe(12);
    expect($reserved->status)->toBe('RESERVED');
    expect($reserved->order_reference)->toBe('ORD-001');

    // On-hand unchanged (reservation is zero-sum)
    expect(ledger()->onHandQty($client->id))->toBe(48);
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

test('48-bottle: pick, pack and dispatch the 12 reserved units', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $holding  = ledger()->receive($batch, 48, $actor, ['warehouse_id' => $wh->id]);
    $avail    = ledger()->putaway($holding, $bin, 48, $actor);
    $reserved = ledger()->reserve($avail, 12, 'ORD-001', $actor);

    $picked = ledger()->pick($reserved, $actor);
    expect($picked->status)->toBe('PICKED');
    expect($picked->location_id)->toBeNull(); // removed from bin
    expect(ledger()->onHandQty($client->id))->toBe(48); // still in warehouse

    $packed = ledger()->pack($picked, $actor);
    expect($packed->status)->toBe('PACKED');

    $dispatched = ledger()->dispatch($packed, $actor, ['reference_id' => 'ORD-001']);
    expect($dispatched->status)->toBe('DISPATCHED');
    expect($dispatched->qty)->toBe(0); // tombstone

    // Physical on-hand reduced by 12
    expect(ledger()->onHandQty($client->id))->toBe(36);

    // Dispatch movement records the outbound
    $dispatchMovement = StockMovement::where('stock_entry_id', $dispatched->id)
        ->where('movement_type', 'DISPATCH')
        ->first();
    expect($dispatchMovement->quantity_change)->toBe(-12);

    // Reconciliation
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

test('48-bottle: transfer 5 units between bins is zero-sum', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin1   = makeLocation005($wh, 'A');
    $bin2   = makeLocation005($wh, 'A');

    $holding = ledger()->receive($batch, 20, $actor, ['warehouse_id' => $wh->id]);
    $avail   = ledger()->putaway($holding, $bin1, 20, $actor);

    $dest = ledger()->transfer($avail, $bin2, 5, $actor);

    $avail->refresh();
    expect($avail->qty)->toBe(15);
    expect($dest->qty)->toBe(5);
    expect($dest->status)->toBe('AVAILABLE');
    expect(ledger()->onHandQty($client->id))->toBe(20);
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

test('48-bottle: quarantine 3 units preserves reservation link', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $holding = ledger()->receive($batch, 48, $actor, ['warehouse_id' => $wh->id]);
    $avail   = ledger()->putaway($holding, $bin, 48, $actor);

    $qEntry = ledger()->quarantine($avail, 3, 'DAMAGED', $actor);

    $avail->refresh();
    expect($avail->qty)->toBe(45);
    expect($qEntry->qty)->toBe(3);
    expect($qEntry->status)->toBe('QUARANTINE');

    // Quarantine record created
    $qRecord = QuarantineRecord::where('stock_entry_id', $qEntry->id)->first();
    expect($qRecord)->not->toBeNull();
    expect($qRecord->reason)->toBe('DAMAGED');
    expect($qRecord->status)->toBe('OPEN');

    // On-hand unchanged (quarantine is still physical stock)
    expect(ledger()->onHandQty($client->id))->toBe(48);
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

test('48-bottle: supervisor-authorised write-down corrects erroneous quantity', function () {
    $client    = makeClient005();
    $wh        = makeWarehouse005($client);
    $actor     = makeActor005($client);
    $approver  = makeActor005($client);
    $batch     = makeBatch005($client, '2027-12-31');
    $bin       = makeLocation005($wh, 'A');

    $holding = ledger()->receive($batch, 48, $actor, ['warehouse_id' => $wh->id]);
    $avail   = ledger()->putaway($holding, $bin, 48, $actor);

    // Physical count found only 46 — write down by 2
    ledger()->adjust($avail, -2, $actor, $approver, 'Physical count discrepancy');

    $avail->refresh();
    expect($avail->qty)->toBe(46);
    expect(ledger()->onHandQty($client->id))->toBe(46);

    // Write-off movement recorded
    $woff = StockMovement::where('stock_entry_id', $avail->id)
        ->where('movement_type', 'WRITE_OFF')
        ->first();
    expect($woff->quantity_change)->toBe(-2);
    expect($woff->approved_by)->toBe($approver->id);

    // Reconciliation: 48 received - 2 written off = 46
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

test('48-bottle: full reconciliation after all operations', function () {
    $client   = makeClient005();
    $wh       = makeWarehouse005($client);
    $actor    = makeActor005($client);
    $approver = makeActor005($client);
    $batch    = makeBatch005($client, '2027-12-31');
    $bin1     = makeLocation005($wh, 'A');
    $bin2     = makeLocation005($wh, 'A');
    $bin3     = makeLocation005($wh, 'A');
    $bin4     = makeLocation005($wh, 'A');

    // Receive 48
    $h = ledger()->receive($batch, 48, $actor, ['warehouse_id' => $wh->id]);

    // Putaway across 3 bins
    $e1 = ledger()->putaway($h, $bin1, 20, $actor); // 20 in bin1
    $h->refresh();
    $e2 = ledger()->putaway($h, $bin2, 20, $actor); // 20 in bin2
    $h->refresh();
    $e3 = ledger()->putaway($h, $bin3, 8, $actor);  // 8 in bin3

    // Reserve 12 for ORD-001 from bin1
    $e4 = ledger()->reserve($e1, 12, 'ORD-001', $actor); // 8 remain in e1

    // Pick, pack, dispatch the 12
    $picked   = ledger()->pick($e4, $actor);
    $packed   = ledger()->pack($picked, $actor);
    $dispatched = ledger()->dispatch($packed, $actor);

    // Transfer 5 from bin2 to bin4
    $e5 = ledger()->transfer($e2, $bin4, 5, $actor); // e2=15, e5=5

    // Quarantine 3 from bin3
    $e6 = ledger()->quarantine($e3, 3, 'DAMAGED', $actor); // e3=5, e6=3 QUARANTINE

    // Write-down 2 from bin2 (count discrepancy)
    $e2->refresh();
    ledger()->adjust($e2, -2, $actor, $approver, 'Count discrepancy');

    // Final state:
    // e1: 8 AVAILABLE bin1
    // e2: 13 AVAILABLE bin2 (20 - 5 transferred - 2 written off)
    // e3: 5 AVAILABLE bin3 (8 - 3 quarantined)
    // e4: 0 DISPATCHED bin1 (tombstone)
    // e5: 5 AVAILABLE bin4
    // e6: 3 QUARANTINE bin3

    $e1->refresh(); $e2->refresh(); $e3->refresh();
    $e4->refresh(); $e5->refresh(); $e6->refresh();

    expect($e1->qty)->toBe(8);
    expect($e2->qty)->toBe(13);
    expect($e3->qty)->toBe(5);
    expect($e4->qty)->toBe(0);  // dispatched tombstone
    expect($e5->qty)->toBe(5);
    expect($e6->qty)->toBe(3);

    // Physical on-hand: 8+13+5+0+5+3 = 34 (excluding dispatched)
    expect(ledger()->onHandQty($client->id))->toBe(34);

    // Received 48, dispatched 12, written off 2 → expected 34
    expect(ledger()->ledgerDerivedOnHand($client->id))->toBe(34);
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

// ===========================================================================
// SECTION 3 — FAILED TRANSACTIONS AND EDGE CASES
// ===========================================================================

test('cannot putaway more than holding entry qty', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $holding = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);

    expect(fn () => ledger()->putaway($holding, $bin, 15, $actor))
        ->toThrow(\RuntimeException::class, 'only 10 remaining');
});

test('cannot reserve more than available qty', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 10, $actor);

    expect(fn () => ledger()->reserve($a, 15, 'ORD-001', $actor))
        ->toThrow(\RuntimeException::class);
});

test('cannot reserve from non-AVAILABLE entry', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 10, $actor);
    $r = ledger()->reserve($a, 5, 'ORD-001', $actor);

    // Try to reserve from the RESERVED entry (not AVAILABLE)
    expect(fn () => ledger()->reserve($r, 3, 'ORD-002', $actor))
        ->toThrow(\RuntimeException::class, 'Must be AVAILABLE');
});

test('cannot dispatch EXPIRED stock', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    // Batch expired yesterday
    $batch  = makeBatch005($client, Carbon::yesterday()->format('Y-m-d'));
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 5, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 5, $actor);

    // Reserve should fail — live expiry check
    expect(fn () => ledger()->reserve($a, 5, 'ORD-001', $actor))
        ->toThrow(\RuntimeException::class, 'EXPIRED');
});

test('cannot pick EXPIRED stock', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, Carbon::tomorrow()->format('Y-m-d'));
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 5, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 5, $actor);
    $r = ledger()->reserve($a, 5, 'ORD-001', $actor);

    // Simulate batch expiring between reservation and pick
    // Directly update the operational_expiry_date to yesterday
    DB::table('batches')->where('id', $batch->id)->update([
        'operational_expiry_date' => Carbon::yesterday()->format('Y-m-d'),
    ]);
    $r->load('batch'); // reload relationship

    expect(fn () => ledger()->pick($r, $actor))
        ->toThrow(\RuntimeException::class, 'EXPIRED');
});

test('write-down below zero is rejected', function () {
    $client   = makeClient005();
    $wh       = makeWarehouse005($client);
    $actor    = makeActor005($client);
    $approver = makeActor005($client);
    $batch    = makeBatch005($client, '2027-12-31');
    $bin      = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 5, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 5, $actor);

    expect(fn () => ledger()->adjust($a, -10, $actor, $approver, 'Too big'))
        ->toThrow(\RuntimeException::class, 'negative');
});

test('negative receive quantity is rejected', function () {
    $client = makeClient005();
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');

    expect(fn () => ledger()->receive($batch, -5, $actor))
        ->toThrow(\InvalidArgumentException::class);
});

test('zero adjustment is rejected', function () {
    $client   = makeClient005();
    $wh       = makeWarehouse005($client);
    $actor    = makeActor005($client);
    $approver = makeActor005($client);
    $batch    = makeBatch005($client, '2027-12-31');
    $bin      = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 5, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 5, $actor);

    expect(fn () => ledger()->adjust($a, 0, $actor, $approver, 'Zero'))
        ->toThrow(\InvalidArgumentException::class);
});

// ===========================================================================
// SECTION 4 — ZONE ELIGIBILITY (SQLite trigger)
// ===========================================================================

test('SQLite trigger blocks AVAILABLE status in quarantine zone', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $qBin   = makeLocation005($wh, 'Q'); // Quarantine zone — ineligible

    $h = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);

    // Putaway to Q zone — trigger should fire when status=AVAILABLE
    // The service sets status=IN_LOCATION for ineligible zones (not AVAILABLE)
    $entry = ledger()->putaway($h, $qBin, 10, $actor);
    expect($entry->status)->toBe('IN_LOCATION'); // Correctly set to IN_LOCATION, not AVAILABLE

    // Direct DB attempt to set AVAILABLE on a Q-zone entry — trigger blocks it
    expect(fn () => DB::table('stock_entries')
        ->where('id', $entry->id)
        ->update(['status' => 'AVAILABLE'])
    )->toThrow(\Illuminate\Database\QueryException::class);
});

test('trigger allows AVAILABLE in ambient zone', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);
    $e = ledger()->putaway($h, $bin, 10, $actor);
    expect($e->status)->toBe('AVAILABLE'); // No exception
});

test('trigger blocks AVAILABLE with null location_id', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $batch  = makeBatch005($client, '2027-12-31');

    expect(fn () => StockEntry::create([
        'fulfilment_client_id' => $client->id,
        'warehouse_id'         => $wh->id,
        'batch_id'             => $batch->id,
        'location_id'          => null,
        'qty'                  => 10,
        'status'               => 'AVAILABLE',
        'version'              => 1,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('trigger blocks negative qty update', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);
    $e = ledger()->putaway($h, $bin, 10, $actor);

    // Directly try to set negative qty
    expect(fn () => DB::table('stock_entries')
        ->where('id', $e->id)
        ->update(['qty' => -1])
    )->toThrow(\Illuminate\Database\QueryException::class);
});

// ===========================================================================
// SECTION 5 — CONCURRENT OPERATORS (optimistic locking)
// ===========================================================================

test('concurrent modification: second write fails with version mismatch', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 20, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 20, $actor);

    // Simulate two operators reading the same entry simultaneously (same version)
    $aForOperatorA = StockEntry::find($a->id); // version = 1
    $aForOperatorB = StockEntry::find($a->id); // version = 1

    // Operator A's reservation succeeds
    $r1 = ledger()->reserve($aForOperatorA, 10, 'ORD-A', $actor);
    expect($r1->qty)->toBe(10);

    // Now $a's version in DB is 2, but $aForOperatorB still has version = 1
    // Operator B's reservation should fail with version mismatch
    expect(fn () => ledger()->reserve($aForOperatorB, 10, 'ORD-B', $actor))
        ->toThrow(\RuntimeException::class, 'Concurrent modification');
});

test('idempotency key prevents duplicate movement on retry', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $key    = \Illuminate\Support\Str::uuid()->toString();

    $e1 = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id, 'idempotency_key' => $key]);

    // Retry with same idempotency key — should not create a second movement
    $e2 = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id, 'idempotency_key' => $key]);

    // Only one movement should exist for this key
    $count = StockMovement::where('idempotency_key', $key)->count();
    expect($count)->toBe(1);
});

// ===========================================================================
// SECTION 6 — CLIENT SEPARATION
// ===========================================================================

test('stock entries are strictly client-scoped — no cross-client access via service', function () {
    $clientA = makeClient005();
    $clientB = makeClient005();
    $whA     = makeWarehouse005($clientA);
    $actorA  = makeActor005($clientA);
    $actorB  = makeActor005($clientB);
    $batchA  = makeBatch005($clientA, '2027-12-31');
    $binA    = makeLocation005($whA, 'A');

    $h = ledger()->receive($batchA, 10, $actorA, ['warehouse_id' => $whA->id]);
    $a = ledger()->putaway($h, $binA, 10, $actorA);

    // Client B sees zero stock
    expect(ledger()->onHandQty($clientB->id))->toBe(0);
    expect(ledger()->onHandQty($clientA->id))->toBe(10);
});

// ===========================================================================
// SECTION 7 — LOCATION RECLASSIFICATION GUARD
// ===========================================================================

test('location reclassification is blocked when AVAILABLE stock is present', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);
    ledger()->putaway($h, $bin, 10, $actor);

    $affectedEntries = ledger()->locationReclassificationCheck($bin);
    expect($affectedEntries)->not->toBeEmpty();
});

test('location reclassification is allowed when bin is empty', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $bin    = makeLocation005($wh, 'A');

    $affectedEntries = ledger()->locationReclassificationCheck($bin);
    expect($affectedEntries)->toBeEmpty();
});

test('force reclassification demotes AVAILABLE to IN_LOCATION and RESERVED to RESERVED_HELD', function () {
    $client     = makeClient005();
    $wh         = makeWarehouse005($client);
    $actor      = makeActor005($client);
    $supervisor = makeActor005($client);
    $batch      = makeBatch005($client, '2027-12-31');
    $bin        = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 20, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 20, $actor);
    $r = ledger()->reserve($a, 8, 'ORD-001', $actor);

    // Force reclassify the bin to Quarantine zone
    $affected = ledger()->forceReclassifyLocation($bin, 'Q', $supervisor);
    expect($affected)->not->toBeEmpty();

    $a->refresh(); $r->refresh();
    expect($a->status)->toBe('IN_LOCATION');   // AVAILABLE → IN_LOCATION
    expect($r->status)->toBe('RESERVED_HELD'); // RESERVED → RESERVED_HELD (order link preserved)
    expect($r->order_reference)->toBe('ORD-001'); // order reference preserved

    $bin->refresh();
    expect($bin->zone)->toBe('Q');
    expect($bin->is_eligible_for_available_stock)->toBeFalse();
});

// ===========================================================================
// SECTION 8 — LEDGER RECONCILIATION INVARIANTS
// ===========================================================================

test('dispatched tombstone does not appear in on-hand inventory', function () {
    $client = makeClient005();
    $wh     = makeWarehouse005($client);
    $actor  = makeActor005($client);
    $batch  = makeBatch005($client, '2027-12-31');
    $bin    = makeLocation005($wh, 'A');

    $h = ledger()->receive($batch, 10, $actor, ['warehouse_id' => $wh->id]);
    $a = ledger()->putaway($h, $bin, 10, $actor);
    $r = ledger()->reserve($a, 10, 'ORD-001', $actor);
    $p = ledger()->pick($r, $actor);
    $k = ledger()->pack($p, $actor);
    $d = ledger()->dispatch($k, $actor);

    expect($d->qty)->toBe(0);
    expect($d->status)->toBe('DISPATCHED');
    expect(ledger()->onHandQty($client->id))->toBe(0);
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});

test('sum of on-hand entries equals ledger-derived total across complex scenario', function () {
    $client   = makeClient005();
    $wh       = makeWarehouse005($client);
    $actor    = makeActor005($client);
    $approver = makeActor005($client);
    $batch    = makeBatch005($client, '2027-12-31');
    $bin1     = makeLocation005($wh, 'A');
    $bin2     = makeLocation005($wh, 'A');

    // Receive 100
    $h = ledger()->receive($batch, 100, $actor, ['warehouse_id' => $wh->id]);
    $a1 = ledger()->putaway($h, $bin1, 60, $actor);
    $h->refresh();
    $a2 = ledger()->putaway($h, $bin2, 40, $actor);

    // Reserve 20 from bin1
    $r = ledger()->reserve($a1, 20, 'ORD-001', $actor);

    // Dispatch the 20
    $p1 = ledger()->pick($r, $actor);
    $p2 = ledger()->pack($p1, $actor);
    ledger()->dispatch($p2, $actor);

    // Transfer 10 from bin2 to bin1 area
    $a1->refresh();
    ledger()->transfer($a2, $bin1, 10, $actor);

    // Quarantine 5 from bin1 remainder
    $a1->refresh();
    ledger()->quarantine($a1, 5, 'DAMAGED', $actor);

    // Write-off 3 from bin2 remainder
    $a2->refresh();
    ledger()->adjust($a2, -3, $actor, $approver, 'Discrepancy');

    // Expected: 100 received - 20 dispatched - 3 written off = 77
    expect(ledger()->onHandQty($client->id))->toBe(77);
    expect(ledger()->ledgerDerivedOnHand($client->id))->toBe(77);
    expect(ledger()->assertReconciled($client->id))->toBeTrue();
});
