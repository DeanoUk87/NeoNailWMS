<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a multi-column foreign key constraint to shopify_product_variants.
 *
 * The constraint: FOREIGN KEY (product_id, fulfilment_client_id)
 *                 REFERENCES products(id, fulfilment_client_id)
 *                 ON DELETE RESTRICT
 *
 * This enforces at the database level that product_id can only point to a
 * product row whose fulfilment_client_id matches the variant's own
 * fulfilment_client_id. A variant with client_id=100 mapping to a product
 * with client_id=200 raises an integrity violation — the DB engine blocks it
 * without any application code needing to run.
 *
 * Delete behaviour — RESTRICT (not CASCADE or SET NULL):
 *   nullOnDelete() was previously used but is incorrect here. fulfilment_client_id
 *   is NOT NULL on the variant row, so the DB engine cannot set it to NULL as
 *   part of the nullOnDelete action — it would raise a NOT NULL constraint
 *   violation of its own.
 *
 *   restrictOnDelete() means: a product with at least one mapped variant
 *   (product_id IS NOT NULL) cannot be deleted. The delete attempt is blocked
 *   at the DB level with a foreign key violation. The correct workflow is to
 *   explicitly set the variant's product_id to NULL (unmap it) before deleting
 *   the product. This is a deliberate safety gate — accidental deletion of a
 *   product that is still referenced by live Shopify variant mappings is blocked.
 *
 * The constraint is NULL-tolerant on the child side: if product_id IS NULL
 * (unmapped variant), the FK is not checked. Unmapped variants have no
 * product association yet.
 *
 * Requires: products.unique(id, fulfilment_client_id) — added in migration
 * 2026_09_25_000001.
 *
 * SQLite notes:
 *   - foreign_key_constraints is enabled in config/database.php (default true)
 *   - The PRAGMA foreign_keys = ON is set per connection by Laravel
 *   - SQLite enforces multi-column FKs and RESTRICT correctly (SQLite 3.53.4)
 *
 * MySQL/MariaDB notes:
 *   - InnoDB enforces RESTRICT natively (RESTRICT is the default for InnoDB FKs)
 *   - The unique index on products(id, fulfilment_client_id) satisfies the
 *     InnoDB requirement that the referenced columns have an index
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_product_variants', function (Blueprint $table) {
            // Multi-column FK: product_id + fulfilment_client_id must both match
            // a row in products — blocks cross-client assignment AND prevents
            // accidental deletion of a product that still has mapped variants.
            $table->foreign(['product_id', 'fulfilment_client_id'], 'spv_product_client_fk')
                ->references(['id', 'fulfilment_client_id'])
                ->on('products')
                ->restrictOnDelete(); // Block delete of product if mapped variants exist
        });
    }

    public function down(): void
    {
        Schema::table('shopify_product_variants', function (Blueprint $table) {
            $table->dropForeign('spv_product_client_fk');
        });
    }
};
