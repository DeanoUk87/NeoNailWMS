<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batches table.
 *
 * A batch represents one manufacturer's production run of a product.
 * Every unit of stock received must be associated with a batch record.
 *
 * Key design decisions (from Task 003A/003B/003C design):
 * - manufacturer_batch_number is NULLABLE. If the number cannot be identified
 *   on receipt, the batch is placed on hold (batch_source = 'unidentifiable')
 *   and stock is not released until a supervisor resolves it.
 * - operational_expiry_date is ALWAYS derived from manufacturer information.
 *   The WMS never invents an expiry date.
 * - For month/year-only labels (EXP 06/2027), operational_expiry_date = last
 *   day of stated month (2027-06-30). The raw string is preserved separately.
 * - no_expiry_confirmed = true only when configured by admin for a product
 *   category explicitly exempt from shelf-life tracking (e.g. accessories).
 * - internal_tracking_ref is an MP Transport warehouse reference assigned
 *   automatically. It is never presented as a manufacturer batch number.
 * - expiry_classification is a cached daily result for query performance.
 *   Live eligibility checks always use the raw operational_expiry_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fulfilment_client_id');
            $table->unsignedBigInteger('product_id');

            // Manufacturer-provided information (preserved exactly as supplied)
            $table->string('manufacturer_batch_number')->nullable();
            $table->string('manufacturer_expiry_raw')->nullable();   // e.g. "06/2027" or "30/06/2027"
            $table->enum('manufacturer_expiry_precision', [
                'full_date',    // Day/month/year provided
                'month_year',   // Month and year only
                'year_only',    // Year only
                'none',         // No expiry information from manufacturer
            ])->nullable();

            // Operational expiry date — derived from manufacturer info
            // Uses rule: today > operational_expiry_date means EXPIRED
            $table->date('operational_expiry_date')->nullable();
            $table->boolean('no_expiry_confirmed')->default(false); // Admin-set for exempt categories

            // Batch source and internal reference
            $table->enum('batch_source', [
                'manufacturer',     // Manufacturer batch number found and recorded
                'unidentifiable',   // No manufacturer batch found — on hold
            ])->default('manufacturer');

            // Internal warehouse tracking reference — NEVER the manufacturer batch
            $table->string('internal_tracking_ref')->nullable();

            // Cached expiry classification (updated daily by scheduled job)
            // Live checks bypass this and use operational_expiry_date directly
            $table->enum('expiry_classification', [
                'FULL_PRICE',
                'SHORT_12',
                'SHORT_9',
                'SHORT_6',
                'SHORT_3',
                'SHORT_1',
                'EXPIRED',
                'NO_EXPIRY',
                'EXPIRY_UNKNOWN',
            ])->default('EXPIRY_UNKNOWN');
            $table->timestamp('last_classification_check')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('fulfilment_client_id');
            $table->index('product_id');
            $table->index('operational_expiry_date');
            $table->index('expiry_classification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
