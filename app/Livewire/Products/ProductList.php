<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';
    public string $filterActive = 'all'; // all|active|inactive

    protected $queryString = ['search', 'filterActive'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('internal_sku', 'like', '%' . $this->search . '%')
                       ->orWhere('name', 'like', '%' . $this->search . '%')
                       ->orWhere('scan_code', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterActive === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('internal_sku')
            ->paginate(25);

        return view('livewire.products.product-list', compact('products'));
    }
}
