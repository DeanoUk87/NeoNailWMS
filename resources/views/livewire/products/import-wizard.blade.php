<div class="max-w-3xl space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('products.index') }}" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 transition-colors">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-zinc-900">Import CSV</h1>
            <p class="mt-0.5 text-sm text-zinc-500">No data is written until you click Confirm Import in step 4.</p>
        </div>
    </div>

    {{-- Progress steps --}}
    <div class="flex items-center gap-2">
        @php
            $steps = ['upload' => '1. Upload', 'map' => '2. Map Columns', 'preview' => '3. Validate', 'confirm' => '4. Confirm', 'done' => '5. Done'];
            $stepKeys = array_keys($steps);
            $currentIndex = array_search($step, $stepKeys);
        @endphp
        @foreach ($steps as $key => $label)
            @php $idx = array_search($key, $stepKeys); @endphp
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition-colors
                    {{ $step === $key ? 'bg-indigo-600 text-white' : ($idx < $currentIndex ? 'bg-indigo-100 text-indigo-700' : 'bg-zinc-100 text-zinc-400') }}">
                    @if ($idx < $currentIndex)
                        <svg class="size-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    @endif
                    {{ $label }}
                </div>
                @if (!$loop->last)
                    <svg class="size-4 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Step 1: Upload --}}
    @if ($step === 'upload')
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <form wire:submit="uploadCsv" class="space-y-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Import Type</label>
                    <select wire:model="importType" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="products">Products</option>
                        <option value="variant_mappings">Shopify Variant Mappings</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">CSV File</label>
                    <input wire:model="csvFile" type="file" accept=".csv,.txt" class="w-full text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                    @error('csvFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-2 text-xs text-zinc-400">
                        @if ($importType === 'products')
                            Required columns: <code class="bg-zinc-100 px-1 rounded">internal_sku, name, unit_of_measure</code> &nbsp;·&nbsp; Optional: <code class="bg-zinc-100 px-1 rounded">scan_code, manufacturer_barcode, pack_qty</code>
                        @else
                            Required columns: <code class="bg-zinc-100 px-1 rounded">shop_domain, variant_gid</code> &nbsp;·&nbsp; Optional: <code class="bg-zinc-100 px-1 rounded">source_sku, source_barcode, shopify_title</code>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
                    <svg class="size-4 shrink-0 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
                    <p class="text-xs text-indigo-700 font-medium">No data will be written yet. Review and confirm in steps 3 and 4.</p>
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    Upload &amp; Continue →
                </button>
            </form>
        </div>
    @endif

    {{-- Step 2: Map Columns --}}
    @if ($step === 'map')
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <p class="text-sm text-zinc-600">Assign CSV columns to WMS fields. Leave optional fields blank to skip them.</p>
            <div class="overflow-hidden rounded-lg border border-zinc-200">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-zinc-200 bg-zinc-50"><th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">WMS Field</th><th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">CSV Column</th></tr></thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($columnMapping as $field => $assigned)
                            <tr>
                                <td class="px-4 py-2.5 font-mono text-xs font-semibold text-zinc-700">{{ $field }}</td>
                                <td class="px-4 py-2.5">
                                    <select wire:model="columnMapping.{{ $field }}" class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
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
            @if (!empty($previewRows))
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">First 5 rows of your file</p>
                    <div class="overflow-x-auto rounded-lg border border-zinc-200">
                        <table class="w-full text-xs">
                            <thead><tr class="border-b border-zinc-200 bg-zinc-50">@foreach ($csvHeaders as $h)<th class="px-3 py-2 text-left font-semibold text-zinc-500">{{ $h }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-zinc-100">@foreach ($previewRows as $row)<tr>@foreach ($row as $cell)<td class="px-3 py-2 text-zinc-700">{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
                        </table>
                    </div>
                </div>
            @endif
            <div class="flex gap-3">
                <button wire:click="runValidation" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">Validate →</button>
                <button wire:click="restart" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition-colors">Start Over</button>
            </div>
        </div>
    @endif

    {{-- Step 3: Validation Preview --}}
    @if ($step === 'preview')
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-center gap-6">
                <div class="text-center"><p class="text-2xl font-bold text-zinc-900">{{ $totalRows }}</p><p class="text-xs text-zinc-400">Total rows</p></div>
                <div class="text-center"><p class="text-2xl font-bold {{ $errorRows > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $errorRows }}</p><p class="text-xs text-zinc-400">Errors</p></div>
                <div class="text-center"><p class="text-2xl font-bold text-emerald-600">{{ $totalRows - $errorRows }}</p><p class="text-xs text-zinc-400">Will import</p></div>
            </div>
            @if (!empty($validationReport))
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Validation Report</p>
                    @foreach ($validationReport as $rowNum => $issues)
                        <div class="rounded-lg border p-3 {{ isset($issues['errors']) ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}">
                            <p class="mb-1 text-xs font-bold text-zinc-600">Row {{ $rowNum }}</p>
                            @foreach ($issues['errors'] ?? [] as $err)<p class="text-xs text-red-700">✗ {{ $err }}</p>@endforeach
                            @foreach ($issues['warnings'] ?? [] as $warn)<p class="text-xs text-amber-700">⚠ {{ $warn }}</p>@endforeach
                            @if (isset($issues['info']))<p class="text-xs text-indigo-700">ℹ {{ $issues['info'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <svg class="size-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    <p class="text-sm text-emerald-700 font-medium">No errors — all {{ $totalRows }} rows are ready to import.</p>
                </div>
            @endif
            @if ($errorRows > 0)
                <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <svg class="size-4 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    <p class="text-xs text-amber-700">Rows with errors will be <strong>quarantined</strong>. Valid rows proceed; errored rows must be corrected and re-imported.</p>
                </div>
            @endif
            <div class="flex gap-3">
                <button wire:click="$set('step', 'confirm')" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">Continue →</button>
                <button wire:click="restart" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition-colors">Start Over</button>
            </div>
        </div>
    @endif

    {{-- Step 4: Confirm --}}
    @if ($step === 'confirm')
        <div class="rounded-xl border-2 border-indigo-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-center gap-3">
                <div class="rounded-full bg-indigo-100 p-2"><svg class="size-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <p class="text-sm font-semibold text-zinc-900">Ready to import {{ $totalRows - $errorRows }} rows</p>
            </div>
            <ul class="space-y-2 text-sm text-zinc-600">
                <li class="flex items-center gap-2"><svg class="size-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> {{ $errorRows }} error row(s) will be quarantined, not imported</li>
                <li class="flex items-center gap-2"><svg class="size-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Manual mappings will not be overwritten</li>
                <li class="flex items-center gap-2"><svg class="size-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Pack quantities marked <strong>unverified</strong> until physically confirmed</li>
                <li class="flex items-center gap-2"><svg class="size-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Safe to retry — duplicates updated, not created</li>
            </ul>
            <div class="flex gap-3">
                <button wire:click="confirmImport" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors disabled:opacity-50">
                    <span wire:loading.remove>Confirm Import</span>
                    <span wire:loading>Importing…</span>
                </button>
                <button wire:click="$set('step', 'preview')" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition-colors">← Back</button>
            </div>
        </div>
    @endif

    {{-- Step 5: Done --}}
    @if ($step === 'done')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full bg-emerald-100 p-2"><svg class="size-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg></div>
                <div>
                    <p class="text-sm font-semibold text-emerald-900">Import complete</p>
                    <p class="text-xs text-emerald-700 mt-0.5">{{ $resultSummary }}</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('products.index') }}" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">View Products</a>
                <button wire:click="restart" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition-colors">Import Another</button>
            </div>
        </div>
    @endif

</div>
