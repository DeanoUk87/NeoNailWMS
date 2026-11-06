<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarantine records table.
 *
 * Every quarantine action creates a record here.
 * The record tracks the reason, resolution path, and who authorised each step.
 * stock_entries with status=QUARANTINE are linked to a quarantine_record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarantine_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('stock_entry_id');
            $table->unsignedBigInteger('fulfilment_client_id');

            $table->enum('reason', [
                'DAMAGED',          // Physical damage on receipt or in storage
                'EXPIRED',          // Past operational_expiry_date
                'NO_BATCH',         // No manufacturer batch number found
                'UNKNOWN_PRODUCT',  // Barcode not recognised
                'INVESTIGATION',    // General hold for investigation
                'NEAR_EXPIRY',      // Within configurable near-expiry threshold
                'ZONE_RECLASSIFY',  // Location reclassified; stock needs relocation
            ]);

            $table->unsignedInteger('quantity');

            $table->enum('status', [
                'OPEN',               // Quarantine active; no resolution yet
                'RESOLVED_RETURN',    // Returned to available stock
                'RESOLVED_DISPOSE',   // Written off / disposed
                'RESOLVED_SUPPLIER',  // Returned to supplier
            ])->default('OPEN');

            $table->unsignedBigInteger('raised_by');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('raised_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();

            $table->index('stock_entry_id');
            $table->index('fulfilment_client_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarantine_records');
    }
};
