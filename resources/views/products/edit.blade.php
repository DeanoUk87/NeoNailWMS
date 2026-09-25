<x-layouts::app :title="'Edit Product'">
    @livewire('products.product-form', ['productId' => $product->id])
</x-layouts::app>
