<?php

namespace App\Livewire\Products;

use App\Models\ProductImport;
use App\Services\ProductImportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Multi-step CSV import wizard.
 *
 * Steps:
 *   1. upload    — choose import type, upload CSV
 *   2. map       — assign CSV columns to WMS fields
 *   3. preview   — see validation report with row-level errors/warnings
 *   4. confirm   — deliberate "Import" button; writes to DB
 *   5. done      — summary of results
 */
class ImportWizard extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $step = 'upload';
    public string $importType = 'products';
    public $csvFile = null;
    public array $csvHeaders = [];
    public array $columnMapping = [];
    public ?int $importId = null;

    // Step 3 preview data
    public array $previewRows = [];
    public array $validationReport = [];
    public int $totalRows = 0;
    public int $errorRows = 0;

    // Step 5 results
    public ?string $resultSummary = null;

    public function uploadCsv(): void
    {
        $this->authorize('create', \App\Models\Product::class);
        $this->validate([
            'csvFile'     => 'required|file|mimes:csv,txt|max:5120',
            'importType'  => 'required|in:products,variant_mappings',
        ]);

        $content = file_get_contents($this->csvFile->getRealPath());
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
        $headers = str_getcsv(array_shift(array_values($lines)));

        $this->csvHeaders = $headers;

        // Auto-suggest column mapping by matching header names to field names
        $requiredFields = $this->importType === 'products'
            ? ProductImportService::PRODUCT_REQUIRED_COLUMNS
            : ProductImportService::MAPPING_REQUIRED_COLUMNS;

        $optionalFields = $this->importType === 'products'
            ? ['scan_code', 'manufacturer_barcode', 'pack_qty']
            : ['source_sku', 'source_barcode', 'shopify_title'];

        $this->columnMapping = [];
        foreach (array_merge($requiredFields, $optionalFields) as $field) {
            // Try exact match, then case-insensitive
            $match = collect($headers)->first(fn ($h) => strtolower(trim($h)) === strtolower($field));
            $this->columnMapping[$field] = $match ?? '';
        }

        // Store preview rows (first 5 data rows for display)
        $allLines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $content))));
        array_shift($allLines); // remove header
        $this->previewRows = array_slice(
            array_map(fn ($l) => str_getcsv($l), $allLines),
            0, 5
        );

        // Temporarily store file content for later service call
        session(['import_csv_content' => $content]);
        session(['import_original_filename' => $this->csvFile->getClientOriginalName()]);

        $this->step = 'map';
    }

    public function runValidation(): void
    {
        $this->authorize('create', \App\Models\Product::class);

        $service = app(ProductImportService::class);

        $import = $service->store(
            auth()->user(),
            $this->importType,
            session('import_original_filename', 'import.csv'),
            session('import_csv_content', ''),
            $this->columnMapping
        );

        $import = $service->validate($import);

        $this->importId        = $import->id;
        $this->validationReport = $import->validation_report ?? [];
        $this->totalRows       = $import->total_rows;
        $this->errorRows       = $import->error_rows;

        $this->step = 'preview';
    }

    public function confirmImport(): void
    {
        $this->authorize('create', \App\Models\Product::class);

        if (!$this->importId) {
            return;
        }

        $import = ProductImport::where('id', $this->importId)
            ->where('fulfilment_client_id', auth()->user()->fulfilment_client_id)
            ->firstOrFail();

        $service = app(ProductImportService::class);
        $import = $service->confirm($import);

        $this->resultSummary = $import->summaryLine();
        $this->step = 'done';
    }

    public function restart(): void
    {
        $this->reset();
        $this->step = 'upload';
    }

    public function render()
    {
        return view('livewire.products.import-wizard');
    }
}
