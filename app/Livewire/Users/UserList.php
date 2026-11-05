<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class UserList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search      = '';
    public string $filterRole  = 'all';
    public string $filterActive = 'active';

    protected $queryString = ['search', 'filterRole', 'filterActive'];

    public function updatingSearch(): void { $this->resetPage(); }

    public function render()
    {
        $this->authorize('viewAny', User::class);

        $clientId = auth()->user()->fulfilment_client_id;

        $users = User::query()
            ->where('fulfilment_client_id', $clientId)
            ->when($this->search, fn ($q) =>
                $q->where(function ($q2) {
                    $q2->where('name',  'like', '%' . $this->search . '%')
                       ->orWhere('email','like', '%' . $this->search . '%');
                })
            )
            ->when($this->filterRole !== 'all', fn ($q) => $q->where('role', $this->filterRole))
            ->when($this->filterActive === 'active',   fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($this->filterActive === 'inactive', fn ($q) => $q->whereNull('email_verified_at'))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.users.user-list', compact('users'));
    }
}
