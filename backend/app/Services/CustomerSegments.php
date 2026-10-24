<?php

namespace App\Services;

use App\Models\CustomerPreference;
use App\Models\Order;
use App\Models\VendorCustomerProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Groups a shop's customers for offers, from their purchase history with the
 * shop and the preferences customers chose to share (or told the shop):
 *
 *   new       first order within the last NEW_DAYS
 *   repeat    two or more orders
 *   frequent  FREQUENT_ORDERS or more orders in the last FREQUENT_DAYS
 *   events    bought for a wedding or special event, or says they shop for one
 *   inactive  no order in INACTIVE_DAYS (and has ordered before)
 *
 * A customer can be in several groups. Only paid orders that weren't
 * cancelled count.
 */
class CustomerSegments
{
    public const NEW_DAYS = 30;
    public const FREQUENT_ORDERS = 4;
    public const FREQUENT_DAYS = 180;
    public const INACTIVE_DAYS = 90;

    /** Words that mark a wedding or special event, in product occasions and preferences */
    public const EVENT_WORDS = ['wedding', 'bridal', 'bride', 'debut', 'engagement', 'event', 'corporate', 'party', 'celebration', 'gala'];

    public const SEGMENTS = [
        'new' => ['New customers', 'First order in the last ' . self::NEW_DAYS . ' days'],
        'repeat' => ['Repeat customers', 'Ordered two or more times'],
        'frequent' => ['Frequent buyers', self::FREQUENT_ORDERS . '+ orders in the last 6 months'],
        'events' => ['Weddings & events', 'Bought for, or said they shop for, weddings and special events'],
        'inactive' => ['Inactive customers', 'No order in the last ' . self::INACTIVE_DAYS . ' days'],
    ];

    private const NOT_A_SALE = ['cancelled', 'failed', 'refunded'];

    /**
     * Every customer with paid orders, with their stats and groups:
     * Collection keyed by user id of [orders, spent, first_at, last_at, recent, segments[], event_reasons[]]
     */
    public function members(int $vendorId, ?Carbon $now = null): Collection
    {
        $now ??= now();
        $sales = Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->whereNotNull('user_id')->whereNotNull('paid_at')
            ->whereNotIn('status', self::NOT_A_SALE)->get(['id', 'user_id', 'total_amount', 'paid_at']);
        if ($sales->isEmpty()) {
            return collect();
        }

        $eventBuyers = $this->eventBuyers($sales);
        $byCustomer = $sales->groupBy('user_id');
        $ids = $byCustomer->keys()->map(fn ($id) => (int) $id);

        // Shared preferences count only when the customer allowed it; the shop's own notes always count
        $shared = CustomerPreference::whereIn('user_id', $ids)->where('share_with_shops', true)->pluck('occasions', 'user_id');
        $noted = VendorCustomerProfile::where('vendor_id', $vendorId)->whereIn('customer_id', $ids)->pluck('occasions', 'customer_id');

        return $byCustomer->map(function ($orders, $id) use ($now, $eventBuyers, $shared, $noted) {
            $first = $orders->min('paid_at');
            $last = $orders->max('paid_at');
            $recent = $orders->filter(fn ($o) => $o->paid_at->gte($now->copy()->subDays(self::FREQUENT_DAYS)))->count();

            $reasons = [];
            if ($eventBuyers->has($id)) {
                $reasons[] = 'Bought ' . $eventBuyers[$id];
            }
            if ($this->mentionsEvents($shared[$id] ?? [])) {
                $reasons[] = 'Shops for events (their preferences)';
            }
            if ($this->mentionsEvents($noted[$id] ?? [])) {
                $reasons[] = 'Shops for events (noted by the shop)';
            }

            $segments = array_values(array_filter([
                $first->gte($now->copy()->subDays(self::NEW_DAYS)) ? 'new' : null,
                $orders->count() >= 2 ? 'repeat' : null,
                $recent >= self::FREQUENT_ORDERS ? 'frequent' : null,
                $reasons ? 'events' : null,
                $last->lt($now->copy()->subDays(self::INACTIVE_DAYS)) ? 'inactive' : null,
            ]));

            return [
                'orders' => $orders->count(),
                'spent' => round((float) $orders->sum('total_amount'), 2),
                'first_at' => $first,
                'last_at' => $last,
                'recent' => $recent,
                'segments' => $segments,
                'event_reasons' => $reasons,
            ];
        });
    }

    /** Customers whose orders included an event product: [user id => product name] */
    private function eventBuyers(Collection $sales): Collection
    {
        $rows = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $sales->pluck('id'))
            ->whereNotNull('products.occasion_tags')
            ->get(['order_items.order_id', 'order_items.product_name', 'products.occasion_tags']);
        $customerOf = $sales->pluck('user_id', 'id');

        $found = collect();
        foreach ($rows as $row) {
            $tags = json_decode($row->occasion_tags ?? '[]', true) ?: [];
            if ($this->mentionsEvents($tags)) {
                $found[$customerOf[$row->order_id]] ??= $row->product_name ?: 'an event bouquet';
            }
        }

        return $found;
    }

    private function mentionsEvents($tags): bool
    {
        foreach ((array) $tags as $tag) {
            $tag = mb_strtolower((string) $tag);
            foreach (self::EVENT_WORDS as $word) {
                if (str_contains($tag, $word)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Customers who said yes to offers and haven't muted this shop */
    public function reachable(int $vendorId, Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return CustomerPreference::whereIn('user_id', $userIds)->where('accept_offers', true)->get(['user_id', 'muted_offer_vendor_ids'])
            ->reject(fn ($p) => in_array($vendorId, array_map('intval', $p->muted_offer_vendor_ids ?? []), true))
            ->pluck('user_id')->map(fn ($id) => (int) $id)->values();
    }
}
