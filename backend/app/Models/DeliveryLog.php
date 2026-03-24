<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryLog extends Model
{
    // Append-only — no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'delivery_id',
        'status',
        'scanned_by',
        'scanned_at',
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class, 'scanned_by');
    }
}