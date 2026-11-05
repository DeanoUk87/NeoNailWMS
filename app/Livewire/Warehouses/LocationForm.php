<?php

namespace App\Livewire\Warehouses;

use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class LocationForm extends Component
{
    use AuthorizesRequests;

    public int  $warehouseId;
    public ?int $locationId = null;

    public string $barcode       = '';
    public string $name          = '';
    public string $zone          = 'A';
    public string $section       = '';
    public string $shelf         = '';
    public string $bin_position  = '';
    public string $location_type = 'bin';
    public string $capacity_units = '';
    public string $notes         = '';
    public bool   $is_active     = true;

    protected function rules(): array
    {
        $locationId = $this->locationId;
        $warehouseId = $this->warehouseId;

        return [
            'barcode'       => [
                'required', 'string', 'max:50',
                Rule::unique('locations', 'barcode')
                    ->where('warehouse_id', $warehouseId)
                    ->ignore($locationId),
            ],
            'name'          => ['required', 'string', 'max:100'],
            'zone'          => ['required', 'string', Rule::in(array_keys(Location::ZONES))],
            'section'       => ['nullable', 'string', 'max:20'],
            'shelf'         => ['nullable', 'string', 'max:20'],
            'bin_position'  => ['nullable', 'string', 'max:20'],
            'location_type' => ['required', 'string', 'max:50'],
            'capacity_units'=> ['nullable', 'integer', 'min:1'],
            'notes'         => ['nullable', 'string', 'max:500'],
            'is_active'     => ['boolean'],
        ];
    }

    public function mount(int $warehouseId, ?int $locationId = null): void
    {
        $this->warehouseId = $warehouseId;
        $this->locationId  = $locationId;

        $warehouse = Warehouse::findOrFail($warehouseId);

        if ($locationId) {
            $location = Location::findOrFail($locationId);
            $this->authorize('update', $location);
            $this->barcode        = $location->barcode;
            $this->name           = $location->name;
            $this->zone           = $location->zone;
            $this->section        = $location->section        ?? '';
            $this->shelf          = $location->shelf          ?? '';
            $this->bin_position   = $location->bin_position   ?? '';
            $this->location_type  = $location->location_type;
            $this->capacity_units = $location->capacity_units ? (string) $location->capacity_units : '';
            $this->notes          = $location->notes          ?? '';
            $this->is_active      = $location->is_active;
        } else {
            $this->authorize('create', Location::class);
        }
    }

    /**
     * Auto-generate barcode from zone/section/shelf/bin_position when operator
     * fills those fields but hasn't manually set a barcode.
     */
    public function generateBarcode(): void
    {
        if ($this->zone && $this->section) {
            $parts = array_filter([
                strtoupper($this->zone) . str_pad($this->section, 2, '0', STR_PAD_LEFT),
                $this->shelf       ? 'S' . str_pad($this->shelf, 2, '0', STR_PAD_LEFT)       : null,
                $this->bin_position? 'B' . str_pad($this->bin_position, 2, '0', STR_PAD_LEFT) : null,
            ]);
            $this->barcode = implode('-', $parts);
        }
    }

    public function save(): void
    {
        $this->validate();

        $isEligible = in_array($this->zone, Location::ELIGIBLE_ZONES, true);

        $data = [
            'warehouse_id'                     => $this->warehouseId,
            'barcode'                          => strtoupper($this->barcode),
            'name'                             => $this->name,
            'zone'                             => $this->zone,
            'section'                          => $this->section        ?: null,
            'shelf'                            => $this->shelf          ?: null,
            'bin_position'                     => $this->bin_position   ?: null,
            'location_type'                    => $this->location_type,
            'capacity_units'                   => $this->capacity_units !== '' ? (int) $this->capacity_units : null,
            'notes'                            => $this->notes          ?: null,
            'is_active'                        => $this->is_active,
            'is_eligible_for_available_stock'  => $isEligible,
        ];

        if ($this->locationId) {
            $location = Location::findOrFail($this->locationId);
            $this->authorize('update', $location);

            // Prevent zone reclassification when it would affect AVAILABLE/RESERVED stock
            // (In Phase 3+ this will check stock_entries; for now it's a safeguard placeholder)
            $location->update($data);
        } else {
            $this->authorize('create', Location::class);
            Location::create($data);
        }

        session()->flash('success', 'Location saved.');
        $this->redirect(route('warehouses.locations', $this->warehouseId));
    }

    public function render()
    {
        return view('livewire.warehouses.location-form', [
            'zones'          => Location::ZONES,
            'locationTypes'  => ['bin', 'shelf', 'bay', 'compartment', 'zone'],
        ]);
    }
}
