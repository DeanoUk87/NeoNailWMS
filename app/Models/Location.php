<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    // Zone codes and their meaning
    public const ZONES = [
        'A' => 'Ambient Storage',
        'C' => 'Cold / Chilled Storage',
        'I' => 'Inbound Holding',
        'Q' => 'Quarantine',
        'D' => 'Dispatch Staging',
        'R' => 'Returns Inspection',
    ];

    // Only these zones are eligible to hold AVAILABLE stock
    public const ELIGIBLE_ZONES = ['A', 'C'];

    protected $fillable = [
        'warehouse_id',
        'barcode',
        'name',
        'zone',
        'section',
        'shelf',
        'bin_position',
        'location_type',
        'capacity_units',
        'notes',
        'is_active',
        'is_eligible_for_available_stock',
    ];

    protected $casts = [
        'is_active'                      => 'boolean',
        'is_eligible_for_available_stock' => 'boolean',
        'capacity_units'                 => 'integer',
    ];

    /**
     * Human-readable zone label.
     */
    public function zoneName(): string
    {
        return self::ZONES[$this->zone] ?? $this->zone;
    }

    /**
     * Whether this location can hold AVAILABLE (pickable) stock.
     */
    public function isEligibleForAvailableStock(): bool
    {
        return in_array($this->zone, self::ELIGIBLE_ZONES, true);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
