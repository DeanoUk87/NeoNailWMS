<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product import audit table.
 *
 * Records every CSV import attempt with its outcome.
 * Retrying the same file produces a new import record but must not
 * create duplicate products/mappings (handled in the import service
 * using upsert logic keyed on internal_sku within a client).
 *
 * import_type: 'products' | 'variant_mappings'
 * status: 'preview' | 'validated' | 'confirmed' | 'failed' | 'partial'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fulfilment_client_id');
            $table->unsignedBigInteger('imported_by');      // users.id
            $table->enum('import_type', ['products', 'variant_mappings']);
            $table->string('original_filename');
            $table->string('stored_filename');              // Path in storage/app/private/imports/
            $table->json('column_mapping');                 // User's column assignment choices
            $table->enum('status', [
                'preview',      // Uploaded, column-mapped, not yet confirmed
                'validated',    // Validation ran — report available
                'confirmed',    // User clicked Confirm Import
                'failed',       // Fatal error — nothing written
                'partial',      // Some rows succeeded, some quarantined
            ])->default('preview');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('validation_report')->nullable();  // Row-level errors/warnings
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('fulfilment_client_id');
            $table->index('imported_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
