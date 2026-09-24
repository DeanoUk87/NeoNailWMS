<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Products</h1>
        <div class="flex gap-3">
            @can('create', App\Models\Product::class)
                <a href="{{ route('products.create') }}" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                    + New Product
                </a>
                <a href="{{ route('products.import') }}" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700">
                    Import CSV
                </a>
            @endcan
            <a href="{{ route('products.mappings') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded hover:bg-gray-50">
                Variant Mappings
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="p-3 mb-4 text-sm text-green-800 bg-green-100 rounded">{{ session('success') }}</div>
    @endif

    {{-- Search and filter --}}
    <div class="flex gap-3 mb-4">
        <input
            wire:model.live.debounce.300ms="search"
            type="text"
            placeholder="Search SKU, name, scan code…"
            class="w-full px-3 py-2 text-sm border rounded"
        />
        <select wire:model.live="filterActive" class="px-3 py-2 text-sm border rounded">
            <option value="all">All</option>
            <option value="active">Active only</option>
            <option value="inactive">Inactive only</option>
        </select>
    </div>

    {{-- Product table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left bg-gray-50 border-b">
                    <th class="px-3 py-2 font-medium">SKU</th>
                    <th class="px-3 py-2 font-medium">Name</th>
                    <th class="px-3 py-2 font-medium">UOM / Pack</th>
                    <th class="px-3 py-2 font-medium">Scan Code</th>
                    <th class="px-3 py-2 font-medium">Mfr Barcode</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono font-semibold">{{ $product->internal_sku }}</td>
                        <td class="px-3 py-2">{{ $product->name }}</td>
                        <td class="px-3 py-2">
                            {{-- Pack info is shown prominently so pickers cannot mistake a pack for a single item --}}
                            <span class="font-medium">{{ $product->unit_of_measure }}</span>
                            @if ($product->pack_qty)
                                <br>
                                <span class="text-xs {{ $product->pack_qty_confirmed ? 'text-green-700' : 'text-amber-700 font-semibold' }}">
                                    {{ $product->packLabel() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $product->scan_code ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $product->manufacturer_barcode ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @if ($product->is_active)
                                <span class="px-2 py-0.5 text-xs text-green-800 bg-green-100 rounded-full">Active</span>
                            @else
                                <span class="px-2 py-0.5 text-xs text-gray-500 bg-gray-100 rounded-full">Inactive</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            @can('update', $product)
                                <a href="{{ route('products.edit', $product) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-400">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $products->links() }}
    </div>
</div>
