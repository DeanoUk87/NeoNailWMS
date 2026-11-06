<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarantineRecord extends Model
{
    public $timestamps = false; // raised_at and resolved_at serve as timestamps

    protected $fillable = [
        'stock_entry_id',
        'fulfilment_client_id',
        'reason',
        'quantity',
        'status',
        'raised_by',
        'resolved_by',
        'raised_at',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'raised_at'   => 'datetime',
        'resolved_at' => 'datetime',
        'quantity'    => 'integer',
    ];

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }
}
