<div class="max-w-3xl">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Import CSV</h1>
        <div class="flex gap-2 text-sm">
            @foreach (['upload' => '1. Upload', 'map' => '2. Map Columns', 'preview' => '3. Preview', 'confirm' => '4. Confirm', 'done' => '5. Done'] as $s => $label)
                <span class="{{ $step === $s ? 'font-bold text-blue-700' : 'text-gray-400' }}">{{ $label }}</span>
                @if (!$loop->last) <span class="text-gray-300">›</span> @endif
            @endforeach
        </div>
    </div>

    {{-- Step 1: Upload --}}
    @if ($step === 'upload')
        <form wire:submit="uploadCsv" class="space-y-5">
            <div>
                <label class="block mb-1 text-sm font-medium">Import Type</label>
                <select wire:model="importType" class="w-full px-3 py-2 text-sm border rounded">
                    <option value="products">Products</option>
                    <option value="variant_mappings">Shopify Variant Mappings</option>
                </select>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium">CSV File</label>
                <input wire:model="csvFile" type="file" accept=".csv,.txt" class="w-full text-sm" />
                @error('csvFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500">
                    @if ($importType === 'products')
                        Required columns: <code>internal_sku, name, unit_of_measure</code>.
                        Optional: <code>scan_code, manufacturer_barcode, pack_qty</code>
                    @else
                        Required columns: <code>shop_domain, variant_gid</code>.
                        Optional: <code>source_sku, source_barcode, shopify_title</code>
                    @endif
                </p>
            </div>

            <div class="p-3 text-sm border rounded bg-blue-50 border-blue-200">
                <strong>No data will be written yet.</strong> The next steps let you review the column mapping and validation report before anything is imported.
            </div>

            <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                Upload &amp; Continue →
            </button>
        </form>
    @endif

    {{-- Step 2: Map Columns --}}
    @if ($step === 'map')
        <div class="space-y-5">
            <p class="text-sm text-gray-600">
                Your CSV has {{ count($csvHeaders) }} columns. Assign each WMS field to the matching CSV column. Leave blank to skip optional fields.
            </p>

            <div class="overflow-x-auto border rounded">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">WMS Field</th>
                            <th class="px-3 py-2 text-left font-medium">CSV Column</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($columnMapping as $field => $assigned)
                            <tr class="border-b">
                                <td class="px-3 py-2 font-mono text-xs font-semibold">{{ $field }}</td>
                                <td class="px-3 py-2">
                                    <select wire:model="columnMapping.{{ $field }}" class="w-full px-2 py-1 text-sm border rounded">
                                        <option value="">— not mapped —</option>
                                        @foreach ($csvHeaders as $header)
                                            <option value="{{ $header }}">{{ $header }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Preview of first 5 rows --}}
            @if (!empty($previewRows))
                <div>
                    <p class="mb-2 text-sm font-medium">First 5 rows of your file:</p>
                    <div class="overflow-x-auto border rounded text-xs">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    @foreach ($csvHeaders as $h)
                                        <th class="px-2 py-1 text-left font-medium">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($previewRows as $row)
                                    <tr class="border-b">
                                        @foreach ($row as $cell)
                                            <td class="px-2 py-1">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="flex gap-3">
                <button wire:click="runValidation" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                    Validate →
                </button>
                <button wire:click="restart" class="px-5 py-2 text-sm text-gray-700 bg-white border rounded hover:bg-gray-50">
                    Start Over
                </button>
            </div>
        </div>
    @endif

    {{-- Step 3: Preview / Validation Report --}}
    @if ($step === 'preview')
        <div class="space-y-5">
            <div class="flex gap-4 text-sm">
                <span class="font-medium">{{ $totalRows }} rows</span>
                <span class="{{ $errorRows > 0 ? 'text-red-700 font-semibold' : 'text-green-700' }}">
                    {{ $errorRows }} error(s)
                </span>
                <span class="text-gray-500">{{ $totalRows - $errorRows }} will import</span>
            </div>

            @if (!empty($validationReport))
                <div class="space-y-2">
                    <p class="text-sm font-medium">Validation Report:</p>
                    @foreach ($validationReport as $rowNum => $issues)
                        <div class="p-3 border rounded {{ isset($issues['errors']) ? 'border-red-300 bg-red-50' : 'border-amber-200 bg-amber-50' }}">
                            <p class="text-xs font-semibold mb-1">Row {{ $rowNum }}</p>
                            @foreach ($issues['errors'] ?? [] as $err)
                                <p class="text-xs text-red-700">✗ {{ $err }}</p>
                            @endforeach
                            @foreach ($issues['warnings'] ?? [] as $warn)
                                <p class="text-xs text-amber-700">⚠ {{ $warn }}</p>
                            @endforeach
                            @if (isset($issues['info']))
                                <p class="text-xs text-blue-700">ℹ {{ $issues['info'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-3 text-sm text-green-800 bg-green-50 border border-green-200 rounded">
                    ✓ No errors found. All {{ $totalRows }} rows are ready to import.
                </div>
            @endif

            @if ($errorRows > 0)
                <div class="p-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded">
                    Rows with errors will be <strong>quarantined</strong> — the import will proceed for valid rows only. Quarantined rows must be corrected and re-imported.
                </div>
            @endif

            <div class="flex gap-3">
                <button wire:click="$set('step', 'confirm')" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                    Continue to Confirm →
                </button>
                <button wire:click="restart" class="px-5 py-2 text-sm text-gray-700 bg-white border rounded hover:bg-gray-50">
                    Start Over
                </button>
            </div>
        </div>
    @endif

    {{-- Step 4: Confirm --}}
    @if ($step === 'confirm')
        <div class="space-y-5">
            <div class="p-4 border-2 border-blue-300 rounded bg-blue-50">
                <p class="text-sm font-semibold mb-2">Ready to import</p>
                <ul class="text-sm space-y-1 list-disc list-inside text-gray-700">
                    <li>{{ $totalRows - $errorRows }} rows will be written to the database.</li>
                    <li>{{ $errorRows }} row(s) with errors will be quarantined.</li>
                    <li>Manual mappings will <strong>not</strong> be overwritten.</li>
                    <li>pack_qty values will be marked as <strong>unverified</strong> until physically confirmed.</li>
                    <li>This action can be retried safely — duplicate rows will be updated, not duplicated.</li>
                </ul>
            </div>

            <div class="flex gap-3">
                <button wire:click="confirmImport" wire:loading.attr="disabled" class="px-5 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700 disabled:opacity-50">
                    <span wire:loading.remove>Confirm Import</span>
                    <span wire:loading>Importing…</span>
                </button>
                <button wire:click="$set('step', 'preview')" class="px-5 py-2 text-sm text-gray-700 bg-white border rounded hover:bg-gray-50">
                    ← Back
                </button>
            </div>
        </div>
    @endif

    {{-- Step 5: Done --}}
    @if ($step === 'done')
        <div class="space-y-5">
            <div class="p-4 text-sm text-green-800 bg-green-50 border border-green-200 rounded">
                ✓ Import complete: {{ $resultSummary }}
            </div>

            <div class="flex gap-3">
                <a href="{{ route('products.index') }}" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                    View Products
                </a>
                <button wire:click="restart" class="px-5 py-2 text-sm text-gray-700 bg-white border rounded hover:bg-gray-50">
                    Import Another File
                </button>
            </div>
        </div>
    @endif

</div>
