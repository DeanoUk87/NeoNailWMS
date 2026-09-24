<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fulfilment_client_id
 * @property string $shop_domain
 * @property string $variant_gid
 * @property int|null $product_id
 * @property string|null $source_sku
 * @property string|null $source_barcode
 * @property string|null $shopify_title
 * @property string $mapping_status   unmapped|pending_review|mapped|exception
 * @property string $provenance       csv_import|manual
 * @property string|null $exception_reason
 *
 * Invariants enforced here and at DB level:
 * - A variant_gid is unique within a shop_domain.
 * - A manual mapping (provenance=manual) must never be silently overwritten
 *   by a CSV import. The import service checks this before writing.
 * - A variant must not be mapped to a product belonging to a different client.
 */
class ShopifyProductVariant extends Model
{
    use HasFactory;

    protected $table = 'shopify_product_variants';

    protected $fillable = [
        'fulfilment_client_id',
        'shop_domain',
        'variant_gid',
        'product_id',
        'source_sku',
        'source_barcode',
        'shopify_title',
        'mapping_status',
        'provenance',
        'exception_reason',
    ];

    public function fulfilmentClient(): BelongsTo
    {
        return $this->belongsTo(FulfilmentClient::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function isManuallymapped(): bool
    {
        return $this->provenance === 'manual';
    }

    public function isException(): bool
    {
        return $this->mapping_status === 'exception';
    }

    public function isMapped(): bool
    {
        return $this->mapping_status === 'mapped' && $this->product_id !== null;
    }
}
