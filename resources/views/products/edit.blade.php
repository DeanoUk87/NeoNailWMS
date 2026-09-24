<x-layouts::app :title="'Edit Product'">
    <flux:main>
        @livewire('products.product-form', ['productId' => $product->id])
    </flux:main>
</x-layouts::app>
