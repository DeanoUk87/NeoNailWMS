<div class="max-w-2xl space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('products.index') }}" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 transition-colors">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-zinc-900">{{ $productId ? 'Edit Product' : 'New Product' }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ $productId ? 'Update product details and pack configuration.' : 'Add a new SKU to the catalogue.' }}</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Identity card --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Product Identity</h2>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Internal SKU <span class="text-red-500">*</span></label>
                    <input wire:model="internal_sku" type="text" class="w-full rounded-lg border px-3 py-2 text-sm transition-colors focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('internal_sku') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('internal_sku') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Product Name <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" class="w-full rounded-lg border px-3 py-2 text-sm transition-colors focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Scan Code <span class="text-zinc-400 font-normal">(internal label)</span></label>
                    <input wire:model="scan_code" type="text" placeholder="Use if no manufacturer barcode" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    @error('scan_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Manufacturer Barcode <span class="text-zinc-400 font-normal">(EAN/UPC)</span></label>
                    <input wire:model="manufacturer_barcode" type="text" placeholder="Leave blank if none" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    <p class="mt-1 text-xs text-zinc-400">Not required — many items have no EAN.</p>
                    @error('manufacturer_barcode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Unit & Pack card --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Unit of Measure & Pack</h2>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Unit of Measure <span class="text-red-500">*</span></label>
                    <input wire:model="unit_of_measure" type="text" placeholder="each / pack / case" class="w-full rounded-lg border px-3 py-2 text-sm transition-colors focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('unit_of_measure') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('unit_of_measure') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Pack Quantity</label>
                    <input wire:model="pack_qty" type="number" min="1" placeholder="Leave blank if not a pack" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    @error('pack_qty') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Pack confirmation — prominent warning box --}}
            <div class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input wire:model="pack_qty_confirmed" type="checkbox" class="mt-0.5 size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <p class="text-sm font-semibold text-amber-900">Pack quantity physically verified</p>
                        <p class="mt-0.5 text-xs text-amber-700">Only tick this after NeoNail has physically counted and confirmed the pack size. Until confirmed, the system marks the quantity as <strong>unverified</strong> and will never use it to calculate individual item counts. A pack is never silently treated as a single item.</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Status card --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Status</h2>
            <label class="flex items-center gap-3 cursor-pointer">
                <input wire:model="is_active" type="checkbox" class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                <div>
                    <p class="text-sm font-semibold text-zinc-700">Active</p>
                    <p class="text-xs text-zinc-400">Inactive products are hidden from pickers but preserved in the database.</p>
                </div>
            </label>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Save Product
            </button>
            <a href="{{ route('products.index') }}" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50 transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>
