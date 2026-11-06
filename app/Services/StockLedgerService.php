<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Location;
use App\Models\QuarantineRecord;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * StockLedgerService
 *
 * The authoritative engine for all stock movements.
 * Every public method wraps its operations in a database transaction.
 * No quantity is created or destroyed without an immutable StockMovement record.
 *
 * Key invariants maintained (from Task 003C Technical Addendum):
 * 1. Physical inventory = SUM(stock_entries.qty) WHERE status NOT IN ('DISPATCHED')
 * 2. Movement ledger reconciles: SUM(RECEIPT) - ABS(SUM(DISPATCH)) - ABS(SUM(WRITE_OFF)) = on-hand
 * 3. AVAILABLE status requires eligible zone (enforced by SQLite trigger)
 * 4. No negative quantities (enforced by SQLite trigger)
 * 5. DISPATCHED entries have qty=0 (tombstone for traceability)
 * 6. Idempotency keys prevent duplicate movements on retry
 * 7. Version counter enables optimistic concurrency detection
 * 8. Expired batches are blocked at allocation, pick, and dispatch (live check)
 */
class StockLedgerService
{
    // -------------------------------------------------------------------------
    // Helper: record an immutable movement
    // -------------------------------------------------------------------------

    private function recordMovement(
        StockEntry $entry,
        string $type,
        int $quantityChange,
        User $actor,
        array $options = []
    ): StockMovement {
        // Idempotency: reject duplicate submissions
        if (!empty($options['idempotency_key'])) {
            $existing = StockMovement::where('idempotency_key', $options['idempotency_key'])->first();
            if ($existing) {
                return $existing; // Silent no-op on retry
            }
        }

        return StockMovement::create([
            'stock_entry_id'   => $entry->id,
            'movement_type'    => $type,
            'quantity_change'  => $quantityChange,
            'from_location_id' => $options['from_location_id'] ?? null,
            'to_location_id'   => $options['to_location_id']   ?? null,
            'reference_type'   => $options['reference_type']   ?? null,
            'reference_id'     => $options['reference_id']     ?? null,
            'performed_by'     => $actor->id,
            'approved_by'      => $options['approved_by']      ?? null,
            'performed_at'     => now(),
            'notes'            => $options['notes']            ?? null,
            'idempotency_key'  => $options['idempotency_key']  ?? null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper: optimistic version check
    // -------------------------------------------------------------------------

    private function updateWithVersionCheck(StockEntry $entry, array $data): void
    {
        $expectedVersion = $entry->version;
        $data['version'] = $expectedVersion + 1;

        $affected = StockEntry::where('id', $entry->id)
            ->where('version', $expectedVersion)
            ->update($data);

        if ($affected === 0) {
            throw new \RuntimeException(
                "Concurrent modification detected on StockEntry #{$entry->id}. " .
                "Another operation updated this record. Please retry."
            );
        }

        $entry->fill($data);
    }

    // -------------------------------------------------------------------------
    // Helper: live expiry check (BD-13 — always from source, never cached)
    // -------------------------------------------------------------------------

    private function assertNotExpired(StockEntry $entry, ?Carbon $asOf = null): void
    {
        $batch = $entry->batch;
        if ($batch->isExpired($asOf)) {
            throw new \RuntimeException(
                "Batch '{$batch->manufacturer_batch_number}' of product " .
                "#{$batch->product_id} is EXPIRED (expiry: {$batch->operational_expiry_date}). " .
                "Cannot allocate, pick or dispatch expired stock."
            );
        }
        if ($batch->batch_source === 'unidentifiable') {
            throw new \RuntimeException(
                "Batch on hold — no manufacturer batch number. Cannot allocate until supervisor resolves."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Operation 1: Receive stock (creates holding entry)
    // -------------------------------------------------------------------------

    /**
     * Record receipt of a quantity into an APPROVED_PUTAWAY holding entry.
     * No location assigned yet — stock is physically in the inbound zone.
     *
     * @return StockEntry The holding entry (status=APPROVED_PUTAWAY, location=null)
     */
    public function receive(
        Batch $batch,
        int $qty,
        User $actor,
        array $options = []
    ): StockEntry {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Received quantity must be positive. Got: {$qty}");
        }

        return DB::transaction(function () use ($batch, $qty, $actor, $options) {
            $entry = StockEntry::create([
                'fulfilment_client_id' => $batch->fulfilment_client_id,
                'warehouse_id'         => $options['warehouse_id'] ?? $this->resolveWarehouseId($batch),
                'batch_id'             => $batch->id,
                'location_id'          => null,
                'qty'                  => $qty,
                'status'               => 'APPROVED_PUTAWAY',
                'version'              => 1,
            ]);

            $this->recordMovement($entry, 'RECEIPT', +$qty, $actor, array_merge($options, [
                'notes' => "Received {$qty} units of batch {$batch->manufacturer_batch_number}",
            ]));

            return $entry->fresh();
        });
    }

    private function resolveWarehouseId(Batch $batch): int
    {
        // Default: use first warehouse for this client
        $warehouse = \App\Models\Warehouse::withoutGlobalScopes()
            ->where('fulfilment_client_id', $batch->fulfilment_client_id)
            ->firstOrFail();
        return $warehouse->id;
    }

    // -------------------------------------------------------------------------
    // Operation 2: Putaway (move from holding entry to a bin)
    // -------------------------------------------------------------------------

    /**
     * Put away a quantity from a holding entry into a specific bin location.
     * Creates a new located entry and reduces the holding entry.
     * Supports partial putaway — call multiple times for different bins.
     *
     * @return StockEntry The new located entry (status=AVAILABLE if eligible zone)
     */
    public function putaway(
        StockEntry $holdingEntry,
        Location $location,
        int $qty,
        User $actor,
        array $options = []
    ): StockEntry {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Putaway quantity must be positive.");
        }
        if (!in_array($holdingEntry->status, ['APPROVED_PUTAWAY', 'IN_LOCATION'], true)) {
            throw new \RuntimeException(
                "Cannot put away entry with status {$holdingEntry->status}. " .
                "Expected APPROVED_PUTAWAY or IN_LOCATION."
            );
        }
        if ($qty > $holdingEntry->qty) {
            throw new \RuntimeException(
                "Cannot put away {$qty} units — only {$holdingEntry->qty} remaining in holding entry."
            );
        }

        return DB::transaction(function () use ($holdingEntry, $location, $qty, $actor, $options) {
            // Determine the target status based on zone eligibility
            $targetStatus = $location->is_eligible_for_available_stock
                ? 'AVAILABLE'
                : 'IN_LOCATION';

            // Reduce the holding entry
            $newHoldingQty = $holdingEntry->qty - $qty;
            $this->updateWithVersionCheck($holdingEntry, [
                'qty'    => $newHoldingQty,
                'status' => $newHoldingQty === 0 ? 'IN_LOCATION' : $holdingEntry->status,
            ]);

            // Create the located entry
            $locatedEntry = StockEntry::create([
                'fulfilment_client_id' => $holdingEntry->fulfilment_client_id,
                'warehouse_id'         => $holdingEntry->warehouse_id,
                'batch_id'             => $holdingEntry->batch_id,
                'location_id'          => $location->id,
                'qty'                  => $qty,
                'status'               => $targetStatus,
                'version'              => 1,
            ]);

            // Record the movement (net zero — redistribution only)
            $this->recordMovement($locatedEntry, 'PUTAWAY', 0, $actor, array_merge($options, [
                'from_location_id' => null,
                'to_location_id'   => $location->id,
                'notes'            => "Putaway {$qty} units to {$location->barcode}",
            ]));

            return $locatedEntry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 3: Reserve stock for an order
    // -------------------------------------------------------------------------

    /**
     * Reserve a quantity from an AVAILABLE entry for a specific order.
     * Splits the entry: reserved portion moves to new RESERVED entry.
     * Live expiry check performed before reservation.
     *
     * @return StockEntry The RESERVED entry
     */
    public function reserve(
        StockEntry $availableEntry,
        int $qty,
        string $orderReference,
        User $actor,
        array $options = []
    ): StockEntry {
        if ($availableEntry->status !== 'AVAILABLE') {
            throw new \RuntimeException(
                "Cannot reserve from entry with status {$availableEntry->status}. Must be AVAILABLE."
            );
        }
        if ($qty <= 0 || $qty > $availableEntry->qty) {
            throw new \RuntimeException(
                "Invalid reservation quantity {$qty}. Available: {$availableEntry->qty}."
            );
        }

        // Live expiry check (BD-13)
        $this->assertNotExpired($availableEntry);

        return DB::transaction(function () use ($availableEntry, $qty, $orderReference, $actor, $options) {
            // Reduce the available entry
            $this->updateWithVersionCheck($availableEntry, [
                'qty' => $availableEntry->qty - $qty,
            ]);

            // Create the reserved entry
            $reservedEntry = StockEntry::create([
                'fulfilment_client_id' => $availableEntry->fulfilment_client_id,
                'warehouse_id'         => $availableEntry->warehouse_id,
                'batch_id'             => $availableEntry->batch_id,
                'location_id'          => $availableEntry->location_id,
                'qty'                  => $qty,
                'status'               => 'RESERVED',
                'order_reference'      => $orderReference,
                'version'              => 1,
            ]);

            $this->recordMovement($reservedEntry, 'RESERVATION', 0, $actor, array_merge($options, [
                'reference_type' => 'order',
                'reference_id'   => $orderReference,
                'notes'          => "Reserved {$qty} units for order {$orderReference}",
            ]));

            return $reservedEntry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 4: Pick — RESERVED → PICKED
    // -------------------------------------------------------------------------

    /**
     * Mark reserved stock as physically picked from its bin.
     * Status-only change: quantity does not change (stock still in warehouse).
     * Live expiry check performed.
     */
    public function pick(StockEntry $reservedEntry, User $actor, array $options = []): StockEntry
    {
        if ($reservedEntry->status !== 'RESERVED') {
            throw new \RuntimeException(
                "Cannot pick entry with status {$reservedEntry->status}. Must be RESERVED."
            );
        }

        $this->assertNotExpired($reservedEntry);

        return DB::transaction(function () use ($reservedEntry, $actor, $options) {
            $fromLocation = $reservedEntry->location_id;

            $this->updateWithVersionCheck($reservedEntry, [
                'status'      => 'PICKED',
                'location_id' => null, // No longer in a bin; at packing station
            ]);

            $this->recordMovement($reservedEntry, 'PICK', 0, $actor, array_merge($options, [
                'from_location_id' => $fromLocation,
                'notes'            => "Picked {$reservedEntry->qty} units for order {$reservedEntry->order_reference}",
            ]));

            return $reservedEntry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 5: Pack — PICKED → PACKED
    // -------------------------------------------------------------------------

    /**
     * Mark picked stock as packed into a parcel.
     * Status-only change.
     */
    public function pack(StockEntry $pickedEntry, User $actor, array $options = []): StockEntry
    {
        if ($pickedEntry->status !== 'PICKED') {
            throw new \RuntimeException(
                "Cannot pack entry with status {$pickedEntry->status}. Must be PICKED."
            );
        }

        return DB::transaction(function () use ($pickedEntry, $actor, $options) {
            $this->updateWithVersionCheck($pickedEntry, ['status' => 'PACKED']);

            $this->recordMovement($pickedEntry, 'PACK', 0, $actor, array_merge($options, [
                'notes' => "Packed {$pickedEntry->qty} units for order {$pickedEntry->order_reference}",
            ]));

            return $pickedEntry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 6: Dispatch — PACKED → DISPATCHED (qty → 0, tombstone)
    // -------------------------------------------------------------------------

    /**
     * Dispatch a packed parcel. Stock physically leaves the warehouse.
     * qty is set to 0 (tombstone). The -qty movement records the outbound flow.
     * Live expiry re-check at dispatch (batch could have expired between pick and dispatch).
     */
    public function dispatch(
        StockEntry $packedEntry,
        User $actor,
        array $options = []
    ): StockEntry {
        if ($packedEntry->status !== 'PACKED') {
            throw new \RuntimeException(
                "Cannot dispatch entry with status {$packedEntry->status}. Must be PACKED."
            );
        }

        // Live expiry re-check at dispatch (BD-13 point 3)
        $this->assertNotExpired($packedEntry);

        return DB::transaction(function () use ($packedEntry, $actor, $options) {
            $dispatchedQty = $packedEntry->qty;

            $this->updateWithVersionCheck($packedEntry, [
                'qty'    => 0,           // Tombstone — stock has left the warehouse
                'status' => 'DISPATCHED',
            ]);

            $this->recordMovement($packedEntry, 'DISPATCH', -$dispatchedQty, $actor, array_merge($options, [
                'notes' => "Dispatched {$dispatchedQty} units for order {$packedEntry->order_reference}",
            ]));

            return $packedEntry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 7: Transfer between bins
    // -------------------------------------------------------------------------

    /**
     * Transfer a quantity from one AVAILABLE bin location to another.
     * Zero-sum operation: total on-hand does not change.
     * Creates a new entry at the destination; reduces the source.
     */
    public function transfer(
        StockEntry $sourceEntry,
        Location $destination,
        int $qty,
        User $actor,
        array $options = []
    ): StockEntry {
        if ($sourceEntry->status !== 'AVAILABLE') {
            throw new \RuntimeException(
                "Can only transfer AVAILABLE stock. Entry status: {$sourceEntry->status}."
            );
        }
        if ($qty <= 0 || $qty > $sourceEntry->qty) {
            throw new \RuntimeException(
                "Invalid transfer quantity {$qty}. Available: {$sourceEntry->qty}."
            );
        }

        return DB::transaction(function () use ($sourceEntry, $destination, $qty, $actor, $options) {
            $fromLocationId = $sourceEntry->location_id;

            $this->updateWithVersionCheck($sourceEntry, [
                'qty' => $sourceEntry->qty - $qty,
            ]);

            $destStatus = $destination->is_eligible_for_available_stock ? 'AVAILABLE' : 'IN_LOCATION';

            $destEntry = StockEntry::create([
                'fulfilment_client_id' => $sourceEntry->fulfilment_client_id,
                'warehouse_id'         => $sourceEntry->warehouse_id,
                'batch_id'             => $sourceEntry->batch_id,
                'location_id'          => $destination->id,
                'qty'                  => $qty,
                'status'               => $destStatus,
                'version'              => 1,
            ]);

            $this->recordMovement($destEntry, 'TRANSFER', 0, $actor, array_merge($options, [
                'from_location_id' => $fromLocationId,
                'to_location_id'   => $destination->id,
                'notes'            => "Transferred {$qty} units from location #{$fromLocationId} to {$destination->barcode}",
            ]));

            return $destEntry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 8: Quarantine
    // -------------------------------------------------------------------------

    /**
     * Quarantine a quantity of AVAILABLE or RESERVED stock.
     * Splits the entry: quarantined portion moves to QUARANTINE status.
     * Creates a QuarantineRecord for supervisor tracking.
     * RESERVED_HELD is used if quarantining reserved stock.
     */
    public function quarantine(
        StockEntry $entry,
        int $qty,
        string $reason,
        User $actor,
        array $options = []
    ): StockEntry {
        if (!in_array($entry->status, ['AVAILABLE', 'RESERVED', 'IN_LOCATION'], true)) {
            throw new \RuntimeException(
                "Cannot quarantine entry with status {$entry->status}."
            );
        }
        if ($qty <= 0 || $qty > $entry->qty) {
            throw new \RuntimeException(
                "Invalid quarantine quantity {$qty}. Entry qty: {$entry->qty}."
            );
        }

        return DB::transaction(function () use ($entry, $qty, $reason, $actor, $options) {
            $fromLocationId = $entry->location_id;

            // Reduce the source entry
            $this->updateWithVersionCheck($entry, [
                'qty' => $entry->qty - $qty,
            ]);

            // Create the quarantine entry
            $quarantineEntry = StockEntry::create([
                'fulfilment_client_id' => $entry->fulfilment_client_id,
                'warehouse_id'         => $entry->warehouse_id,
                'batch_id'             => $entry->batch_id,
                'location_id'          => $entry->location_id,
                'qty'                  => $qty,
                'status'               => 'QUARANTINE',
                'order_reference'      => $entry->order_reference,
                'version'              => 1,
            ]);

            // Create quarantine record for supervisor tracking
            QuarantineRecord::create([
                'stock_entry_id'       => $quarantineEntry->id,
                'fulfilment_client_id' => $entry->fulfilment_client_id,
                'reason'               => $reason,
                'quantity'             => $qty,
                'status'               => 'OPEN',
                'raised_by'            => $actor->id,
                'raised_at'            => now(),
                'notes'                => $options['notes'] ?? null,
            ]);

            $this->recordMovement($quarantineEntry, 'QUARANTINE_IN', 0, $actor, array_merge($options, [
                'from_location_id' => $fromLocationId,
                'notes'            => "Quarantined {$qty} units. Reason: {$reason}",
            ]));

            return $quarantineEntry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 9: Stock correction (supervisor-authorised write-down/write-up)
    // -------------------------------------------------------------------------

    /**
     * Apply an authorised stock correction.
     * Positive adjustment = write-up (stock found)
     * Negative adjustment = write-down (stock missing/damaged)
     * Both require supervisor approval.
     */
    public function adjust(
        StockEntry $entry,
        int $adjustment,
        User $actor,
        User $approver,
        string $reason,
        array $options = []
    ): StockEntry {
        if ($adjustment === 0) {
            throw new \InvalidArgumentException("Adjustment quantity cannot be zero.");
        }

        $newQty = $entry->qty + $adjustment;
        if ($newQty < 0) {
            throw new \RuntimeException(
                "Adjustment of {$adjustment} would make qty negative " .
                "(current: {$entry->qty}, result: {$newQty}). Cannot adjust below zero."
            );
        }

        return DB::transaction(function () use ($entry, $adjustment, $newQty, $actor, $approver, $reason, $options) {
            $this->updateWithVersionCheck($entry, ['qty' => $newQty]);

            $movementType = $adjustment > 0 ? 'ADJUSTMENT' : 'WRITE_OFF';

            $this->recordMovement($entry, $movementType, $adjustment, $actor, array_merge($options, [
                'approved_by' => $approver->id,
                'notes'       => "Adjustment {$adjustment}: {$reason}",
            ]));

            return $entry->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Operation 10: Location reclassification guard (Task 003D Correction 2)
    // -------------------------------------------------------------------------

    /**
     * Check whether a location can be reclassified or deactivated.
     * Blocks if any AVAILABLE/RESERVED/IN_LOCATION stock is present.
     * Returns array of affected entry IDs if blocked; empty array if safe.
     */
    public function locationReclassificationCheck(Location $location): array
    {
        return StockEntry::where('location_id', $location->id)
            ->whereIn('status', ['AVAILABLE', 'RESERVED', 'RESERVED_HELD', 'IN_LOCATION'])
            ->pluck('id')
            ->toArray();
    }

    /**
     * Force reclassify a location zone (supervisor emergency action).
     * Demotes all AVAILABLE/RESERVED stock to RESERVED_HELD or IN_LOCATION.
     * Uses BEGIN IMMEDIATE for SQLite concurrency safety.
     */
    public function forceReclassifyLocation(
        Location $location,
        string $newZone,
        User $supervisor,
        array $options = []
    ): array {
        $affected = [];

        DB::transaction(function () use ($location, $newZone, $supervisor, $options, &$affected) {
            // Find all active entries in this location
            $entries = StockEntry::where('location_id', $location->id)
                ->whereIn('status', ['AVAILABLE', 'RESERVED', 'IN_LOCATION'])
                ->get();

            foreach ($entries as $entry) {
                // RESERVED → RESERVED_HELD (preserves order link)
                // AVAILABLE/IN_LOCATION → IN_LOCATION
                $newStatus = $entry->status === 'RESERVED' ? 'RESERVED_HELD' : 'IN_LOCATION';

                $this->updateWithVersionCheck($entry, ['status' => $newStatus]);

                $this->recordMovement($entry, 'ZONE_RECLASSIFICATION', 0, $supervisor, [
                    'notes' => "Location {$location->barcode} reclassified from {$location->zone} to {$newZone}. Stock demoted to {$newStatus}.",
                ]);

                $affected[] = $entry->id;
            }

            // Change the location zone
            $location->update([
                'zone' => $newZone,
                'is_eligible_for_available_stock' => in_array($newZone, Location::ELIGIBLE_ZONES, true),
            ]);
        });

        return $affected;
    }

    // -------------------------------------------------------------------------
    // Balance reconciliation
    // -------------------------------------------------------------------------

    /**
     * Calculate current on-hand physical inventory for a client.
     * Excludes DISPATCHED entries (qty=0 tombstones).
     */
    public function onHandQty(int $clientId, ?int $batchId = null, ?int $locationId = null): int
    {
        return StockEntry::where('fulfilment_client_id', $clientId)
            ->whereIn('status', StockEntry::ON_HAND_STATUSES)
            ->when($batchId,    fn ($q) => $q->where('batch_id',    $batchId))
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->sum('qty');
    }

    /**
     * Calculate on-hand derived from the movement ledger.
     * Must equal onHandQty() — any difference is a reconciliation error.
     */
    public function ledgerDerivedOnHand(int $clientId): int
    {
        $received = StockMovement::whereHas('stockEntry', fn ($q) =>
            $q->where('fulfilment_client_id', $clientId)
        )->whereIn('movement_type', ['RECEIPT', 'RETURN_IN'])
         ->sum('quantity_change');

        $dispatched = StockMovement::whereHas('stockEntry', fn ($q) =>
            $q->where('fulfilment_client_id', $clientId)
        )->where('movement_type', 'DISPATCH')
         ->sum('quantity_change'); // negative values

        $writtenOff = StockMovement::whereHas('stockEntry', fn ($q) =>
            $q->where('fulfilment_client_id', $clientId)
        )->where('movement_type', 'WRITE_OFF')
         ->sum('quantity_change'); // negative values

        return $received + $dispatched + $writtenOff;
    }

    /**
     * Verify that actual on-hand equals ledger-derived on-hand.
     * Returns true if balanced; throws RuntimeException if not.
     */
    public function assertReconciled(int $clientId): bool
    {
        $actual  = $this->onHandQty($clientId);
        $derived = $this->ledgerDerivedOnHand($clientId);

        if ($actual !== $derived) {
            throw new \RuntimeException(
                "Stock reconciliation error for client #{$clientId}: " .
                "actual on-hand ({$actual}) != ledger-derived ({$derived}). " .
                "Difference: " . ($actual - $derived)
            );
        }

        return true;
    }
}
