<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use BelongsToOwner;

    public $timestamps = false; // only created_at defined in schema

    protected $fillable = [
        'owner_id',
        'supplier_id',
        'order_number',
        'status',
        'total_amount',
        'order_date',
        'expected_date',
        'notes',
        'created_by_name',
        'submitted_at',
        'approved_at',
        'approved_by_name',
        'sent_at',
        'sent_to_email',
        'completed_at',
    ];

    protected $casts = [
        'total_amount'  => 'decimal:2',
        'order_date'    => 'date:Y-m-d',
        'expected_date' => 'date:Y-m-d',
        'submitted_at'  => 'datetime',
        'approved_at'   => 'datetime',
        'sent_at'       => 'datetime',
        'completed_at'  => 'datetime',
    ];

    /** The five stages people see, and the stored statuses each one covers */
    public const STAGES = [
        'draft'              => ['draft'],
        'pending_approval'   => ['pending_approval'],
        'ordered'            => ['pending', 'processing', 'shipped', 'received'],
        'partially_received' => ['partially_received'],
        'completed'          => ['completed'],
    ];

    /** Still being written or waiting for approval: not yet with the supplier */
    public const EDITABLE = ['draft', 'pending_approval'];

    /** Set while a step writes its own, more specific history line (approved, sent back, received) */
    private static bool $quietStatus = false;

    protected static function booted(): void
    {
        // Every status change is written to the order's history, whichever
        // screen, scan or shipment step made it
        static::updated(function (PurchaseOrder $order) {
            if ($order->wasChanged('status') && ! static::$quietStatus) {
                PurchaseOrderEvent::record($order, 'status', $order->getOriginal('status'), $order->status);
            }
        });
    }

    /** Change the status without the generic "status changed" line */
    public function quietlyMoveTo(array $attributes): bool
    {
        static::$quietStatus = true;
        try {
            return $this->update($attributes);
        } finally {
            static::$quietStatus = false;
        }
    }

    protected $dates = ['created_at'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A purchase order belongs to one supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * A purchase order has many line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * A purchase order has one shipment.
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /**
     * Everything that happened to the order, newest first.
     */
    public function events(): HasMany
    {
        return $this->hasMany(PurchaseOrderEvent::class)->latest('id');
    }

    /**
     * Deliveries received against the order, with their inspection, newest first.
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class)->latest('id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Recalculate and persist total_amount from order items.
     */
    public function recalculateTotal(): static
    {
        $this->total_amount = $this->items()->sum('subtotal');
        $this->save();

        return $this;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
