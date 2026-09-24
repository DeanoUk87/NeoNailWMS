<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products table.
 *
 * Design decisions:
 * - No parent-product merchandising system at this stage. Each colour/size
 *   variant is its own row (maps to one Shopify variant GID).
 * - manufacturer_barcode is NULLABLE. Many NeoNail items (e.g. nail files)
 *   have no EAN. Use internal_sku + scan_code for those.
 * - pack_qty and pack_qty_confirmed are separated deliberately. A pack
 *   quantity must be physically verified by NeoNail before it is trusted.
 *   pack_qty_confirmed = false means "do not treat this pack as N items".
 * - unit_of_measure is free-text but required (e.g. "each", "pack", "case").
 * - Client scoping via fulfilment_client_id with no FK constraint (matches
 *   the pattern established in the users table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fulfilment_client_id');
            $table->string('internal_sku');          // Primary human-assigned code
            $table->string('name');
            $table->string('scan_code')->nullable(); // Internal label barcode (printed if no EAN)
            $table->string('manufacturer_barcode')->nullable(); // EAN/UPC — optional
            $table->string('unit_of_measure');       // "each", "pack", "case" etc.
            $table->unsignedSmallInteger('pack_qty')->nullable(); // Units per pack/case
            $table->boolean('pack_qty_confirmed')->default(false); // Must be physically verified
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // A client's internal_sku must be unique within that client
            $table->unique(['fulfilment_client_id', 'internal_sku']);

            // scan_code unique within a client (nullable — unique index allows multiple NULLs in SQLite/MySQL)
            $table->unique(['fulfilment_client_id', 'scan_code']);

            $table->index('fulfilment_client_id');
            $table->index('manufacturer_barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
