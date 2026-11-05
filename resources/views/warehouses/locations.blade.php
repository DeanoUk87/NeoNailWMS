<x-layouts::app :title="'Locations'">
    @livewire('warehouses.location-list', ['warehouseId' => $warehouse->id])
</x-layouts::app>
