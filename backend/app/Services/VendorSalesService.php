<?php

namespace App\Services;

use App\Models\Order;
use App\Models\VendorTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * A shop's sales for a period, for the Finance "Sales & revenue" page.
 *
 * Money is counted when an order is paid (the same ledger entries the finance
 * dashboard and profit & loss use, so the figures agree):
 *   gross sales (regular prices) − discounts + delivery fees + tax = collected;
 *   collected − refunds = net sales.
 * Order outcomes (completed, cancelled…) are counted for orders placed in the period.
 */
class VendorSalesService
{
    public const STATUS_GROUPS = ['completed', 'in_progress', 'cancelled', 'returned'];

    private static ?bool $listPriceReady = null;

    /** Whether order items record their regular price yet (needed to show discounts) */
    public function listPriceReady(): bool
    {
        return self::$listPriceReady ??= Schema::hasColumn('order_items', 'list_price');
    }

    public static function statusGroup(?string $status): string
    {
        return match ($status) {
            'completed', 'complete', 'delivered' => 'completed',
            'cancelled', 'failed' => 'cancelled',
            'returned', 'refunded' => 'returned',
            default => 'in_progress',
        };
    }

    public static function isCod(?string $method): bool
    {
        return ($method ?? 'cod') === 'cod';
    }

    /**
     * Each order paid in [from, to] with its money split out, oldest first.
     * The sale's ledger time is when it counts, as on the dashboard.
     */
    public function paidSales(int $vendorId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $credits = VendorTransaction::forVendor($vendorId)
            ->where('type', 'credit')
            ->where('category', 'order_revenue')
            ->whereNotNull('order_id')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['order_id', 'amount', 'created_at']);

        if ($credits->isEmpty()) {
            return collect();
        }

