<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockMovement — immutable audit ledger entry.
 *
 * Records are never updated or deleted.
 * Every change to stock_entries.qty or status produces a movement.
 *
 * Balance reconciliation:
 *   Expected on-hand = SUM(RECEIPT qty) + SUM(RETURN_IN qty)
 *                    - ABS(SUM(DISPATCH qty)) - ABS(SUM(WRITE_OFF qty))
 *   Actual on-hand   = SUM(stock_entries.qty WHERE status NOT IN ('DISPATCHED'))
 */
class StockMovement extends Model
{
    // Movements are immutable — no updates allowed
    public $timestamps = false;

    protected $fillable = [
        'stock_entry_id',
        'movement_type',
        'quantity_change',
        'from_location_id',
        'to_location_id',
        'reference_type',
        'reference_id',
        'performed_by',
        'approved_by',
        'performed_at',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'performed_at'    => 'datetime',
    ];

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
