<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Order;
use App\Models\OrderRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Only the customer completes an order, by marking it received. If they
 * don't, it completes by itself 3 days after it went out for delivery,
 * unless a return or refund is open on it.
 *
 * There's no cron on the server (Render free plan), so the check runs while
 * the app is used: at most once every 10 minutes, after a response has been
 * sent (see AutoCompleteDeliveredOrders). It's also the artisan command
 * orders:auto-complete, for a scheduler if one is added.
 */
class OrderAutoCompleteService
{
    public const DAYS = 3;

    /** When the order went out for delivery */
    public function wentOutAt(Delivery $delivery): ?Carbon
    {
        $at = $delivery->relationLoaded('logs')
            ? $delivery->logs->where('status', 'to_received')->max('scanned_at')
            : DeliveryLog::where('delivery_id', $delivery->id)->where('status', 'to_received')->max('scanned_at');

        return $at ? Carbon::parse($at) : ($delivery->status === 'to_received' ? $delivery->updated_at : null);
    }

    /** When it completes by itself if the customer doesn't mark it received */
    public function completesAt(Delivery $delivery): ?Carbon
    {
        if ($delivery->status !== 'to_received') {
            return null;
        }

        return $this->wentOutAt($delivery)?->copy()->addDays(self::DAYS);
    }

    /** Runs the sweep unless it ran in the last 10 minutes */
    public function sweepIfDue(): int
    {
        if (! Cache::add('orders:auto-complete:ran', true, now()->addMinutes(10))) {
            return 0;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('deliveries')) {
            return 0;
        }

        return $this->sweep();
    }

    /** Completes every order that's been out for delivery 3 days or more */
    public function sweep(): int
    {
        $cutoff = now()->subDays(self::DAYS);
        $done = 0;

        Delivery::withoutGlobalScopes()
            ->where('status', 'to_received')
            ->with('logs')
            ->orderBy('id')
            ->chunkById(100, function ($deliveries) use ($cutoff, &$done) {
                foreach ($deliveries as $delivery) {
                    $out = $this->wentOutAt($delivery);
                    if (! $out || $out->gt($cutoff)) {
                        continue;
                    }
                    try {
                        if ($this->complete($delivery)) {
                            $done++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Order auto-complete failed', ['delivery_id' => $delivery->id, 'error' => get_class($e)]);
                    }
                }
            });

        return $done;
    }

    private function complete(Delivery $delivery): bool
    {
        $order = DB::transaction(function () use ($delivery) {
            $locked = Delivery::withoutGlobalScopes()->lockForUpdate()->find($delivery->id);
            if (! $locked || $locked->status !== 'to_received') {
                return null;
            }
            $order = Order::withoutGlobalScopes()->find($locked->order_id);
            if (! $order) {
                return null;
            }
            // A return or refund being settled: it stays open until that's done
            if (OrderRequest::where('order_id', $order->id)->whereIn('status', OrderRequest::OPEN_STATUSES)->exists()) {
                return null;
            }

            $locked->update(['status' => 'completed', 'last_scanned_at' => now()]);
            DeliveryLog::create([
                'owner_id' => $locked->owner_id ?? $order->vendor_id,
                'delivery_id' => $locked->id,
                'status' => 'completed',
                'scanned_at' => now(),
                'notes' => 'Completed automatically ' . self::DAYS . ' days after it went out for delivery.',
            ]);
            $order->markAsCompleted();

            return $order;
        });

        if (! $order) {
            return false;
        }

        // Cash on delivery is settled the same way as when the customer marks it received
        app(VendorFinanceService::class)->settleCashOnDeliveryOrder($order->fresh());

        try {
            if ($order->user_id) {
                app(NotificationService::class)->notify(
                    [$order->user_id],
                    'order',
                    "Order {$order->order_number} is complete",
                    'It was marked received automatically ' . self::DAYS . ' days after it went out for delivery. If something is wrong with it, you can still ask the shop for a return or refund.',
                );
            }
        } catch (\Throwable) {
            // The order is complete either way
        }

        return true;
    }
}
