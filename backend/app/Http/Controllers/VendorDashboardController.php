<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\VendorTransaction;
use App\Services\SubscriptionAccessService;
use App\Subscriptions\SubscriptionPlans;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The vendor portal's home page: sales, orders, stock, top sellers,
 * recent money movements and the plan, in one request.
 *
 * Open to every plan. Each section is only filled in when the plan
 * includes the module it comes from; otherwise it is listed in `locked`.
 */
class VendorDashboardController extends Controller
{
    use ResolvesOwner;

    private const RANGES = ['7d', '30d', 'month', 'year'];

    public function overview(Request $request, SubscriptionAccessService $access): JsonResponse
    {
        $data = $request->validate(['range' => 'nullable|in:' . implode(',', self::RANGES)]);
        $range = $data['range'] ?? '30d';
        $vendorId = $this->resolveOwnerId($request);
        $user = $request->user();

        [$from, $to, $prevFrom, $prevTo] = $this->bounds($range);
        // One subscription lookup for the whole page
        $summary = $access->accessSummary($user);
        $modules = $summary['modules'] ?? [];
        $can = fn (string $module) => in_array(SubscriptionPlans::canonicalModule($module), $modules, true);
        $locked = array_values(array_filter(['orders', 'products', 'finance'], fn ($m) => ! $can($m)));

        $result = [
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'locked' => $locked,
            'subscription' => $this->subscription($summary),
        ];

        if ($can('orders')) {
            $result['sales'] = $this->sales($vendorId, $from, $to, $prevFrom, $prevTo);
            $result['orders'] = $this->orders($vendorId, $from, $to);
            $result['chart'] = $this->chart($vendorId, $from, $to, $range === 'year' ? 'month' : 'day');
            $result['top_flowers'] = $this->topFlowers($vendorId, $from, $to);
        }
        if ($can('products')) {
            $result['products'] = $this->products($vendorId);
        }
        if ($can('finance')) {
            $result['revenue'] = $this->revenue($vendorId, $from, $to, $prevFrom, $prevTo);
            $result['transactions'] = $this->recentTransactions($vendorId);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** Orders that count as a sale: paid, or completed (cash on delivery), and not refunded. */
    private function salesQuery(int $vendorId, Carbon $from, Carbon $to)
    {
        return Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])
            ->where('payment_status', '!=', 'refunded')
            ->where(fn ($q) => $q->where('payment_status', 'paid')->orWhere('status', 'completed'));
    }

    private function sales(int $vendorId, Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $now = $this->salesQuery($vendorId, $from, $to)->selectRaw('COUNT(*) as n, COALESCE(SUM(total_amount), 0) as total')->first();
        $before = $this->salesQuery($vendorId, $prevFrom, $prevTo)->sum('total_amount');
        $total = (float) $now->total;

        return [
            'total' => round($total, 2),
            'count' => (int) $now->n,
            'average' => $now->n ? round($total / $now->n, 2) : 0,
            'change_pct' => $this->change($total, (float) $before),
        ];
    }

