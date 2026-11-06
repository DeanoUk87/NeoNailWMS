<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents one manufacturer production batch of a product.
 *
 * Expiry classification uses the algorithm from Task 003C Technical Addendum:
 * - Compute boundary dates by calendar-month addition from today
 * - Compare operational_expiry_date strictly against each boundary
 * - Live checks always use operational_expiry_date, not the cached classification
 */
class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfilment_client_id',
        'product_id',
        'manufacturer_batch_number',
        'manufacturer_expiry_raw',
        'manufacturer_expiry_precision',
        'operational_expiry_date',
        'no_expiry_confirmed',
        'batch_source',
        'internal_tracking_ref',
        'expiry_classification',
        'last_classification_check',
        'notes',
    ];

    protected $casts = [
        'operational_expiry_date'    => 'date',
        'no_expiry_confirmed'        => 'boolean',
        'last_classification_check'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function fulfilmentClient(): BelongsTo
    {
        return $this->belongsTo(FulfilmentClient::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    // -------------------------------------------------------------------------
    // Expiry classification (Task 003C/003D algorithm)
    // -------------------------------------------------------------------------

    /**
     * Classify this batch's expiry status as of a given date.
     * Uses direct boundary-date comparison — no whole-month truncation.
     *
     * BD-13 confirmed: live checks at allocation/pick/dispatch always call this
     * directly from operational_expiry_date, never the cached field.
     *
     * @param Carbon|null $asOf Assessment date (default: today)
     */
    public function classifyExpiry(?Carbon $asOf = null): string
    {
        $today = $asOf ?? Carbon::today();

        if ($this->no_expiry_confirmed) {
            return 'NO_EXPIRY';
        }

        if (!$this->operational_expiry_date) {
            return 'EXPIRY_UNKNOWN';
        }

        $expiry = $this->operational_expiry_date;

        // Boundary dates by calendar-month addition
        $b12 = $today->copy()->addMonthsNoOverflow(12);
        $b9  = $today->copy()->addMonthsNoOverflow(9);
        $b6  = $today->copy()->addMonthsNoOverflow(6);
        $b3  = $today->copy()->addMonthsNoOverflow(3);
        $b1  = $today->copy()->addMonthsNoOverflow(1);

        // Strict > comparisons (from Task 003C Technical Addendum)
        if ($expiry->gt($b12)) return 'FULL_PRICE';
        if ($expiry->gt($b9))  return 'SHORT_12';
        if ($expiry->gt($b6))  return 'SHORT_9';
        if ($expiry->gt($b3))  return 'SHORT_6';
        if ($expiry->gt($b1))  return 'SHORT_3';
        if ($expiry->gt($today)) return 'SHORT_1';

        // today > expiry means EXPIRED (strict >)
        return 'EXPIRED';
    }

    /**
     * Whether this batch is currently expired.
     * Uses: today > operational_expiry_date (strict greater-than).
     * Stock valid ON the expiry date; expired the FOLLOWING day.
     */
    public function isExpired(?Carbon $asOf = null): bool
    {
        if ($this->no_expiry_confirmed || !$this->operational_expiry_date) {
            return false;
        }
        $today = $asOf ?? Carbon::today();
        return $today->gt($this->operational_expiry_date);
    }

    /**
     * Whether this batch is eligible for allocation and picking.
     * Blocks EXPIRED and EXPIRY_UNKNOWN batches.
     */
    public function isEligibleForPicking(?Carbon $asOf = null): bool
    {
        return !$this->isExpired($asOf)
            && $this->batch_source !== 'unidentifiable';
    }

    /**
     * Update the cached expiry_classification field.
     * Called by the daily scheduler — not used for hard-stop decisions.
     */
    public function updateCachedClassification(?Carbon $asOf = null): void
    {
        $this->update([
            'expiry_classification'  => $this->classifyExpiry($asOf),
            'last_classification_check' => now(),
        ]);
    }

    /**
     * Derive operational_expiry_date from manufacturer-supplied information.
     * Preserves raw string and precision separately.
     *
     * Month/year only → last day of stated month (most conservative)
     * Year only → 31 December of stated year
     */
    public static function deriveOperationalExpiryDate(
        string $raw,
        string $precision
    ): ?string {
        return match ($precision) {
            'full_date'  => Carbon::parse($raw)->format('Y-m-d'),
            // month_year: raw is "MM/YYYY" e.g. "06/2027"
            // Parse as "YYYY-MM-01" to avoid locale-dependent d/m/y ambiguity
            'month_year' => (function () use ($raw) {
                [$m, $y] = explode('/', $raw);
                return Carbon::createFromDate((int) $y, (int) $m, 1)
                    ->endOfMonth()
                    ->format('Y-m-d');
            })(),
            'year_only'  => Carbon::createFromDate((int) $raw, 12, 31)->format('Y-m-d'),
            default      => null,
        };
    }
}
