<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a composite unique index on (id, fulfilment_client_id) to products.
 *
 * This is required as the FK target for the multi-column foreign key on
 * shopify_product_variants (migration 2026_09_25_000002). SQLite and MySQL
 * both require that the referenced columns form a unique index.
 *
 * The (id) primary key already guarantees uniqueness of id alone, but the
 * multi-column FK needs (id, fulfilment_client_id) to be declared unique
 * together so the DB engine can enforce "product_id belongs to the same client".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['id', 'fulfilment_client_id'], 'products_id_client_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_id_client_unique');
        });
    }
};
