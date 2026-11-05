<?php

namespace App\Livewire\Warehouses;

use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class LocationList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public int    $warehouseId;
    public string $search      = '';
    public string $filterZone  = 'all';
    public string $filterActive = 'active';

    protected $queryString = ['search', 'filterZone', 'filterActive'];

    public function updatingSearch(): void { $this->resetPage(); }

    public function mount(int $warehouseId): void
    {
        $this->warehouseId = $warehouseId;
    }

    public function render()
    {
        $warehouse = Warehouse::findOrFail($this->warehouseId);
        $this->authorize('view', $warehouse);

        $locations = Location::query()
            ->where('warehouse_id', $this->warehouseId)
            ->when($this->search, fn ($q) =>
                $q->where(function ($q2) {
                    $q2->where('barcode', 'like', '%' . $this->search . '%')
                       ->orWhere('name',   'like', '%' . $this->search . '%');
                })
            )
            ->when($this->filterZone !== 'all', fn ($q) => $q->where('zone', $this->filterZone))
            ->when($this->filterActive === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('zone')
            ->orderBy('barcode')
            ->paginate(50);

        return view('livewire.warehouses.location-list', [
            'warehouse' => $warehouse,
            'locations' => $locations,
            'zones'     => \App\Models\Location::ZONES,
        ]);
    }
}
