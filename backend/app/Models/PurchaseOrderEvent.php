<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a purchase order's history: created, sent for approval,
 * approved, sent back, emailed, part received, a status change.
 */
class PurchaseOrderEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'owner_id', 'purchase_order_id', 'event', 'from_status', 'to_status', 'note',
        'actor_type', 'actor_id', 'actor_name', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** Write an event for whoever is signed in (or "System" for automatic steps) */
    public static function record(PurchaseOrder $order, string $event, ?string $from = null, ?string $to = null, ?string $note = null): ?self
    {
        $actor = auth()->user();

        // History must never stop the order itself from saving
        try {
            return static::createEvent($order, $event, $from, $to, $note, $actor);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Purchase order history not written', ['order_id' => $order->id, 'event' => $event, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private static function createEvent(PurchaseOrder $order, string $event, ?string $from, ?string $to, ?string $note, $actor): self
    {
        return static::create([
            'owner_id'          => $order->owner_id,
            'purchase_order_id' => $order->id,
            'event'             => $event,
            'from_status'       => $from,
            'to_status'         => $to,
            'note'              => $note,
            'actor_type'        => $actor ? class_basename($actor) : null,
            'actor_id'          => $actor?->id,
            'actor_name'        => static::nameOf($actor),
            'created_at'        => now(),
        ]);
    }

    public static function nameOf($actor): ?string
    {
        if (! $actor) {
            return null;
        }
        $name = trim(($actor->name ?? '') . ' ' . ($actor->surname ?? ''));

        return $name !== '' ? $name : ($actor->email ?? null);
    }
}
