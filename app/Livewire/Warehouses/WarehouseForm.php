<?php

namespace App\Livewire\Warehouses;

use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class WarehouseForm extends Component
{
    use AuthorizesRequests;

    public ?int $warehouseId = null;

    public string $name    = '';
    public string $address = '';
    public string $notes   = '';
    public bool   $is_active = true;

    protected function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'address'   => ['nullable', 'string', 'max:255'],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function mount(?int $warehouseId = null): void
    {
        $this->warehouseId = $warehouseId;

        if ($warehouseId) {
            $warehouse = Warehouse::findOrFail($warehouseId);
            $this->authorize('update', $warehouse);
            $this->name      = $warehouse->name;
            $this->address   = $warehouse->address ?? '';
            $this->notes     = $warehouse->notes   ?? '';
            $this->is_active = $warehouse->is_active;
        } else {
            $this->authorize('create', Warehouse::class);
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'fulfilment_client_id' => auth()->user()->fulfilment_client_id,
            'name'      => $this->name,
            'address'   => $this->address ?: null,
            'notes'     => $this->notes   ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->warehouseId) {
            $warehouse = Warehouse::findOrFail($this->warehouseId);
            $this->authorize('update', $warehouse);
            $warehouse->update($data);
        } else {
            $this->authorize('create', Warehouse::class);
            $warehouse = Warehouse::create($data);
            $this->warehouseId = $warehouse->id;
        }

        session()->flash('success', 'Warehouse saved.');
        $this->redirect(route('warehouses.index'));
    }

    public function render()
    {
        return view('livewire.warehouses.warehouse-form');
    }
}
