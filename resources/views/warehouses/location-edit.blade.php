<x-layouts::app :title="'Edit Location'">
    @livewire('warehouses.location-form', ['warehouseId' => $warehouse->id, 'locationId' => $location->id])
</x-layouts::app>
