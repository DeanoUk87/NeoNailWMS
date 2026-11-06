<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * StockEntry — the core inventory record.
 *
 * qty is the CURRENT balance for this entry.
 * qty = 0 on a DISPATCHED entry is intentional (traceability tombstone).
 *
 * Physical inventory = SUM(qty) WHERE status NOT IN ('DISPATCHED')
 *
 * Statuses that contribute to on-hand physical inventory:
 *   RECEIVED_UNVERIFIED, PENDING_SUPERVISOR, APPROVED_PUTAWAY,
 *   IN_LOCATION, AVAILABLE, RESERVED, RESERVED_HELD,
 *   PICKED, PACKED, QUARANTINE
 *
 * version is an optimistic lock counter. Increment on every write.
 * If concurrent writes detect version mismatch, the second rolls back.
 */
class StockEntry extends Model
{
    use HasFactory;

    // Statuses that contribute to physical on-hand inventory
    public const ON_HAND_STATUSES = [
        'RECEIVED_UNVERIFIED', 'PENDING_SUPERVISOR', 'APPROVED_PUTAWAY',
        'IN_LOCATION', 'AVAILABLE', 'RESERVED', 'RESERVED_HELD',
        'PICKED', 'PACKED', 'QUARANTINE',
    ];

    // Statuses eligible for picking/dispatch
    public const PICKABLE_STATUSES = ['AVAILABLE'];

    protected $fillable = [
        'fulfilment_client_id',
        'warehouse_id',
        'batch_id',
        'location_id',
        'qty',
        'status',
        'order_reference',
        'version',
    ];

    protected $casts = [
        'qty'     => 'integer',
        'version' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function fulfilmentClient(): BelongsTo
    {
        return $this->belongsTo(FulfilmentClient::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function quarantineRecord(): HasOne
    {
        return $this->hasOne(QuarantineRecord::class);
    }

    // -------------------------------------------------------------------------
    // Business helpers
    // -------------------------------------------------------------------------

    public function isAvailable(): bool   { return $this->status === 'AVAILABLE'; }
    public function isReserved(): bool    { return $this->status === 'RESERVED'; }
    public function isQuarantined(): bool { return $this->status === 'QUARANTINE'; }
    public function isDispatched(): bool  { return $this->status === 'DISPATCHED'; }
    public function isOnHand(): bool      { return in_array($this->status, self::ON_HAND_STATUSES, true); }
}
