<?php

namespace App\Models;

use App\Models\Scopes\FulfilmentClientScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfilment_client_id',
        'name',
    ];

    /**
     * Boot the model and register the global scope.
     *
     * The FulfilmentClientScope restricts ALL Warehouse queries to the
     * authenticated user's fulfilment_client_id at the Eloquent level.
     * A route middleware alone is not sufficient; this scope ensures
     * isolation even when warehouses are queried from artisan commands,
     * jobs, or other non-HTTP contexts that carry auth state.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new FulfilmentClientScope);
    }

    public function fulfilmentClient(): BelongsTo
    {
        return $this->belongsTo(FulfilmentClient::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
