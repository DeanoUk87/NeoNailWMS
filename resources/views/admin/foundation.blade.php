<x-layouts::app :title="'Foundation'">
    <div class="space-y-4">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="rounded-full bg-emerald-100 p-2">
                    <svg class="size-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                    <p class="text-lg font-bold text-emerald-900">Foundation OK</p>
                    <p class="text-sm text-emerald-700">Application stack is healthy.</p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-3">
            <div class="flex items-center justify-between py-2 border-b border-zinc-100">
                <span class="text-sm text-zinc-500">Application</span>
                <span class="text-sm font-semibold text-zinc-900">{{ $appName }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-zinc-500">Authenticated user</span>
                <span class="text-sm font-semibold text-zinc-900">{{ $userEmail }}</span>
            </div>
        </div>
    </div>
</x-layouts::app>
