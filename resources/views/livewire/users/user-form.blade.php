<div class="max-w-2xl space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('users.index') }}" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 transition-colors">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-zinc-900">{{ $mode === 'create' ? 'New User' : 'Edit User' }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ $mode === 'create' ? 'Create a new warehouse staff account.' : 'Update account details and permissions.' }}</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-5">

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Account Details</h2>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Full Name <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" placeholder="e.g. Jane Smith" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Email Address <span class="text-red-500">*</span></label>
                    <input wire:model="email" type="email" placeholder="jane@example.com" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">Role <span class="text-red-500">*</span></label>
                    <select wire:model="role" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="operator">Operator — view products, receive instructions</option>
                        <option value="supervisor">Supervisor — approve exceptions, view reports</option>
                        <option value="admin">Admin — full access including user management</option>
                    </select>
                    @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-semibold text-zinc-700">
                        {{ $mode === 'create' ? 'Initial Password' : 'New Password' }}
                        @if ($mode === 'create') <span class="text-red-500">*</span> @endif
                        @if ($mode === 'edit') <span class="text-zinc-400 font-normal text-xs">(leave blank to keep current)</span> @endif
                    </label>
                    <input wire:model="newPassword" type="password" placeholder="{{ $mode === 'create' ? 'Minimum 8 characters' : 'Leave blank to keep unchanged' }}" autocomplete="new-password" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 {{ $errors->has('newPassword') ? 'border-red-400 bg-red-50' : 'border-zinc-300' }}" />
                    @error('newPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($mode === 'edit')
                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input wire:model="is_active" type="checkbox" class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm font-semibold text-zinc-700">Account active</span>
                    </label>
                    <p class="mt-1 text-xs text-zinc-400 ml-7">Inactive accounts cannot log in. The account history is preserved.</p>
                </div>
            @endif
        </div>

        @if ($mode === 'edit')
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-400">Password Reset</h2>
                <p class="text-sm text-zinc-500 mb-3">Send a password reset link to the user's email address. They will receive a secure link to set a new password. No password is revealed.</p>
                <button wire:click.prevent="sendPasswordReset" type="button" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition-colors">
                    Send Password Reset Email
                </button>
            </div>
        @endif

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                {{ $mode === 'create' ? 'Create User' : 'Save Changes' }}
            </button>
            <a href="{{ route('users.index') }}" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50 transition-colors">Cancel</a>
        </div>
    </form>
</div>
