<x-layouts::app :title="'New Location'">
    @livewire('warehouses.location-form', ['warehouseId' => $warehouse->id])
</x-layouts::app>
