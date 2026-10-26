<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The shop's rating of a supplier, out of 5, on quality, being on time and
 * communication; optionally for one purchase order.
 */
class SupplierRating extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'owner_id', 'supplier_id', 'purchase_order_id', 'quality', 'timeliness', 'communication',
        'comment', 'rated_by_type', 'rated_by_id', 'rated_by_name',
    ];

    protected $casts = [
        'quality'       => 'integer',
        'timeliness'    => 'integer',
        'communication' => 'integer',
    ];

    protected $appends = ['score'];

    public function getScoreAttribute(): float
    {
        return round(($this->quality + $this->timeliness + $this->communication) / 3, 1);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
