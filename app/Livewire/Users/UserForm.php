<?php

namespace App\Livewire\Users;

use App\Models\User;
use App\Models\UserAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserForm extends Component
{
    use AuthorizesRequests;

    public ?int $userId = null;

    public string $name     = '';
    public string $email    = '';
    public string $role     = 'operator';
    public bool   $is_active = true;
    public string $newPassword = '';
    public string $resetMessage = '';

    // Editing mode: 'create' or 'edit'
    public string $mode = 'create';

    protected function rules(): array
    {
        $rules = [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'role'  => ['required', Rule::in(['admin', 'supervisor', 'operator'])],
            'is_active' => ['boolean'],
        ];

        if ($this->mode === 'create') {
            $rules['newPassword'] = ['required', 'string', 'min:8'];
        } else {
            $rules['newPassword'] = ['nullable', 'string', 'min:8'];
        }

        return $rules;
    }

    public function mount(?int $userId = null): void
    {
        if ($userId) {
            $target = User::findOrFail($userId);
            $this->authorize('update', $target);
            $this->mode    = 'edit';
            $this->userId  = $userId;
            $this->name    = $target->name;
            $this->email   = $target->email;
            $this->role    = $target->role;
            $this->is_active = !is_null($target->email_verified_at);
        } else {
            $this->authorize('create', User::class);
            $this->mode = 'create';
        }
    }

    public function save(): void
    {
        $this->validate();

        $actor = auth()->user();

        if ($this->mode === 'create') {
            $this->authorize('create', User::class);

            $user = new User();
            $user->forceFill([
                'name'                 => $this->name,
                'email'                => $this->email,
                'password'             => Hash::make($this->newPassword),
                'role'                 => $this->role,
                'fulfilment_client_id' => $actor->fulfilment_client_id,
                'email_verified_at'    => now(), // Admin-provisioned users are pre-verified
            ])->save();

            UserAuditLog::record($user, $actor, 'created', [
                'role' => $this->role,
            ]);

            session()->flash('success', "User {$user->name} created.");
        } else {
            $target = User::findOrFail($this->userId);
            $this->authorize('update', $target);

            $before = ['role' => $target->role, 'name' => $target->name, 'email' => $target->email];

            $target->forceFill([
                'name'  => $this->name,
                'email' => $this->email,
                'role'  => $this->role,
                'email_verified_at' => $this->is_active ? ($target->email_verified_at ?? now()) : null,
            ]);

            if ($this->newPassword) {
                $target->forceFill(['password' => Hash::make($this->newPassword)]);
            }

            $target->save();

            $after   = ['role' => $target->role, 'name' => $target->name, 'email' => $target->email];
            $changes = array_filter(array_map(null, array_keys($before), $before, $after),
                fn ($item) => $item[1] !== $item[2]
            );

            UserAuditLog::record($target, $actor, 'updated', [
                'before' => $before,
                'after'  => $after,
            ]);

            session()->flash('success', "User {$target->name} updated.");
        }

        $this->redirect(route('users.index'));
    }

    /**
     * Send a password reset link to the user's email.
     * Uses Laravel's built-in Password broker — no password is exposed.
     */
    public function sendPasswordReset(): void
    {
        if (!$this->userId) return;
        $target = User::findOrFail($this->userId);
        $this->authorize('update', $target);

        Password::sendResetLink(['email' => $target->email]);

        UserAuditLog::record($target, auth()->user(), 'password_reset_sent');

        $this->resetMessage = "Password reset link sent to {$target->email}.";
    }

    public function render()
    {
        return view('livewire.users.user-form');
    }
}
