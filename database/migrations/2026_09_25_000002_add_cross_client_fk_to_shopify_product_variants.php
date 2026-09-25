<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a multi-column foreign key constraint to shopify_product_variants.
 *
 * The constraint: FOREIGN KEY (product_id, fulfilment_client_id)
 *                 REFERENCES products(id, fulfilment_client_id)
 *
 * This enforces at the database level that product_id can only point to a
 * product row whose fulfilment_client_id matches the variant's own
 * fulfilment_client_id. A variant with client_id=100 mapping to a product
 * with client_id=200 raises an integrity violation — the DB engine blocks it
 * without any application code needing to run.
 *
 * The constraint is deferred/partial on NULL: if product_id IS NULL (unmapped
 * variant), the FK is not checked. This is the correct behaviour — unmapped
 * variants have no product association yet.
 *
 * Requires: products.unique(id, fulfilment_client_id) — added in migration
 * 2026_09_25_000001.
 *
 * SQLite notes:
 *   - foreign_key_constraints is enabled in config/database.php (default true)
 *   - The PRAGMA foreign_keys = ON is set per connection by Laravel
 *   - SQLite enforces multi-column FKs correctly (tested: SQLite 3.53.4)
 *
 * MySQL/MariaDB notes:
 *   - InnoDB enforces multi-column FKs natively
 *   - The unique index on products(id, fulfilment_client_id) satisfies the
 *     InnoDB requirement that the referenced columns have an index
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_product_variants', function (Blueprint $table) {
            // The multi-column FK: product_id + fulfilment_client_id must both
            // match a row in products — blocks cross-client product_id assignment.
            $table->foreign(['product_id', 'fulfilment_client_id'], 'spv_product_client_fk')
                ->references(['id', 'fulfilment_client_id'])
                ->on('products')
                ->nullOnDelete(); // If the product is deleted, unmap the variant
        });
    }

    public function down(): void
    {
        Schema::table('shopify_product_variants', function (Blueprint $table) {
            $table->dropForeign('spv_product_client_fk');
        });
    }
};
