<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopify product variants mapping table.
 *
 * Design decisions:
 * - variant_gid is the Shopify Global ID (gid://shopify/ProductVariant/123).
 *   Unique within a shop (shop_domain). Never reused across clients.
 * - product_id is nullable — a variant may be imported before it is mapped
 *   to a WMS product. Unmapped variants are visible in the exception list.
 * - mapping_status: 'unmapped' | 'pending_review' | 'mapped' | 'exception'
 * - provenance: 'csv_import' | 'manual' — a manual mapping must remain
 *   identifiable and must NOT be silently overwritten by a later import.
 * - source_sku and source_barcode are the raw values from the import source
 *   (Shopify export CSV). Kept for audit even after mapping.
 * - A variant GID is unique per shop_domain to enforce the Shopify contract.
 * - fulfilment_client_id prevents cross-client mapping at the DB level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_product_variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fulfilment_client_id');
            $table->string('shop_domain');           // e.g. neonail-uk.myshopify.com
            $table->string('variant_gid');           // gid://shopify/ProductVariant/…
            $table->unsignedBigInteger('product_id')->nullable(); // WMS product — null until mapped
            $table->string('source_sku')->nullable();    // Raw SKU from import
            $table->string('source_barcode')->nullable(); // Raw barcode from import
            $table->string('shopify_title')->nullable(); // Variant title from Shopify
            $table->enum('mapping_status', [
                'unmapped',
                'pending_review',
                'mapped',
                'exception',        // Conflicting/ambiguous — needs human resolution
            ])->default('unmapped');
            $table->enum('provenance', [
                'csv_import',
                'manual',           // Human-approved — must not be overwritten by import
            ])->default('csv_import');
            $table->text('exception_reason')->nullable(); // Why it is in exception status
            $table->timestamps();

            // A variant GID is unique within a shop — enforced at DB level
            $table->unique(['shop_domain', 'variant_gid']);

            $table->index('fulfilment_client_id');
            $table->index('product_id');
            $table->index('mapping_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_product_variants');
    }
};
