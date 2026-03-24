<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_id',
        'order_id',
        'status',
        'last_scanned_by',
        'last_scanned_at',
        'barcode',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    // ─── Status FSM ──────────────────────────────────────────────────────────
    //
    //  pending → to_processed → to_ship → to_received → completed
    //                                                 ↘ returned / refunded
    //
    // Each key lists the ONLY statuses it may legally advance to.

    const STATUS_TRANSITIONS = [
        'pending'      => ['to_processed'],
        'to_processed' => ['to_ship'],
        'to_ship'      => ['to_received'],
        'to_received'  => ['completed'],
        'completed'    => ['returned', 'refunded'],
        'returned'     => [],
        'refunded'     => [],
    ];

    const SCANNER_PAGE_TARGET = [
        'to_process' => 'to_processed',
        'to_ship'    => 'to_ship',
        'to_receive' => 'to_received',
    ];

    const ORDER_STATUS_MAP = [
        'pending'      => 'processing',
        'to_processed' => 'processing',
        'to_ship'      => 'shipped',
        'to_receive'   => 'out_for_delivery',
        'completed'    => 'completed',
        'returned'     => 'returned',
        'refunded'     => 'refunded',
    ];

    const STATUS_LABELS = [
        'pending'      => 'Pending',
        'to_processed' => 'To Process',
        'to_ship'      => 'To Ship',
        'to_receive'   => 'To Receive',
        'completed'    => 'Completed',
        'returned'     => 'Returned',
        'refunded'     => 'Refunded',
    ];

    const SCAN_PREREQUISITE = [
        'to_processed' => 'pending',
        'to_ship'      => 'to_processed',
        'to_received'  => 'to_ship',
        'completed'    => 'to_received',
    ];
    
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::STATUS_TRANSITIONS[$this->status] ?? []);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class, 'last_scanned_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class)->orderByDesc('scanned_at');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId));
    }
}