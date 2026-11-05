<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend locations table.
 *
 * The original locations table has:
 *   warehouse_id, barcode (unique within warehouse), name, location_type, is_active
 *
 * This migration adds:
 *   - zone: the physical zone type (A=ambient, I=inbound, Q=quarantine, D=dispatch, R=returns)
 *   - section: rack or section identifier (e.g. "01")
 *   - shelf: shelf level within the section (e.g. "S02")
 *   - bin_position: bin or compartment position (e.g. "B03")
 *   - capacity_units: optional maximum number of sellable units this bin can hold
 *   - notes: free-text notes for the location
 *
 * The barcode column already stores the unique scannable code (e.g. "A01-S02-B03").
 * The zone/section/shelf/bin_position fields allow the WMS to query and display
 * the hierarchy without parsing the barcode string.
 *
 * is_eligible_for_available_stock: computed from zone. Stored as a column for
 * use in CHECK constraints and simple queries. Set true for zones A and C only.
 * Updated by the application whenever zone changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('zone', 1)->default('A')->after('warehouse_id');
            $table->string('section')->nullable()->after('zone');     // rack/aisle e.g. "01"
            $table->string('shelf')->nullable()->after('section');    // shelf level e.g. "S02"
            $table->string('bin_position')->nullable()->after('shelf'); // bin/compartment e.g. "B03"
            $table->unsignedInteger('capacity_units')->nullable()->after('bin_position');
            $table->text('notes')->nullable()->after('capacity_units');
            // Denormalised eligibility flag — true only for zones A and C.
            // Used by the SQLite BEFORE UPDATE trigger and simple WHERE clauses.
            $table->boolean('is_eligible_for_available_stock')->default(true)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'zone', 'section', 'shelf', 'bin_position',
                'capacity_units', 'notes', 'is_eligible_for_available_stock',
            ]);
        });
    }
};
