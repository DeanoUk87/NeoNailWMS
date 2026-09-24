<?php

namespace App\Livewire\Products;

use App\Models\ShopifyProductVariant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class VariantMappingList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $filterStatus = 'all'; // all|unmapped|mapped|exception|pending_review
    public string $search = '';

    protected $queryString = ['filterStatus', 'search'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Manually map a variant to a product.
     * Sets provenance=manual so future imports will never overwrite this.
     */
    public function mapVariant(int $variantId, int $productId): void
    {
        $this->authorize('create', \App\Models\Product::class); // admin/supervisor only

        $variant = ShopifyProductVariant::where('id', $variantId)
            ->where('fulfilment_client_id', auth()->user()->fulfilment_client_id)
            ->firstOrFail();

        // Verify product belongs to same client
        $product = \App\Models\Product::withoutGlobalScopes()
            ->where('id', $productId)
            ->where('fulfilment_client_id', auth()->user()->fulfilment_client_id)
            ->firstOrFail();

        $variant->update([
            'product_id'       => $product->id,
            'mapping_status'   => 'mapped',
            'provenance'       => 'manual',
            'exception_reason' => null,
        ]);

        session()->flash('success', "Variant mapped to '{$product->name}' (manual — will not be overwritten by imports).");
    }

    /**
     * Mark a variant as an exception for human review.
     */
    public function flagException(int $variantId, string $reason): void
    {
        $this->authorize('create', \App\Models\Product::class);

        $variant = ShopifyProductVariant::where('id', $variantId)
            ->where('fulfilment_client_id', auth()->user()->fulfilment_client_id)
            ->firstOrFail();

        $variant->update([
            'mapping_status'   => 'exception',
            'exception_reason' => $reason,
        ]);
    }

    public function render()
    {
        $clientId = auth()->user()->fulfilment_client_id;

        $variants = ShopifyProductVariant::with('product')
            ->where('fulfilment_client_id', $clientId)
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('mapping_status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('variant_gid', 'like', '%' . $this->search . '%')
                       ->orWhere('source_sku', 'like', '%' . $this->search . '%')
                       ->orWhere('source_barcode', 'like', '%' . $this->search . '%')
                       ->orWhere('shopify_title', 'like', '%' . $this->search . '%');
                });
            })
            ->orderByRaw("CASE mapping_status WHEN 'exception' THEN 0 WHEN 'unmapped' THEN 1 WHEN 'pending_review' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->paginate(25);

        $exceptionCount = ShopifyProductVariant::where('fulfilment_client_id', $clientId)
            ->where('mapping_status', 'exception')
            ->count();

        $unmappedCount = ShopifyProductVariant::where('fulfilment_client_id', $clientId)
            ->where('mapping_status', 'unmapped')
            ->count();

        return view('livewire.products.variant-mapping-list', compact('variants', 'exceptionCount', 'unmappedCount'));
    }
}
