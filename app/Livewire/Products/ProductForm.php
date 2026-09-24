<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ProductForm extends Component
{
    use AuthorizesRequests;

    public ?int $productId = null;

    // Form fields
    public string $internal_sku = '';
    public string $name = '';
    public string $scan_code = '';
    public string $manufacturer_barcode = '';
    public string $unit_of_measure = 'each';
    public string $pack_qty = '';
    public bool $pack_qty_confirmed = false;
    public bool $is_active = true;

    protected function rules(): array
    {
        $clientId = auth()->user()->fulfilment_client_id;
        $productId = $this->productId;

        return [
            'internal_sku'         => [
                'required', 'string', 'max:100',
                // Unique within client, ignoring current product on edit
                function ($attr, $value, $fail) use ($clientId, $productId) {
                    $q = Product::withoutGlobalScopes()
                        ->where('fulfilment_client_id', $clientId)
                        ->where('internal_sku', $value);
                    if ($productId) {
                        $q->where('id', '!=', $productId);
                    }
                    if ($q->exists()) {
                        $fail("SKU '{$value}' already exists for this client.");
                    }
                },
            ],
            'name'                 => ['required', 'string', 'max:255'],
            'scan_code'            => ['nullable', 'string', 'max:100'],
            'manufacturer_barcode' => ['nullable', 'string', 'max:100'],
            'unit_of_measure'      => ['required', 'string', 'max:50'],
            'pack_qty'             => ['nullable', 'integer', 'min:1'],
            'pack_qty_confirmed'   => ['boolean'],
            'is_active'            => ['boolean'],
        ];
    }

    public function mount(?int $productId = null): void
    {
        $this->productId = $productId;

        if ($productId) {
            $product = Product::findOrFail($productId);
            $this->authorize('update', $product);

            $this->internal_sku         = $product->internal_sku;
            $this->name                 = $product->name;
            $this->scan_code            = $product->scan_code ?? '';
            $this->manufacturer_barcode = $product->manufacturer_barcode ?? '';
            $this->unit_of_measure      = $product->unit_of_measure;
            $this->pack_qty             = $product->pack_qty ? (string) $product->pack_qty : '';
            $this->pack_qty_confirmed   = $product->pack_qty_confirmed;
            $this->is_active            = $product->is_active;
        } else {
            $this->authorize('create', Product::class);
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'fulfilment_client_id' => auth()->user()->fulfilment_client_id,
            'internal_sku'         => $this->internal_sku,
            'name'                 => $this->name,
            'scan_code'            => $this->scan_code ?: null,
            'manufacturer_barcode' => $this->manufacturer_barcode ?: null,
            'unit_of_measure'      => $this->unit_of_measure,
            'pack_qty'             => $this->pack_qty !== '' ? (int) $this->pack_qty : null,
            'pack_qty_confirmed'   => $this->pack_qty_confirmed,
            'is_active'            => $this->is_active,
        ];

        if ($this->productId) {
            $product = Product::findOrFail($this->productId);
            $this->authorize('update', $product);
            $product->update($data);
        } else {
            $this->authorize('create', Product::class);
            $product = Product::create($data);
            $this->productId = $product->id;
        }

        session()->flash('success', 'Product saved successfully.');
        $this->redirect(route('products.index'));
    }

    public function render()
    {
        return view('livewire.products.product-form');
    }
}
