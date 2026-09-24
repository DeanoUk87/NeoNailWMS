<?php

namespace App\Models;

use App\Models\Scopes\FulfilmentClientScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $fulfilment_client_id
 * @property string $internal_sku
 * @property string $name
 * @property string|null $scan_code
 * @property string|null $manufacturer_barcode  Nullable — not all items have an EAN
 * @property string $unit_of_measure
 * @property int|null $pack_qty                 Null until physically measured
 * @property bool $pack_qty_confirmed           Must be verified before trusting pack count
 * @property bool $is_active
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfilment_client_id',
        'internal_sku',
        'name',
        'scan_code',
        'manufacturer_barcode',
        'unit_of_measure',
        'pack_qty',
        'pack_qty_confirmed',
        'is_active',
    ];

    protected $casts = [
        'pack_qty_confirmed' => 'boolean',
        'is_active'          => 'boolean',
        'pack_qty'           => 'integer',
    ];

    /**
     * Global scope: restrict all queries to the authenticated user's client.
     * Mirrors the pattern used on Warehouse.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new FulfilmentClientScope);
    }

    public function fulfilmentClient(): BelongsTo
    {
        return $this->belongsTo(FulfilmentClient::class);
    }

    public function shopifyVariants(): HasMany
    {
        return $this->hasMany(ShopifyProductVariant::class);
    }

    /**
     * Display-safe pack label. Always explicit — never "1 pack = 1 item" silently.
     */
    public function packLabel(): string
    {
        if ($this->pack_qty === null) {
            return 'pack qty unknown';
        }

        $status = $this->pack_qty_confirmed ? '' : ' (unverified)';

        return "{$this->pack_qty} × {$this->unit_of_measure}{$status}";
    }
}
