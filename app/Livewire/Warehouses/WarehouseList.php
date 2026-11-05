<?php

namespace App\Livewire\Warehouses;

use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class WarehouseList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';
    public string $filterActive = 'active';

    protected $queryString = ['search', 'filterActive'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()
            ->when($this->search, fn ($q) =>
                $q->where('name', 'like', '%' . $this->search . '%')
            )
            ->when($this->filterActive === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('locations')
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.warehouses.warehouse-list', compact('warehouses'));
    }
}
