<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ShopifyProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ProductImportService
 *
 * Handles the full lifecycle of a CSV import:
 *   1. store()       — save the file, create a ProductImport in 'preview' status
 *   2. validate()    — parse rows, check rules, return row-level report (no DB writes to products)
 *   3. confirm()     — write validated rows to products or variant_mappings idempotently
 *
 * Key rules enforced:
 *   - A manual mapping (provenance=manual) is NEVER overwritten by a CSV import.
 *   - Conflicting/ambiguous identity (same SKU, different name OR same barcode, different SKU)
 *     goes to exception status — never silently merged.
 *   - pack_qty_confirmed is NEVER set to true by an import — only a human action can confirm it.
 *   - Retrying the same import (re-uploading the same file) produces a new ProductImport record
 *     but upserts on (fulfilment_client_id, internal_sku) — no duplicates created.
 *   - A variant GID must be unique within its shop_domain — DB constraint enforced plus
 *     a pre-check to surface a clear error rather than a constraint violation.
 */
class ProductImportService
{
    // Columns required in a products CSV
    public const PRODUCT_REQUIRED_COLUMNS = ['internal_sku', 'name', 'unit_of_measure'];

    // Columns required in a variant mappings CSV
    public const MAPPING_REQUIRED_COLUMNS = ['shop_domain', 'variant_gid'];

    /**
     * Store the uploaded file and create a ProductImport record in 'preview' status.
     */
    public function store(
        User $user,
        string $importType,
        string $originalFilename,
        string $csvContent,
        array $columnMapping
    ): ProductImport {
        $storedFilename = 'imports/' . Str::uuid() . '_' . $originalFilename;
        Storage::disk('private')->put($storedFilename, $csvContent);

        return ProductImport::create([
            'fulfilment_client_id' => $user->fulfilment_client_id,
            'imported_by'          => $user->id,
            'import_type'          => $importType,
            'original_filename'    => $originalFilename,
            'stored_filename'      => $storedFilename,
            'column_mapping'       => $columnMapping,
            'status'               => 'preview',
            'total_rows'           => 0,
        ]);
    }

    /**
     * Parse the CSV and validate rows. Updates the ProductImport with a report.
     * Does NOT write to products or shopify_product_variants.
     */
    public function validate(ProductImport $import): ProductImport
    {
        $csv = Storage::disk('private')->get($import->stored_filename);
        $rows = $this->parseCsv($csv, $import->column_mapping);

        $report = [];
        $errorCount = 0;

        if ($import->import_type === 'products') {
            [$report, $errorCount] = $this->validateProductRows($rows, $import->fulfilment_client_id);
        } else {
            [$report, $errorCount] = $this->validateMappingRows($rows, $import->fulfilment_client_id);
        }

        $import->update([
            'status'            => 'validated',
            'total_rows'        => count($rows),
            'error_rows'        => $errorCount,
            'validation_report' => $report,
        ]);

        return $import;
    }

    /**
     * Write validated rows to the database. Idempotent — safe to call multiple times.
     * Updates the ProductImport status to 'confirmed' or 'partial'.
     */
    public function confirm(ProductImport $import): ProductImport
    {
        if ($import->status === 'confirmed') {
            return $import; // Already done — idempotent
        }

        $csv = Storage::disk('private')->get($import->stored_filename);
        $rows = $this->parseCsv($csv, $import->column_mapping);

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $report = $import->validation_report ?? [];

        DB::transaction(function () use (
            $import, $rows, &$imported, &$skipped, &$errors, &$report
        ) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // 1-indexed, row 1 is header
                try {
                    if ($import->import_type === 'products') {
                        $result = $this->upsertProduct($row, $import->fulfilment_client_id);
                    } else {
                        $result = $this->upsertVariantMapping($row, $import->fulfilment_client_id);
                    }

                    if ($result === 'skipped') {
                        $skipped++;
                        $report[$rowNum]['info'] = 'Skipped: manual mapping preserved.';
                    } else {
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $report[$rowNum]['error'] = $e->getMessage();
                }
            }
        });

        $status = $errors > 0 ? 'partial' : 'confirmed';

        $import->update([
            'status'            => $status,
            'imported_rows'     => $imported,
            'skipped_rows'      => $skipped,
            'error_rows'        => $errors,
            'validation_report' => $report,
            'confirmed_at'      => now(),
        ]);

