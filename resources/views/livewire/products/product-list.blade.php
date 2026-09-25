<div class="space-y-6">

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900">Products</h1>
            <p class="mt-1 text-sm text-zinc-500">SKU catalogue for {{ auth()->user()->fulfilmentClient->name ?? 'your client' }}</p>
        </div>
        <div class="flex gap-3">
            @can('create', App\Models\Product::class)
                <a href="{{ route('products.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Product
                </a>
                <a href="{{ route('products.import') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import CSV
                </a>
            @endcan
            <a href="{{ route('products.mappings') }}" class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50 transition-colors">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                Variant Mappings
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <svg class="size-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Search and filter card --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="flex gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Search SKU, name, scan code…"
                    class="w-full rounded-lg border border-zinc-300 py-2 pl-9 pr-4 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
            </div>
            <select wire:model.live="filterActive" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">All products</option>
                <option value="active">Active only</option>
                <option value="inactive">Inactive only</option>
            </select>
        </div>
    </div>

    {{-- Products table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 bg-zinc-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">SKU</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Name</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">UOM / Pack</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Scan Code</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Mfr Barcode</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($products as $product)
                    <tr class="group hover:bg-indigo-50/40 transition-colors">
                        <td class="px-5 py-3.5">
                            <span class="font-mono text-xs font-bold text-zinc-800 bg-zinc-100 px-2 py-1 rounded">{{ $product->internal_sku }}</span>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-zinc-900">{{ $product->name }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-zinc-700">{{ $product->unit_of_measure }}</span>
                            @if ($product->pack_qty)
                                <br>
                                <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold {{ $product->pack_qty_confirmed ? 'text-emerald-700' : 'text-amber-600' }}">
                                    @if (!$product->pack_qty_confirmed)
                                        <svg class="size-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                    @endif
                                    {{ $product->packLabel() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 font-mono text-xs text-zinc-500">{{ $product->scan_code ?? '—' }}</td>
                        <td class="px-5 py-3.5 font-mono text-xs text-zinc-500">{{ $product->manufacturer_barcode ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            @if ($product->is_active)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-semibold text-zinc-500 ring-1 ring-inset ring-zinc-500/20">Inactive</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            @can('update', $product)
                                <a href="{{ route('products.edit', $product) }}" class="rounded-md px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                                    Edit
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <svg class="mx-auto size-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <p class="mt-2 text-sm text-zinc-400">No products found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $products->links() }}</div>

</div>
