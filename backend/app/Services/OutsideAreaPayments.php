<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReservationAvailabilityCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * An order outside the shop's delivery areas that the shop approved, but the
 * customer didn't pay for (in full, or the down payment) within
 * Order::OUTSIDE_PAY_HOURS, is cancelled: its stock and date are freed and
 * both sides are told. Nothing was paid, so nothing is refunded.
 *
 * Like the auto-complete sweep, it runs while the app is used (no cron on
 * the free server): at most every 10 minutes, after a response goes out.
 */
class OutsideAreaPayments
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function sweepIfDue(): int
    {
        if (! Cache::add('orders:outside-area-unpaid:ran', true, now()->addMinutes(10))) {
            return 0;
        }
        if (! Schema::hasColumn('orders', 'pay_by')) {
            return 0;
        }

        return $this->sweep();
    }

    public function sweep(): int
    {
        $done = 0;
        Order::query()
            ->where('outside_area', true)
            ->where('status', 'pending')
            ->whereNotNull('pay_by')
            ->where('pay_by', '<', now())
            ->limit(50)
            ->get()
            ->each(function (Order $order) use (&$done) {
                // Paid in the meantime (in full, or the down payment): it carries on
                if (! $order->awaitingOutsidePayment()) {
                    return;
                }
                try {
                    // An open payment page can't take the money after this
                    app(\App\Http\Controllers\CheckoutController::class)->closePaymentPage($order);
                    $cancelled = app(VendorFinanceService::class)->cancelPendingOrder($order->fresh());
                    if ($cancelled->reservation_date) {
                        ReservationAvailabilityCache::updateForDate($cancelled->vendor_id, $cancelled->reservation_date->toDateString());
                    }
                    $this->notifications->notify(
                        [$order->user_id],
                        'order',
                        'Your order was cancelled',
                        "Order {$order->order_number} from {$order->store_name} wasn't paid within " . Order::OUTSIDE_PAY_HOURS . ' hours of being approved, so it was cancelled. Nothing was charged.',
                        '/customer/orders',
                    );
                    $this->notifications->notify(
                        [$order->vendor_id],
                        'order',
                        'Order cancelled: not paid',
                        "Order {$order->order_number} wasn't paid within " . Order::OUTSIDE_PAY_HOURS . ' hours of your approval, so it was cancelled and its items were put back.',
                        null,
                    );
                    $done++;
                } catch (\Throwable $e) {
                    Log::warning('Couldn\'t cancel an unpaid outside-area order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            });

        return $done;
    }
}
