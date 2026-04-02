<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseLocation extends Model
{
    use BelongsToOwner;
    // Table has both created_at and updated_at columns
    public $timestamps = true;

    protected $fillable = [
        'owner_id',
        'warehouse_id',
        'name',
        'code',
        'description',
        'zone',
        'is_refrigerated',
        'capacity_units',
        'is_active',
    ];

    protected $casts = [
        'is_refrigerated' => 'boolean',
        'is_active'       => 'boolean',
        'capacity_units'  => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The warehouse this location belongs to (nullable — unowned locations have null).
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * All batches stored in this location.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(WarehouseBatch::class, 'warehouse_location_id');
    }

    /**
     * Only active (non-depleted) batches in this location.
     */
    public function activeBatches(): HasMany
    {
        return $this->hasMany(WarehouseBatch::class, 'warehouse_location_id')
                    ->where('status', 'active');
    }
}
