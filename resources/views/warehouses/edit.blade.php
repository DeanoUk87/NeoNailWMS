<x-layouts::app :title="'Edit Warehouse'">
    @livewire('warehouses.warehouse-form', ['warehouseId' => $warehouse->id])
</x-layouts::app>
