<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold">Shopify Variant Mappings</h1>
            <p class="mt-1 text-sm text-gray-500">Link Shopify product variants to WMS products.</p>
        </div>
        <a href="{{ route('products.index') }}" class="text-sm text-blue-600 hover:underline">← Products</a>
    </div>

    {{-- Exception / unmapped banners --}}
    @if ($exceptionCount > 0)
        <div class="p-3 mb-4 text-sm text-red-800 bg-red-100 rounded border border-red-200">
            ⚠ <strong>{{ $exceptionCount }} variant(s) need attention</strong> — conflicting or ambiguous mappings in the exception list below.
        </div>
    @endif
    @if ($unmappedCount > 0)
        <div class="p-3 mb-4 text-sm text-amber-800 bg-amber-50 rounded border border-amber-200">
            {{ $unmappedCount }} variant(s) are unmapped.
        </div>
    @endif

    @if (session('success'))
        <div class="p-3 mb-4 text-sm text-green-800 bg-green-100 rounded">{{ session('success') }}</div>
    @endif

    {{-- Filters --}}
    <div class="flex gap-3 mb-4">
        <input
            wire:model.live.debounce.300ms="search"
            type="text"
            placeholder="Search GID, SKU, barcode, title…"
            class="w-full px-3 py-2 text-sm border rounded"
        />
        <select wire:model.live="filterStatus" class="px-3 py-2 text-sm border rounded">
            <option value="all">All statuses</option>
            <option value="exception">Exceptions</option>
            <option value="unmapped">Unmapped</option>
            <option value="pending_review">Pending review</option>
            <option value="mapped">Mapped</option>
        </select>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left bg-gray-50 border-b">
                    <th class="px-3 py-2 font-medium">Variant GID</th>
                    <th class="px-3 py-2 font-medium">Shopify Title</th>
                    <th class="px-3 py-2 font-medium">Source SKU</th>
                    <th class="px-3 py-2 font-medium">Source Barcode</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Mapped To</th>
                    <th class="px-3 py-2 font-medium">Provenance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($variants as $variant)
                    <tr class="border-b hover:bg-gray-50 {{ $variant->isException() ? 'bg-red-50' : '' }}">
                        <td class="px-3 py-2 font-mono text-xs max-w-xs truncate" title="{{ $variant->variant_gid }}">
                            {{ Str::after($variant->variant_gid, 'ProductVariant/') ?: $variant->variant_gid }}
                        </td>
                        <td class="px-3 py-2">{{ $variant->shopify_title ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $variant->source_sku ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $variant->source_barcode ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @php
                                $statusColour = match($variant->mapping_status) {
                                    'mapped'         => 'text-green-700 bg-green-100',
                                    'exception'      => 'text-red-700 bg-red-100 font-semibold',
                                    'pending_review' => 'text-amber-700 bg-amber-100',
                                    default          => 'text-gray-600 bg-gray-100',
                                };
                            @endphp
                            <span class="px-2 py-0.5 text-xs rounded-full {{ $statusColour }}">
                                {{ $variant->mapping_status }}
                            </span>
                            @if ($variant->exception_reason)
                                <p class="mt-1 text-xs text-red-600">{{ $variant->exception_reason }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($variant->product)
                                <span class="font-mono text-xs">{{ $variant->product->internal_sku }}</span>
                                <br>
                                <span class="text-xs text-gray-500">{{ $variant->product->name }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($variant->isManuallymapped())
                                <span class="px-2 py-0.5 text-xs text-blue-700 bg-blue-100 rounded-full" title="Manual mappings are never overwritten by CSV imports">
                                    manual 🔒
                                </span>
                            @else
                                <span class="text-xs text-gray-400">csv import</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-400">No variants found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $variants->links() }}
    </div>
</div>
