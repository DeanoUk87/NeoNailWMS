<?php

namespace Database\Seeders;

use App\Models\FulfilmentClient;
use App\Models\Product;
use App\Models\ShopifyProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoSeeder — populates the local database with representative sample data
 * for screen recording and UI review purposes.
 *
 * DO NOT run on production.
 *
 * Run with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // --- Fulfilment Client ---
        $client = FulfilmentClient::firstOrCreate(
            ['slug' => 'neonail-uk'],
            ['name' => 'NeoNail UK', 'is_active' => true]
        );

        // --- Users ---
        $admin = User::firstOrCreate(
            ['email' => 'admin@neonail.test'],
            [
                'name'                 => 'Admin User',
                'password'             => Hash::make('password'),
                'role'                 => 'admin',
                'fulfilment_client_id' => $client->id,
                'email_verified_at'    => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'operator@neonail.test'],
            [
                'name'                 => 'Warehouse Operator',
                'password'             => Hash::make('password'),
                'role'                 => 'operator',
                'fulfilment_client_id' => $client->id,
                'email_verified_at'    => now(),
            ]
        );

        // --- Products ---
        // Item 4504 — the nail files with unverified pack qty
        $nailFiles = Product::withoutGlobalScopes()->firstOrCreate(
            ['fulfilment_client_id' => $client->id, 'internal_sku' => '4504'],
            [
                'name'                 => 'Nail Files',
                'unit_of_measure'      => 'pack',
                'pack_qty'             => 25,
                'pack_qty_confirmed'   => false, // unverified — as required
                'manufacturer_barcode' => null,
                'scan_code'            => 'INT-4504',
                'is_active'            => true,
            ]
        );

        $baseCoat = Product::withoutGlobalScopes()->firstOrCreate(
            ['fulfilment_client_id' => $client->id, 'internal_sku' => 'BC-001'],
            [
                'name'                 => 'Base Coat 15ml',
                'unit_of_measure'      => 'each',
                'pack_qty'             => null,
                'pack_qty_confirmed'   => false,
                'manufacturer_barcode' => '5901234567890',
                'scan_code'            => null,
                'is_active'            => true,
            ]
        );

        $gelPolishRed = Product::withoutGlobalScopes()->firstOrCreate(
            ['fulfilment_client_id' => $client->id, 'internal_sku' => 'GP-RED-001'],
            [
                'name'                 => 'Gel Polish — Classic Red',
                'unit_of_measure'      => 'each',
                'pack_qty'             => null,
                'pack_qty_confirmed'   => false,
                'manufacturer_barcode' => '5901234567907',
                'scan_code'            => null,
                'is_active'            => true,
            ]
        );

        $gelPolishPink = Product::withoutGlobalScopes()->firstOrCreate(
            ['fulfilment_client_id' => $client->id, 'internal_sku' => 'GP-PINK-001'],
            [
                'name'                 => 'Gel Polish — Ballet Pink',
                'unit_of_measure'      => 'each',
                'pack_qty'             => null,
                'pack_qty_confirmed'   => false,
                'manufacturer_barcode' => '5901234567914',
                'scan_code'            => null,
                'is_active'            => true,
            ]
        );

        // Inactive product — demonstrates filter
        Product::withoutGlobalScopes()->firstOrCreate(
            ['fulfilment_client_id' => $client->id, 'internal_sku' => 'DISC-001'],
            [
                'name'                 => 'Discontinued Remover Pads',
                'unit_of_measure'      => 'pack',
                'pack_qty'             => 100,
                'pack_qty_confirmed'   => true,
                'manufacturer_barcode' => null,
                'scan_code'            => 'INT-DISC-001',
                'is_active'            => false,
            ]
        );

        // --- Shopify Variant Mappings ---
        $shop = 'neonail-uk.myshopify.com';

        // Mapped — manual (locked)
        ShopifyProductVariant::firstOrCreate(
            ['shop_domain' => $shop, 'variant_gid' => 'gid://shopify/ProductVariant/1000000001'],
            [
                'fulfilment_client_id' => $client->id,
                'product_id'           => $baseCoat->id,
                'source_sku'           => 'BC-001',
                'source_barcode'       => '5901234567890',
                'shopify_title'        => 'Base Coat 15ml / Default',
                'mapping_status'       => 'mapped',
                'provenance'           => 'manual',
            ]
        );

        // Mapped — csv import
        ShopifyProductVariant::firstOrCreate(
            ['shop_domain' => $shop, 'variant_gid' => 'gid://shopify/ProductVariant/1000000002'],
            [
                'fulfilment_client_id' => $client->id,
                'product_id'           => $gelPolishRed->id,
                'source_sku'           => 'GP-RED-001',
                'source_barcode'       => '5901234567907',
                'shopify_title'        => 'Gel Polish / Classic Red',
                'mapping_status'       => 'mapped',
                'provenance'           => 'csv_import',
            ]
        );

        // Unmapped
        ShopifyProductVariant::firstOrCreate(
            ['shop_domain' => $shop, 'variant_gid' => 'gid://shopify/ProductVariant/1000000003'],
            [
                'fulfilment_client_id' => $client->id,
                'product_id'           => null,
                'source_sku'           => 'GP-PINK-001',
                'source_barcode'       => '5901234567914',
                'shopify_title'        => 'Gel Polish / Ballet Pink',
                'mapping_status'       => 'unmapped',
                'provenance'           => 'csv_import',
            ]
        );

        // Exception — ambiguous barcode
        ShopifyProductVariant::firstOrCreate(
            ['shop_domain' => $shop, 'variant_gid' => 'gid://shopify/ProductVariant/1000000004'],
            [
                'fulfilment_client_id' => $client->id,
                'product_id'           => null,
                'source_sku'           => '4504',
                'source_barcode'       => null,
                'shopify_title'        => 'Nail Files 25-Pack',
                'mapping_status'       => 'exception',
                'provenance'           => 'csv_import',
                'exception_reason'     => 'pack_qty for SKU 4504 is unverified (25 × pack). Cannot confirm mapping until pack size is physically checked.',
            ]
        );

        // Exception — cross-SKU barcode conflict
        ShopifyProductVariant::firstOrCreate(
            ['shop_domain' => $shop, 'variant_gid' => 'gid://shopify/ProductVariant/1000000005'],
            [
                'fulfilment_client_id' => $client->id,
                'product_id'           => null,
                'source_sku'           => 'UNKNOWN-SKU',
                'source_barcode'       => '5901234567890',
                'shopify_title'        => 'Unknown Variant — barcode matches Base Coat',
                'mapping_status'       => 'exception',
                'provenance'           => 'csv_import',
                'exception_reason'     => 'Source barcode 5901234567890 matches product BC-001 (Base Coat) but source_sku UNKNOWN-SKU does not. Ambiguous — human review required.',
            ]
        );

        $this->command->info('Demo data seeded:');
        $this->command->info("  Client: NeoNail UK");
        $this->command->info("  Users: admin@neonail.test / operator@neonail.test (password: 'password')");
        $this->command->info("  Products: 5 (4 active, 1 inactive)");
        $this->command->info("  Variants: 5 (2 mapped, 1 unmapped, 2 exceptions)");
    }
}