        $orders = Order::query()
            ->whereIn('id', $credits->pluck('order_id')->unique())
            ->with(['items' => fn ($q) => $q->select(array_merge(
                ['id', 'order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'subtotal'],
                $this->listPriceReady() ? ['list_price'] : [],
            ))])
            ->get(['id', 'order_number', 'payment_method', 'delivery_fee', 'tax_amount', 'discount_amount', 'total_amount'])
            ->keyBy('id');

        return $credits->map(function ($credit) use ($orders) {
            $order = $orders->get($credit->order_id);
            $collected = round((float) $credit->amount, 2);
            $items = collect($order?->items ?? [])->map(function ($item) {
                $paid = (float) $item->unit_price;
                $list = $item->list_price ?? null;
                $qty = (int) $item->quantity;

                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name ?: 'Item',
                    'quantity' => $qty,
                    'sales' => round($paid * $qty, 2),
                    'discount' => $list === null ? 0.0 : round(max(0, (float) $list - $paid) * $qty, 2),
                    'tracked' => $list !== null,
                ];
            });
            $delivery = round((float) ($order?->delivery_fee ?? 0), 2);
            $tax = round((float) ($order?->tax_amount ?? 0), 2);
            $discounts = round($items->sum('discount') + (float) ($order?->discount_amount ?? 0), 2);

            return [
                'order_id' => $credit->order_id,
                'order_number' => $order?->order_number,
                'at' => Carbon::parse($credit->created_at),
                'method' => $order?->payment_method ?? 'cod',
                'collected' => $collected,
                'delivery' => $delivery,
                'tax' => $tax,
                'discounts' => $discounts,
                // Item value at regular prices: what was collected before discounts, fees and tax
                'gross' => round($collected - $delivery - $tax + $discounts, 2),
                'items' => $items,
                'discounts_tracked' => $items->isNotEmpty() && $items->every(fn ($i) => $i['tracked']),
            ];
        });
    }

    /** Refunds paid back in [from, to]: [order_id, amount, at] */
    public function refunds(int $vendorId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return VendorTransaction::forVendor($vendorId)
            ->where('type', 'debit')
            ->where('category', 'refund')
            ->whereBetween('created_at', [$from, $to])
            ->get(['order_id', 'amount', 'created_at'])
            ->map(fn ($t) => ['order_id' => $t->order_id, 'amount' => round((float) $t->amount, 2), 'at' => Carbon::parse($t->created_at)]);
    }

    /** The sales statement for the period */
    public function totals(Collection $sales, Collection $refunds): array
    {
        $collected = round((float) $sales->sum('collected'), 2);
        $refunded = round((float) $refunds->sum('amount'), 2);
        $cod = $sales->filter(fn ($s) => self::isCod($s['method']));
        $online = $sales->reject(fn ($s) => self::isCod($s['method']));
        $orders = $sales->count();

        return [
            'orders' => $orders,
            'gross_sales' => round((float) $sales->sum('gross'), 2),
            'discounts' => round((float) $sales->sum('discounts'), 2),
            'delivery_fees' => round((float) $sales->sum('delivery'), 2),
            'tax' => round((float) $sales->sum('tax'), 2),
            'collected' => $collected,
            'refunds' => $refunded,
            'refund_count' => $refunds->count(),
            'net_sales' => round($collected - $refunded, 2),
            'average_order' => $orders ? round($collected / $orders, 2) : 0,
            'cod' => ['amount' => round((float) $cod->sum('collected'), 2), 'orders' => $cod->count()],
            'ewallet' => ['amount' => round((float) $online->sum('collected'), 2), 'orders' => $online->count()],
            // Some sales are older than discount tracking, so their discounts are unknown
            'discounts_partial' => $sales->contains(fn ($s) => ! $s['discounts_tracked']),
        ];
    }

    /** E-wallet sales split by provider (GCash, Maya…) */
    public function ewalletMethods(Collection $sales): array
    {
        return $sales->reject(fn ($s) => self::isCod($s['method']))
            ->groupBy('method')
            ->map(fn ($rows, $method) => ['method' => $method, 'amount' => round((float) $rows->sum('collected'), 2), 'orders' => $rows->count()])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /** Day, week, month or year rows across the period (picked from its length unless given) */
    public function byDate(Collection $sales, Collection $refunds, CarbonInterface $from, CarbonInterface $to, ?string $unit = null): array
    {
        $days = (int) ceil($from->diffInDays($to));
        $unit ??= $days <= 62 ? 'day' : ($days <= 190 ? 'week' : 'month');

        $rows = [];
        $cursor = Carbon::instance($from)->copy()->startOf($unit);
        while ($cursor->lte($to)) {
            $start = $cursor->copy()->max($from);
            $end = $cursor->copy()->endOf($unit)->min($to);
            $in = $sales->filter(fn ($s) => $s['at']->between($start, $end));
            $back = $refunds->filter(fn ($r) => $r['at']->between($start, $end));
            $t = $this->totals($in, $back);
            $rows[] = [
                'key' => $start->toDateString(),
                'label' => match ($unit) {
                    'day' => $start->format('D, M j'),
                    'week' => $start->format('M j') . ' – ' . $end->format('M j'),
                    'year' => $start->format('Y'),
                    default => $start->format('F Y'),
                },
                'short' => match ($unit) {
                    'day' => $start->format('j'),
                    'week' => $start->format('M j'),
                    'year' => $start->format('Y'),
                    default => $start->format('M'),
                },
                'orders' => $t['orders'],
                'gross_sales' => $t['gross_sales'],
                'discounts' => $t['discounts'],
                'refunds' => $t['refunds'],
                'net_sales' => $t['net_sales'],
                'cod' => $t['cod']['amount'],
                'ewallet' => $t['ewallet']['amount'],
            ];
            $cursor->add(1, $unit);
        }

        return ['unit' => $unit, 'rows' => $rows];
    }

    /** What sold, best sellers first */
    public function byProduct(Collection $sales, int $limit = 50): array
    {
        $items = $sales->flatMap(fn ($s) => $s['items']->map(fn ($i) => $i + ['order_id' => $s['order_id']]));
        $total = (float) $items->sum('sales');

        return $items
            ->groupBy(fn ($i) => $i['product_id'] ? 'p' . $i['product_id'] : 'n' . mb_strtolower($i['name']))
            ->map(fn ($rows) => [
                'product_id' => $rows->first()['product_id'],
                'name' => $rows->first()['name'],
                'quantity' => (int) $rows->sum('quantity'),
                'orders' => $rows->pluck('order_id')->unique()->count(),
                'discounts' => round((float) $rows->sum('discount'), 2),
                'sales' => round((float) $rows->sum('sales'), 2),
                'share' => $total > 0 ? round($rows->sum('sales') / $total * 100, 1) : 0,
            ])
            ->sortByDesc('sales')
            ->take($limit)
            ->values()
            ->all();
    }

    /** Orders placed in the period by outcome: count and value of each */
    public function outcomes(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as amount')
            ->groupBy('status')
            ->get();

        $groups = collect(self::STATUS_GROUPS)->mapWithKeys(fn ($g) => [$g => ['orders' => 0, 'amount' => 0.0]])->all();
        foreach ($rows as $row) {
            $g = self::statusGroup($row->status);
            $groups[$g]['orders'] += (int) $row->orders;
            $groups[$g]['amount'] = round($groups[$g]['amount'] + (float) $row->amount, 2);
        }
        $placed = array_sum(array_column($groups, 'orders'));

        return [
            'placed' => $placed,
            'groups' => $groups,
            'completion_rate' => $placed ? round($groups['completed']['orders'] / $placed * 100, 1) : null,
            'cancellation_rate' => $placed ? round($groups['cancelled']['orders'] / $placed * 100, 1) : null,
        ];
    }
}