    private function orders(int $vendorId, Carbon $from, Carbon $to): array
    {
        $inRange = Order::query()->where('vendor_id', $vendorId)->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'failed')
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        // Work waiting right now, whenever the order was placed.
        $open = Order::query()->where('vendor_id', $vendorId)->whereIn('status', ['pending', 'processing'])
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $recent = Order::query()->where('vendor_id', $vendorId)->where('status', '!=', 'failed')
            ->latest()->limit(5)
            ->get(['order_number', 'status', 'payment_status', 'total_amount', 'delivery_contact_name', 'created_at'])
            ->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total' => (float) $o->total_amount,
                'customer' => $o->delivery_contact_name,
                'created_at' => $o->created_at?->toIso8601String(),
            ]);

        return [
            'total' => (int) $inRange->sum(),
            'completed' => (int) ($inRange['completed'] ?? 0),
            'cancelled' => (int) ($inRange['cancelled'] ?? 0),
            'pending' => (int) ($open['pending'] ?? 0),
            'processing' => (int) ($open['processing'] ?? 0),
            'recent' => $recent,
        ];
    }

    private function chart(int $vendorId, Carbon $from, Carbon $to, string $unit): array
    {
        $format = $unit === 'month' ? 'Y-m' : 'Y-m-d';
        $orders = $this->salesQuery($vendorId, $from, $to)->get(['total_amount', 'created_at']);

        $buckets = [];
        foreach (CarbonPeriod::create($from->copy()->startOf($unit), "1 {$unit}", $to) as $date) {
            $buckets[$date->format($format)] = [
                'key' => $date->format($format),
                'label' => $unit === 'month' ? $date->format('M') : $date->format('M j'),
                'sales' => 0.0,
                'orders' => 0,
            ];
        }
        foreach ($orders as $order) {
            $key = $order->created_at->format($format);
            if (isset($buckets[$key])) {
                $buckets[$key]['sales'] = round($buckets[$key]['sales'] + (float) $order->total_amount, 2);
                $buckets[$key]['orders']++;
            }
        }

        return ['unit' => $unit, 'points' => array_values($buckets)];
    }

    private function topFlowers(int $vendorId, Carbon $from, Carbon $to): array
    {
        $orderIds = $this->salesQuery($vendorId, $from, $to)->select('id');

        $rows = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('product_id, MAX(product_name) as name, MAX(product_image) as image, SUM(quantity) as qty, SUM(subtotal) as total')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        $categories = Product::withoutGlobalScopes()->whereIn('id', $rows->pluck('product_id')->filter())
            ->pluck('category', 'id');

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'image' => $r->image,
            'category' => $categories[$r->product_id] ?? null,
            'quantity' => (int) $r->qty,
            'total' => round((float) $r->total, 2),
        ])->all();
    }

    private function products(int $vendorId): array
    {
        $base = fn () => Product::withoutGlobalScopes()->whereNull('deleted_at')->where('owner_id', $vendorId);
        $low = fn ($q) => $q->where('quantity_in_stock', '>', 0)->whereNotNull('min_stock_level')
            ->whereColumn('quantity_in_stock', '<=', 'min_stock_level');

        $alerts = $base()
            ->where(fn ($q) => $q->where('quantity_in_stock', '<=', 0)->orWhere(fn ($q) => $low($q)))
            ->with('images')
            ->orderBy('quantity_in_stock')
            ->limit(6)
            ->get(['id', 'product_name', 'quantity_in_stock', 'min_stock_level', 'status'])
            ->map(fn (Product $p) => [
                'name' => $p->product_name,
                'image' => ($p->images->firstWhere('is_primary', true) ?? $p->images->first())?->image_url,
                'stock' => (int) $p->quantity_in_stock,
                'min' => $p->min_stock_level !== null ? (int) $p->min_stock_level : null,
                'out' => (int) $p->quantity_in_stock <= 0,
            ]);

        return [
            'total' => $base()->count(),
            'active' => $base()->where('status', 'active')->count(),
            'low_stock' => $low($base())->count(),
            'out_of_stock' => $base()->where('quantity_in_stock', '<=', 0)->count(),
            'alerts' => $alerts,
        ];
    }

    private function revenue(int $vendorId, Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $sum = function (Carbon $a, Carbon $b) use ($vendorId) {
            $rows = VendorTransaction::forVendor($vendorId)->whereBetween('created_at', [$a, $b])
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('type', 'credit')->where('category', 'order_revenue'))
                    ->orWhere(fn ($q) => $q->where('type', 'debit')->where('category', 'refund')))
                ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');

            return [(float) ($rows['credit'] ?? 0), (float) ($rows['debit'] ?? 0)];
        };

        [$earned, $refunded] = $sum($from, $to);
        [$prevEarned, $prevRefunded] = $sum($prevFrom, $prevTo);
        $net = $earned - $refunded;

        return [
            'earned' => round($earned, 2),
            'refunded' => round($refunded, 2),
            'net' => round($net, 2),
            'change_pct' => $this->change($net, $prevEarned - $prevRefunded),
        ];
    }

    /**
     * The latest money in and out, each saying where it came from or went:
     * ledger entries (customer payments, refunds, stock purchases…) and paid
     * payroll, which the finance dashboard also counts as an expense.
     */
    private function recentTransactions(int $vendorId): array
    {
        $ledger = VendorTransaction::forVendor($vendorId)
            ->with(['order:id,order_number,user_id,delivery_contact_name', 'order.user:id,name,surname'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (VendorTransaction $t) {
                $meta = (array) ($t->metadata ?? []);
                $order = $t->order;
                $customer = $order?->delivery_contact_name
                    ?: trim(($order?->user?->name ?? '') . ' ' . ($order?->user?->surname ?? '')) ?: 'a customer';

                [$title, $party] = match ($t->category) {
                    'order_revenue' => ['Customer payment', "From {$customer}"],
                    'refund' => [! empty($meta['partial']) ? 'Partial refund' : 'Refund', "To {$customer}"],
                    'procurement' => ['Supplier purchase', 'To ' . ($meta['supplier_name'] ?? 'a supplier')],
                    'withdrawal' => ['Withdrawal', 'To your payout account'],
                    'adjustment' => isset($meta['funding_request_id'])
                        ? ['Stock purchase', 'For ' . ($meta['product_name'] ?? 'inventory')
                            . (isset($meta['approved_quantity']) ? " ({$meta['approved_quantity']})" : '')]
                        : ['Balance adjustment', $t->description ?: 'Manual adjustment'],
                    default => [ucfirst(str_replace('_', ' ', $t->category)), $t->description ?: ''],
                };

                return [
                    'key' => md5('txn' . $t->id),
                    'type' => $t->type,
                    'category' => $t->category,
                    'title' => $title,
                    'party' => $party,
                    'order_number' => $order?->order_number ?? ($meta['order_number'] ?? null),
                    'amount' => round((float) $t->amount, 2),
                    'at' => $t->created_at,
                ];
            });

        $payroll = Payroll::with('employee')
            ->where('owner_id', $vendorId)
            ->whereIn('status', ['approved', 'paid'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (Payroll $p) {
                $name = $p->employee?->full_name ?? $p->employee?->name ?? 'an employee';
                $period = $p->period_start && $p->period_end
                    ? $p->period_start->format('M j') . '–' . $p->period_end->format('M j')
                    : null;

                return [
                    'key' => md5('pay' . $p->id),
                    'type' => 'debit',
                    'category' => 'payroll',
                    'title' => 'Salary',
                    'party' => "To {$name}" . ($period ? " · {$period}" : ''),
                    'order_number' => null,
                    'amount' => round((float) $p->net_salary, 2),
                    'at' => ($p->status === 'paid' && $p->paid_at) ? $p->paid_at : $p->updated_at,
                ];
            })
            ->filter(fn ($t) => $t['amount'] > 0);

        return $ledger->concat($payroll)
            ->sortByDesc(fn ($t) => $t['at']?->getTimestamp() ?? 0)
            ->take(8)
            ->map(fn ($t) => ['created_at' => $t['at']?->toIso8601String()] + collect($t)->except('at')->all())
            ->values()
            ->all();
    }

    private function subscription(array $summary): array
    {
        $ends = $summary['trial_ends_at'] && $summary['status'] === 'trialing'
            ? $summary['trial_ends_at']
            : $summary['subscription_ends_at'];

        return [
            'active' => $summary['subscription_active'],
            'status' => $summary['status'],
            'plan_name' => $summary['plan_name'],
            'monthly_price' => $summary['monthly_price'],
            'ends_at' => $ends,
            'days_left' => $ends ? max(0, (int) ceil(now()->diffInHours(Carbon::parse($ends), false) / 24)) : null,
            'modules' => count($summary['modules']),
            'limits' => $summary['resource_limits'],
        ];
    }

    private function bounds(string $range): array
    {
        $now = now();
        [$from, $to] = match ($range) {
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        // The same stretch of time just before, to compare against.
        [$prevFrom, $prevTo] = match ($range) {
            'month' => [$from->copy()->subMonthNoOverflow(), $now->copy()->subMonthNoOverflow()->endOfDay()],
            'year' => [$from->copy()->subYear(), $now->copy()->subYear()->endOfDay()],
            default => [$from->copy()->subDays((int) ceil($from->diffInDays($to))), $from->copy()->subSecond()],
        };

        return [$from, $to, $prevFrom, $prevTo];
    }

    private function change(float $now, float $before): ?float
    {
        if ($before <= 0) {
            return $now > 0 ? null : 0.0;
        }

        return round((($now - $before) / $before) * 100, 1);
    }
}
