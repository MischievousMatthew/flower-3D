<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cash on delivery the shop received for an order, and whether it has been settled */
class CodCollection extends Model
{
    protected $fillable = [
        'owner_id', 'order_id', 'expected_amount', 'collected_amount', 'collected_by', 'collected_on',
        'recorded_by', 'settled_on', 'settlement_reference', 'settled_by', 'notes',
    ];

    protected $casts = [
        'expected_amount' => 'float',
        'collected_amount' => 'float',
        'collected_on' => 'date',
        'settled_on' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Collected minus expected: below zero is a shortage */
    public function difference(): float
    {
        return round($this->collected_amount - $this->expected_amount, 2);
    }
}
