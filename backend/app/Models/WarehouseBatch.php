<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseBatch extends Model
{
    use BelongsToOwner;
    // ── Condition progression (in order) ──────────────────────────────────
    public const CONDITIONS = ['fresh', 'aging', 'wilting', 'spoiled', 'discarded'];

    // ── Condition thresholds as % of freshness_days remaining ─────────────
    // e.g. if freshness_days = 10:
    //   > 60% remaining (6+ days)  → fresh
    //   40–60% (4–6 days)          → aging
    //   20–40% (2–4 days)          → wilting
    //   < 20% (< 2 days)           → spoiled
    //   past expiry                → spoiled / should be discarded
    public const CONDITION_THRESHOLDS = [
        'fresh'   => 0.60,
        'aging'   => 0.40,
        'wilting' => 0.20,
        'spoiled' => 0.0,
    ];

    protected $fillable = [
        'owner_id',
        'product_id',
        'warehouse_location_id',
        'storage_location',
        'batch_number',
        'barcode',
        'lot_number',
        'received_date',
        'harvest_date',
        'expiration_date',
        'freshness_days',
        'qty_received',
        'qty_remaining',
        'condition_status',
        'status',
        'notes',
    ];

    protected $casts = [
        'received_date'   => 'date',
        'harvest_date'    => 'date',
        'expiration_date' => 'date',
        'freshness_days'  => 'integer',
        'qty_received'    => 'integer',
        'qty_remaining'   => 'integer',
    ];

    protected $appends = ['days_remaining', 'computed_condition', 'is_expired'];

    // ── Relationships ──────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WarehouseBatchLog::class)->orderByDesc('created_at');
    }

    // ── Computed attributes ────────────────────────────────────────────────

    /**
     * Days until expiration (negative = already expired).
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->expiration_date) {
            return null;
        }
        return (int) now()->startOfDay()->diffInDays($this->expiration_date, false);
    }

    /**
     * Whether this batch is past its expiration date.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (! $this->expiration_date) {
            return false;
        }
        return $this->expiration_date->isPast();
    }

    /**
     * Real-time condition derived from dates — independent of the stored
     * condition_status (which is the last manually or system-set value).
     *
     * Use this in the UI to show the most current state.
     * Use condition_status for filtering/querying (it's indexed).
     */
    public function getComputedConditionAttribute(): string
    {
        if (! $this->expiration_date && ! $this->freshness_days) {
            return $this->condition_status; // fall back to stored value
        }

        $daysRemaining = $this->days_remaining;

        if ($daysRemaining === null) {
            return $this->condition_status;
        }

        if ($daysRemaining < 0) {
            return 'spoiled';
        }

        if ($this->freshness_days) {
            $ratio = $daysRemaining / $this->freshness_days;

            return match(true) {
                $ratio > self::CONDITION_THRESHOLDS['fresh']   => 'fresh',
                $ratio > self::CONDITION_THRESHOLDS['aging']   => 'aging',
                $ratio > self::CONDITION_THRESHOLDS['wilting'] => 'wilting',
                default                                         => 'spoiled',
            };
        }

        // No freshness_days — use simple day thresholds
        return match(true) {
            $daysRemaining > 5 => 'fresh',
            $daysRemaining > 2 => 'aging',
            $daysRemaining > 0 => 'wilting',
            default            => 'spoiled',
        };
    }

    // ── Business logic ─────────────────────────────────────────────────────

    /**
     * Update condition and write a log entry.
     * This is the only correct way to change condition_status.
     */
    public function updateCondition(string $newCondition, ?int $performedBy = null, ?string $notes = null): void
    {
        $old = $this->condition_status;

        $this->update(['condition_status' => $newCondition]);

        $this->logs()->create([
            'performed_by'     => $performedBy,
            'event_type'       => 'CONDITION_UPDATED',
            'condition_before' => $old,
            'condition_after'  => $newCondition,
            'qty_after'        => $this->qty_remaining,
            'notes'            => $notes,
        ]);
    }

    /**
     * Reduce quantity (culling spoiled units, shipment pick, etc.).
     * Automatically marks batch as depleted when qty reaches 0.
     */
    public function reduceQuantity(int $qty, string $eventType = 'QUANTITY_ADJUSTED', ?int $performedBy = null, ?string $notes = null): void
    {
        $newQty = max(0, $this->qty_remaining - $qty);

        $this->update([
            'qty_remaining' => $newQty,
            'status'        => $newQty === 0 ? 'depleted' : $this->status,
        ]);

        $this->logs()->create([
            'performed_by' => $performedBy,
            'event_type'   => $eventType,
            'qty_change'   => -$qty,
            'qty_after'    => $newQty,
            'notes'        => $notes,
        ]);
    }

    /**
     * Transfer this batch to a different storage location.
     * Also syncs the denormalized storage_location string.
     */
    public function transferTo(WarehouseLocation $newLocation, ?int $performedBy = null, ?string $notes = null): void
    {
        $fromLocation = $this->storage_location;

        $this->update([
            'warehouse_location_id' => $newLocation->id,
            'storage_location'      => $newLocation->name,
        ]);

        $this->logs()->create([
            'performed_by'  => $performedBy,
            'event_type'    => 'TRANSFERRED',
            'from_location' => $fromLocation,
            'to_location'   => $newLocation->name,
            'qty_after'     => $this->qty_remaining,
            'notes'         => $notes,
        ]);
    }

    /**
     * Auto-generate a batch number if none is provided.
     * Format: {SKU}-{YYYYMMDD}-{3-digit sequence}
     */
    public static function generateBatchNumber(string $sku, string $date): string
    {
        $dateStr = Carbon::parse($date)->format('Ymd');
        $prefix  = strtoupper($sku) . '-' . $dateStr . '-';

        $count = static::where('batch_number', 'like', $prefix . '%')->count();

        return $prefix . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
