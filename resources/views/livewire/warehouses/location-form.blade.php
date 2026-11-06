<div class="max-w-2xl space-y-6">
    <div class="flex items-center gap-4">
        <a wire:navigate href="{{ route('warehouses.locations', $warehouseId) }}" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 transition-colors">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-zinc-900">{{ $locationId ? 'Edit Location' : 'New Location' }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ $locationId ? 'Update location details.' : 'Add a bin, shelf or zone location.' }}</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-5">

        {{-- Identity card --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Location Identity</h2>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Zone <span class="text-red-500">*</span></label>
                    <select wire:model="zone" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('zone') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}">
                        @foreach ($zones as $code => $label)
                            <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                        @endforeach
                    </select>
                    @error('zone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Location Type <span class="text-red-500">*</span></label>
                    <select wire:model="location_type" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @foreach ($locationTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Section / Rack</label>
                    <input wire:model="section" wire:change="generateBarcode" type="text" placeholder="01" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Shelf Level</label>
                    <input wire:model="shelf" wire:change="generateBarcode" type="text" placeholder="02" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Bin / Position</label>
                    <input wire:model="bin_position" wire:change="generateBarcode" type="text" placeholder="03" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">
                        Barcode / Reference <span class="text-red-500">*</span>
                        <span class="text-zinc-400 font-normal text-xs">(auto-generated or enter manually)</span>
                    </label>
                    <input wire:model="barcode" type="text" placeholder="e.g. A01-S02-B03" class="w-full rounded-lg border px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('barcode') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('barcode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-zinc-400">Must be unique within this warehouse. This is what the scanner reads.</p>
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Display Name <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" placeholder="e.g. Ambient Rack 1 Shelf 2 Bin 3" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Capacity and notes --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Capacity & Notes</h2>
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Maximum Capacity (units)</label>
                    <input wire:model="capacity_units" type="number" min="1" placeholder="Leave blank if unconfigured" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    <p class="mt-1 text-xs text-zinc-400">Optional. Used by the system to warn when a bin is full.</p>
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Notes</label>
                    <textarea wire:model="notes" rows="2" placeholder="Any notes about this location" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input wire:model="is_active" type="checkbox" class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                <span class="text-sm font-semibold text-zinc-700">Active</span>
            </label>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">Save Location</button>
            <a wire:navigate href="{{ route('warehouses.locations', $warehouseId) }}" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50 transition-colors">Cancel</a>
        </div>
    </form>
</div>
