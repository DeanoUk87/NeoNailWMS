<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouses.index') }}" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 transition-colors">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-zinc-900">{{ $warehouse->name }} — Locations</h1>
                <p class="mt-0.5 text-sm text-zinc-500">Manage bin, shelf and zone locations.</p>
            </div>
        </div>
        @can('create', App\Models\Location::class)
            <a href="{{ route('warehouses.locations.create', $warehouse) }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Location
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <svg class="size-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="flex gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
            <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search barcode or name…" class="w-full rounded-lg border border-zinc-300 py-2 pl-9 pr-4 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
        </div>
        <select wire:model.live="filterZone" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="all">All zones</option>
            @foreach ($zones as $code => $label)
                <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterActive" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="all">All</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 bg-zinc-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Barcode / Reference</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Name</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Zone</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Type</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Capacity</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($locations as $location)
                    <tr class="hover:bg-indigo-50/30 transition-colors">
                        <td class="px-5 py-3.5">
                            <span class="font-mono text-xs font-bold text-zinc-800 bg-zinc-100 px-2 py-1 rounded">{{ $location->barcode }}</span>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-zinc-900">{{ $location->name }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $location->is_eligible_for_available_stock ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20' }}">
                                {{ $location->zone }} — {{ $location->zoneName() }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-zinc-500 capitalize">{{ $location->location_type }}</td>
                        <td class="px-5 py-3.5 text-zinc-500">{{ $location->capacity_units ? $location->capacity_units . ' units' : '—' }}</td>
                        <td class="px-5 py-3.5">
                            @if ($location->is_active)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-semibold text-zinc-500 ring-1 ring-inset ring-zinc-500/20">Inactive</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            @can('update', $location)
                                <a href="{{ route('warehouses.locations.edit', [$warehouse, $location]) }}" class="rounded-md px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <svg class="mx-auto size-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <p class="mt-2 text-sm text-zinc-400">No locations yet. <a href="{{ route('warehouses.locations.create', $warehouse) }}" class="text-indigo-600 hover:underline">Add the first location.</a></p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $locations->links() }}</div>
</div>
