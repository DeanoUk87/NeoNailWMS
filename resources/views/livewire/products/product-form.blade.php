<div class="max-w-2xl">
    <h1 class="mb-6 text-xl font-semibold">
        {{ $productId ? 'Edit Product' : 'New Product' }}
    </h1>

    <form wire:submit="save" class="space-y-5">

        <div>
            <label class="block mb-1 text-sm font-medium">Internal SKU <span class="text-red-500">*</span></label>
            <input wire:model="internal_sku" type="text" class="w-full px-3 py-2 text-sm border rounded @error('internal_sku') border-red-500 @enderror" />
            @error('internal_sku') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-sm font-medium">Product Name <span class="text-red-500">*</span></label>
            <input wire:model="name" type="text" class="w-full px-3 py-2 text-sm border rounded @error('name') border-red-500 @enderror" />
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1 text-sm font-medium">
                    Unit of Measure <span class="text-red-500">*</span>
                    <span class="font-normal text-gray-500">(e.g. each, pack, case)</span>
                </label>
                <input wire:model="unit_of_measure" type="text" class="w-full px-3 py-2 text-sm border rounded @error('unit_of_measure') border-red-500 @enderror" />
                @error('unit_of_measure') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium">Pack Quantity</label>
                <input wire:model="pack_qty" type="number" min="1" placeholder="Leave blank if not applicable" class="w-full px-3 py-2 text-sm border rounded @error('pack_qty') border-red-500 @enderror" />
                @error('pack_qty') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Pack qty confirmed — prominently labelled to avoid silently treating a pack as one item --}}
        <div class="p-3 border rounded bg-amber-50 border-amber-200">
            <label class="flex items-start gap-2 cursor-pointer">
                <input wire:model="pack_qty_confirmed" type="checkbox" class="mt-0.5" />
                <span class="text-sm">
                    <strong>Pack quantity physically verified</strong>
                    <br>
                    <span class="text-gray-600">Only tick this after NeoNail has physically counted and confirmed the pack size. Until confirmed, the system treats pack quantity as unverified and will not use it to calculate individual item counts.</span>
                </span>
            </label>
        </div>

        <div>
            <label class="block mb-1 text-sm font-medium">Scan Code (internal label)</label>
            <input wire:model="scan_code" type="text" placeholder="Internal barcode — use if no manufacturer barcode" class="w-full px-3 py-2 text-sm border rounded @error('scan_code') border-red-500 @enderror" />
            @error('scan_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-sm font-medium">Manufacturer Barcode (EAN/UPC)</label>
            <input wire:model="manufacturer_barcode" type="text" placeholder="Leave blank if the item has no manufacturer barcode" class="w-full px-3 py-2 text-sm border rounded @error('manufacturer_barcode') border-red-500 @enderror" />
            @error('manufacturer_barcode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-gray-500">Not required — many items do not have an EAN.</p>
        </div>

        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input wire:model="is_active" type="checkbox" />
                <span class="text-sm font-medium">Active</span>
            </label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                Save Product
            </button>
            <a href="{{ route('products.index') }}" class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border rounded hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>