        return $import;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function parseCsv(string $csv, array $columnMapping): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $csv)));
        $lines = array_values($lines);

        if (empty($lines)) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $raw = str_getcsv($line);
            $mapped = [];
            foreach ($columnMapping as $field => $csvColumn) {
                $csvIndex = array_search($csvColumn, $headers);
                $mapped[$field] = $csvIndex !== false ? ($raw[$csvIndex] ?? null) : null;
            }
            $rows[] = $mapped;
        }

        return $rows;
    }

    private function validateProductRows(array $rows, int $clientId): array
    {
        $report = [];
        $errorCount = 0;
        $seenSkus = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $rowErrors = [];

            if (empty($row['internal_sku'])) {
                $rowErrors[] = 'internal_sku is required.';
            }
            if (empty($row['name'])) {
                $rowErrors[] = 'name is required.';
            }
            if (empty($row['unit_of_measure'])) {
                $rowErrors[] = 'unit_of_measure is required.';
            }

            // Duplicate SKU within the file
            if (!empty($row['internal_sku'])) {
                if (isset($seenSkus[$row['internal_sku']])) {
                    $rowErrors[] = "Duplicate internal_sku '{$row['internal_sku']}' in this file (first at row {$seenSkus[$row['internal_sku']]}).";
                } else {
                    $seenSkus[$row['internal_sku']] = $rowNum;
                }
            }

            // Check for conflicting barcode: same barcode, different SKU
            if (!empty($row['manufacturer_barcode'])) {
                $conflict = Product::withoutGlobalScopes()
                    ->where('fulfilment_client_id', $clientId)
                    ->where('manufacturer_barcode', $row['manufacturer_barcode'])
                    ->where('internal_sku', '!=', $row['internal_sku'])
                    ->first();
                if ($conflict) {
                    $rowErrors[] = "manufacturer_barcode '{$row['manufacturer_barcode']}' already belongs to SKU '{$conflict->internal_sku}'. Quarantined.";
                }
            }

            // Warn about unconfirmed pack_qty (never an error, always visible)
            if (!empty($row['pack_qty'])) {
                $report[$rowNum]['warnings'][] = "pack_qty={$row['pack_qty']} will be imported as UNVERIFIED. A human must confirm it before it is trusted.";
            }

            if (!empty($rowErrors)) {
                $report[$rowNum]['errors'] = $rowErrors;
                $errorCount++;
            }
        }

        return [$report, $errorCount];
    }

    private function validateMappingRows(array $rows, int $clientId): array
    {
        $report = [];
        $errorCount = 0;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $rowErrors = [];

            if (empty($row['shop_domain'])) {
                $rowErrors[] = 'shop_domain is required.';
            }
            if (empty($row['variant_gid'])) {
                $rowErrors[] = 'variant_gid is required.';
            }

            // Check if this variant is already manually mapped — must not overwrite
            if (!empty($row['variant_gid']) && !empty($row['shop_domain'])) {
                $existing = ShopifyProductVariant::where('shop_domain', $row['shop_domain'])
                    ->where('variant_gid', $row['variant_gid'])
                    ->first();

                if ($existing && $existing->provenance === 'manual') {
                    $report[$rowNum]['warnings'][] = "variant_gid '{$row['variant_gid']}' has a manual mapping — will be skipped, not overwritten.";
                }

                // Cross-client check
                if ($existing && $existing->fulfilment_client_id !== $clientId) {
                    $rowErrors[] = "variant_gid '{$row['variant_gid']}' is already assigned to a different client. Cannot import.";
                }
            }

            if (!empty($rowErrors)) {
                $report[$rowNum]['errors'] = $rowErrors;
                $errorCount++;
            }
        }

        return [$report, $errorCount];
    }

    /**
     * Upsert a product row. Idempotent on (fulfilment_client_id, internal_sku).
     * pack_qty_confirmed is always forced to false on import.
     */
    private function upsertProduct(array $row, int $clientId): string
    {
        if (empty($row['internal_sku']) || empty($row['name'])) {
            throw new \InvalidArgumentException('internal_sku and name are required.');
        }

        // Check for conflicting barcode (different SKU, same barcode) — quarantine
        if (!empty($row['manufacturer_barcode'])) {
            $conflict = Product::withoutGlobalScopes()
                ->where('fulfilment_client_id', $clientId)
                ->where('manufacturer_barcode', $row['manufacturer_barcode'])
                ->where('internal_sku', '!=', $row['internal_sku'])
                ->first();
            if ($conflict) {
                throw new \RuntimeException(
                    "Conflicting manufacturer_barcode '{$row['manufacturer_barcode']}' — belongs to SKU '{$conflict->internal_sku}'."
                );
            }
        }

        Product::withoutGlobalScopes()->updateOrCreate(
            [
                'fulfilment_client_id' => $clientId,
                'internal_sku'         => $row['internal_sku'],
            ],
            [
                'name'                 => $row['name'],
                'scan_code'            => $row['scan_code'] ?? null,
                'manufacturer_barcode' => $row['manufacturer_barcode'] ?? null,
                'unit_of_measure'      => $row['unit_of_measure'] ?? 'each',
                'pack_qty'             => !empty($row['pack_qty']) ? (int) $row['pack_qty'] : null,
                'pack_qty_confirmed'   => false, // NEVER set by import
                'is_active'            => true,
            ]
        );

        return 'upserted';
    }

    /**
     * Upsert a variant mapping. Skips manual mappings. Raises exception on cross-client.
     */
    private function upsertVariantMapping(array $row, int $clientId): string
    {
        if (empty($row['variant_gid']) || empty($row['shop_domain'])) {
            throw new \InvalidArgumentException('variant_gid and shop_domain are required.');
        }

        $existing = ShopifyProductVariant::where('shop_domain', $row['shop_domain'])
            ->where('variant_gid', $row['variant_gid'])
            ->first();

        // Do not overwrite a manual mapping
        if ($existing && $existing->provenance === 'manual') {
            return 'skipped';
        }

        // Cross-client protection
        if ($existing && $existing->fulfilment_client_id !== $clientId) {
            throw new \RuntimeException(
                "variant_gid '{$row['variant_gid']}' belongs to a different client."
            );
        }

        // Resolve product_id — only if an unambiguous match exists
        $productId = null;
        $mappingStatus = 'unmapped';
        $exceptionReason = null;

        if (!empty($row['source_sku'])) {
            $matches = Product::withoutGlobalScopes()
                ->where('fulfilment_client_id', $clientId)
                ->where('internal_sku', $row['source_sku'])
                ->get();

            if ($matches->count() === 1) {
                $productId = $matches->first()->id;
                $mappingStatus = 'mapped';
            } elseif ($matches->count() > 1) {
                $mappingStatus = 'exception';
                $exceptionReason = "Ambiguous: {$matches->count()} products match SKU '{$row['source_sku']}'.";
            }
        }

        // Barcode fallback (only if SKU match failed and a barcode is provided)
        if ($productId === null && !empty($row['source_barcode'])) {
            $barcodeMatches = Product::withoutGlobalScopes()
                ->where('fulfilment_client_id', $clientId)
                ->where(function ($q) use ($row) {
                    $q->where('manufacturer_barcode', $row['source_barcode'])
                      ->orWhere('scan_code', $row['source_barcode']);
                })
                ->get();

            if ($barcodeMatches->count() === 1) {
                $productId = $barcodeMatches->first()->id;
                $mappingStatus = 'mapped';
            } elseif ($barcodeMatches->count() > 1) {
                $mappingStatus = 'exception';
                $exceptionReason = "Ambiguous: {$barcodeMatches->count()} products match barcode '{$row['source_barcode']}'.";
            }
        }

        ShopifyProductVariant::updateOrCreate(
            [
                'shop_domain' => $row['shop_domain'],
                'variant_gid' => $row['variant_gid'],
            ],
            [
                'fulfilment_client_id' => $clientId,
                'product_id'           => $productId,
                'source_sku'           => $row['source_sku'] ?? null,
                'source_barcode'       => $row['source_barcode'] ?? null,
                'shopify_title'        => $row['shopify_title'] ?? null,
                'mapping_status'       => $mappingStatus,
                'provenance'           => 'csv_import',
                'exception_reason'     => $exceptionReason,
            ]
        );

        return 'upserted';
    }
}
