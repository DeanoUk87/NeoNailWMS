<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('products.index') }}" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 transition-colors">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-zinc-900">Shopify Variant Mappings</h1>
                <p class="mt-0.5 text-sm text-zinc-500">Link Shopify variants to WMS products. Manual mappings are locked and cannot be overwritten by imports.</p>
            </div>
        </div>
    </div>

    {{-- Alert banners --}}
    @if ($exceptionCount > 0)
        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
            <svg class="size-5 shrink-0 text-red-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <div>
                <p class="text-sm font-semibold text-red-800">{{ $exceptionCount }} variant{{ $exceptionCount === 1 ? '' : 's' }} need attention</p>
                <p class="text-xs text-red-700 mt-0.5">Conflicting or ambiguous mappings — human review required before these variants can be used.</p>
            </div>
        </div>
    @endif

    @if ($unmappedCount > 0)
        <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3.5">
            <svg class="size-4 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
            <p class="text-sm text-amber-800"><strong>{{ $unmappedCount }}</strong> variant{{ $unmappedCount === 1 ? '' : 's' }} not yet mapped to a WMS product.</p>
        </div>
    @endif

    @if (session('success'))
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm text-emerald-800">
            <svg class="size-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search GID, SKU, barcode, title…" class="w-full rounded-lg border border-zinc-300 py-2 pl-9 pr-4 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
        </div>
        <select wire:model.live="filterStatus" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="all">All statuses</option>
            <option value="exception">Exceptions</option>
            <option value="unmapped">Unmapped</option>
            <option value="pending_review">Pending review</option>
            <option value="mapped">Mapped</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 bg-zinc-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Variant</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Source SKU / Barcode</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Mapped to</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Provenance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($variants as $variant)
                    <tr class="hover:bg-indigo-50/30 transition-colors {{ $variant->isException() ? 'bg-red-50/60' : '' }}">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-zinc-900">{{ $variant->shopify_title ?? '—' }}</p>
                            <p class="mt-0.5 font-mono text-xs text-zinc-400" title="{{ $variant->variant_gid }}">
                                …{{ substr($variant->variant_gid, -12) }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-mono text-xs text-zinc-700">{{ $variant->source_sku ?? '—' }}</p>
                            <p class="font-mono text-xs text-zinc-400">{{ $variant->source_barcode ?? '' }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            @php
                                $badge = match($variant->mapping_status) {
                                    'mapped'         => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                    'exception'      => 'bg-red-50 text-red-700 ring-red-600/20',
                                    'pending_review' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                    default          => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badge }}">
                                {{ $variant->mapping_status }}
                            </span>
                            @if ($variant->exception_reason)
                                <p class="mt-1 text-xs text-red-600 max-w-xs">{{ $variant->exception_reason }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($variant->product)
                                <span class="font-mono text-xs font-bold text-zinc-800 bg-zinc-100 px-2 py-0.5 rounded">{{ $variant->product->internal_sku }}</span>
                                <p class="mt-0.5 text-xs text-zinc-500">{{ $variant->product->name }}</p>
                            @else
                                <span class="text-zinc-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($variant->isManuallymapped())
                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20" title="Manual mappings are never overwritten by CSV imports">
                                    <svg class="size-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                    manual
                                </span>
                            @else
                                <span class="text-xs text-zinc-400">csv import</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center">
                            <svg class="mx-auto size-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            <p class="mt-2 text-sm text-zinc-400">No variants found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $variants->links() }}</div>

</div>
