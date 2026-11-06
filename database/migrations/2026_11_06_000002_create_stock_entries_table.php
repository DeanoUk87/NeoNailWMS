<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock entries table.
 *
 * Each row represents a quantity of a specific batch at a specific location
 * with a specific status. This is the core inventory record.
 *
 * Key design decisions (from Task 003C Technical Addendum):
 * - qty is the CURRENT balance for this entry.
 * - qty = 0 on a DISPATCHED entry is intentional — the entry exists as a
 *   traceability anchor linking the batch to the dispatch movement.
 * - Physical inventory = SUM(qty) WHERE status NOT IN ('DISPATCHED')
 * - version is an optimistic lock counter for concurrent update detection.
 * - location_id is nullable only for APPROVED_PUTAWAY/RECEIVED_UNVERIFIED/
 *   PENDING_SUPERVISOR status (stock not yet located). AVAILABLE requires
 *   a non-null location in an eligible zone (enforced by trigger below).
 * - order_reference is set when status = RESERVED or RESERVED_HELD, linking
 *   the entry to the order that reserved it.
 * - The CHECK constraint enforces that AVAILABLE entries always have a location.
 *   The SQLite BEFORE INSERT/UPDATE trigger enforces zone eligibility.
 *
 * Status flow (from Task 003D):
 *   RECEIVED_UNVERIFIED → PENDING_SUPERVISOR → APPROVED_PUTAWAY →
 *   IN_LOCATION → AVAILABLE → RESERVED → PICKED → PACKED →
 *   DISPATCHED (qty=0, tombstone)
 *   AVAILABLE/RESERVED/RESERVED_HELD → QUARANTINE
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fulfilment_client_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('location_id')->nullable(); // null until putaway
            $table->unsignedInteger('qty');                        // current balance

            $table->enum('status', [
                'RECEIVED_UNVERIFIED', // Packaging unit count received; conversion unverified
                'PENDING_SUPERVISOR',  // Exception flag — supervisor action required
                'APPROVED_PUTAWAY',    // Ready to put away; physically in inbound zone
                'IN_LOCATION',         // Physically in a bin; not yet available to pick
                'AVAILABLE',           // In eligible bin; can be picked and allocated
                'RESERVED',            // Allocated to an order; picking authorised
                'RESERVED_HELD',       // Reservation preserved; bin unavailable
                'PICKED',              // Physically removed from bin; at packing station
                'PACKED',              // Sealed in parcel; in dispatch staging
                'DISPATCHED',          // Left the warehouse; qty=0 (tombstone)
                'QUARANTINE',          // Damaged/expired/investigation; cannot be picked
            ])->default('RECEIVED_UNVERIFIED');

            $table->string('order_reference')->nullable(); // Set when RESERVED/RESERVED_HELD
            $table->unsignedInteger('version')->default(1); // Optimistic lock counter

            $table->timestamps();

            // CHECK: AVAILABLE must have a location
            // The trigger enforces zone eligibility (not expressible as a simple CHECK)
            // $table->check("status != 'AVAILABLE' OR location_id IS NOT NULL");
            // Note: Laravel's check() method is not supported on SQLite in all versions.
            // The raw SQL is applied via a migration statement below.

            $table->index('fulfilment_client_id');
            $table->index('batch_id');
            $table->index('location_id');
            $table->index('status');
            $table->index(['fulfilment_client_id', 'status']); // For balance queries
        });

        // Apply CHECK constraint directly (SQLite-compatible)
        \DB::statement("
            CREATE TRIGGER trg_stock_entry_available_check_insert
            BEFORE INSERT ON stock_entries
            FOR EACH ROW
            WHEN NEW.status = 'AVAILABLE'
            BEGIN
                SELECT RAISE(ABORT, 'AVAILABLE stock entry must have a location_id')
                WHERE NEW.location_id IS NULL;

                SELECT RAISE(ABORT, 'location does not exist or is inactive')
                WHERE NOT EXISTS (
                    SELECT 1 FROM locations
                    WHERE id = NEW.location_id AND is_active = 1
                );

                SELECT RAISE(ABORT, 'location zone is not eligible for AVAILABLE stock')
                WHERE NOT EXISTS (
                    SELECT 1 FROM locations
                    WHERE id = NEW.location_id AND zone IN ('A', 'C')
                );
            END;
        ");

        \DB::statement("
            CREATE TRIGGER trg_stock_entry_available_check_update
            BEFORE UPDATE OF status ON stock_entries
            FOR EACH ROW
            WHEN NEW.status = 'AVAILABLE'
            BEGIN
                SELECT RAISE(ABORT, 'AVAILABLE stock entry must have a location_id')
                WHERE NEW.location_id IS NULL;

                SELECT RAISE(ABORT, 'location does not exist or is inactive')
                WHERE NOT EXISTS (
                    SELECT 1 FROM locations
                    WHERE id = NEW.location_id AND is_active = 1
                );

                SELECT RAISE(ABORT, 'location zone is not eligible for AVAILABLE stock')
                WHERE NOT EXISTS (
                    SELECT 1 FROM locations
                    WHERE id = NEW.location_id AND zone IN ('A', 'C')
                );
            END;
        ");

        // Prevent negative quantities
        \DB::statement("
            CREATE TRIGGER trg_stock_entry_no_negative_qty
            BEFORE UPDATE OF qty ON stock_entries
            FOR EACH ROW
            BEGIN
                SELECT RAISE(ABORT, 'stock_entries.qty cannot be negative')
                WHERE NEW.qty < 0;
            END;
        ");
    }

    public function down(): void
    {
        \DB::statement('DROP TRIGGER IF EXISTS trg_stock_entry_available_check_insert');
        \DB::statement('DROP TRIGGER IF EXISTS trg_stock_entry_available_check_update');
        \DB::statement('DROP TRIGGER IF EXISTS trg_stock_entry_no_negative_qty');
        Schema::dropIfExists('stock_entries');
    }
};
