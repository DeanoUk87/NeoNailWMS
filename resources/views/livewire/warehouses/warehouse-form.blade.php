<div class="max-w-2xl space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('warehouses.index') }}" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 transition-colors">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-zinc-900">{{ $warehouseId ? 'Edit Warehouse' : 'New Warehouse' }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ $warehouseId ? 'Update warehouse details.' : 'Add a physical warehouse location.' }}</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-5">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <div>
                <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Warehouse Name <span class="text-red-500">*</span></label>
                <input wire:model="name" type="text" placeholder="e.g. MP Transport — Main Warehouse" class="w-full rounded-lg border px-3 py-2 text-sm transition-colors focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Address</label>
                <input wire:model="address" type="text" placeholder="Street address (optional)" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Notes</label>
                <textarea wire:model="notes" rows="3" placeholder="Any relevant notes about this warehouse" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>
            </div>
            <div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="is_active" type="checkbox" class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm font-semibold text-zinc-700">Active</span>
                </label>
            </div>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">Save Warehouse</button>
            <a href="{{ route('warehouses.index') }}" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50 transition-colors">Cancel</a>
        </div>
    </form>
</div>
