<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\ProductReport;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionCheckout;
use App\Support\PaymentLedger;
use App\Support\ReportPeriod;
use App\Support\VendorDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The admin home: the whole business at a glance. Read-only.
 */
class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard?range=30d
     */
    public function overview(Request $request): JsonResponse
    {
        $first = collect([Order::query()->min('created_at'), User::query()->min('created_at')])->filter()->min();
        $period = ReportPeriod::make($request->input('range'), $first ? CarbonImmutable::parse($first) : null);
        $now = CarbonImmutable::now();

        $paidOrders = Order::query()->whereIn('payment_status', ['paid', 'refunded'])->whereNotNull('paid_at')
            ->get(['id', 'vendor_id', 'payment_method', 'total_amount', 'paid_at']);
        $subscriptionPayments = VendorSubscriptionCheckout::query()->where('status', 'paid')->whereNotNull('paid_at')
            ->get(['vendor_id', 'amount', 'paid_at']);
        $orders = Order::query()->get(['id', 'status', 'created_at']);
        $customers = User::query()->where('role', 'customer')->get(['id', 'created_at']);
        $vendors = User::query()->where('role', 'vendor')->get(['id', 'created_at']);

        $between = fn (Collection $rows, string $at, CarbonImmutable $from, CarbonImmutable $to) =>
            $rows->filter(fn ($r) => $r->{$at} && $r->{$at}->gte($from) && $r->{$at}->lte($to));
        $inPeriod = fn (Collection $rows, string $at) => $between($rows, $at, $period->start, $period->end);
        $inPrevious = fn (Collection $rows, string $at) => $period->previousStart
            ? $between($rows, $at, $period->previousStart, $period->start->subSecond())
            : null;
        $money = fn (?Collection $rows, string $field) => $rows === null ? null : round((float) $rows->sum(fn ($r) => (float) $r->{$field}), 2);

        $sales = $inPeriod($paidOrders, 'paid_at');
        $salesTotal = $money($sales, 'total_amount');
        $periodOrders = $inPeriod($orders, 'created_at');

        $subscriptions = VendorSubscription::query()->get();
        $paying = $subscriptions->filter(fn ($s) => $s->status === SubscriptionStatus::Active && $s->paid_at
            && ($s->subscription_ends_at === null || $now->lt($s->subscription_ends_at)));
        $trialing = $subscriptions->filter(fn ($s) => $s->isTrialActive($now));

        $kpis = [
            'total_sales' => $salesTotal,
            'total_sales_previous' => $money($inPrevious($paidOrders, 'paid_at'), 'total_amount'),
            'paid_orders' => $sales->count(),
            'average_order_value' => $sales->count() ? round($salesTotal / $sales->count(), 2) : 0,
            'lifetime_sales' => $money($paidOrders, 'total_amount'),
            'platform_revenue' => $money($inPeriod($subscriptionPayments, 'paid_at'), 'amount'),
            'platform_revenue_previous' => $money($inPrevious($subscriptionPayments, 'paid_at'), 'amount'),
            'mrr' => round((float) $paying->sum(fn ($s) => (float) $s->paid_amount), 2),
            'orders' => $periodOrders->count(),
            'orders_previous' => $inPrevious($orders, 'created_at')?->count(),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'in_progress_orders' => $orders->whereIn('status', ['processing', 'out_for_delivery'])->count(),
            'customers' => $customers->count(),
            'new_customers' => $inPeriod($customers, 'created_at')->count(),
            'new_customers_previous' => $inPrevious($customers, 'created_at')?->count(),
            'vendors' => $vendors->count(),
            'new_vendors' => $inPeriod($vendors, 'created_at')->count(),
            'active_subscriptions' => $paying->count() + $trialing->count(),
            'paying_subscriptions' => $paying->count(),
            'trialing_subscriptions' => $trialing->count(),
        ];

        $series = array_map(function ($bucket) use ($between, $money, $paidOrders, $subscriptionPayments, $orders, $customers) {
            $to = $bucket['end']->subSecond();
            return [
                'label' => $bucket['label'],
                'sales' => $money($between($paidOrders, 'paid_at', $bucket['start'], $to), 'total_amount'),
                'platform_revenue' => $money($between($subscriptionPayments, 'paid_at', $bucket['start'], $to), 'amount'),
                'orders' => $between($orders, 'created_at', $bucket['start'], $to)->count(),
                'new_customers' => $between($customers, 'created_at', $bucket['start'], $to)->count(),
            ];
        }, $period->buckets());

        $topProducts = $this->topProducts($sales->pluck('id'));
        $topVendors = $this->topVendors($sales);
        $statusMix = $periodOrders->countBy('status')->map(fn ($count, $status) => ['status' => $status, 'count' => $count])->sortByDesc('count')->values()->all();
        $codShare = $salesTotal ? round($money($sales->where('payment_method', 'cod'), 'total_amount') / $salesTotal * 100) : null;
        $orderSummary = app(AdminOrderController::class)->summary()->getData(true)['data'];

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $period->range,
                'generated_at' => $now->toIso8601String(),
                'kpis' => $kpis,
                'series' => ['unit' => $period->unit(), 'points' => $series],
                'status_mix' => $statusMix,
                'top_products' => $topProducts,
                'top_vendors' => $topVendors,
                'insights' => $this->insights($kpis, $topProducts, $topVendors, $codShare, $orderSummary),
                'attention' => [
                    'vendor_applications' => VendorApplication::query()->where('status', 'pending')->count(),
                    'product_reports' => ProductReport::query()->where('status', 'pending')->count(),
                    'open_disputes' => OrderRequest::query()->where('status', 'pending')->count(),
                    'order_issues' => $orderSummary['issues'],
                ],
                'recent_orders' => $this->recentOrders(),
                'recent_transactions' => PaymentLedger::format(
                    PaymentLedger::query()->orderByDesc('occurred_at')->limit(6)->get()
                ),
            ],
        ]);
    }

    /** Best-selling items by quantity in the period's paid orders */
    private function topProducts(Collection $orderIds): array
    {
        if ($orderIds->isEmpty()) {
            return [];
        }

        return DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->selectRaw('product_id, max(product_name) as name, sum(quantity) as quantity, sum(subtotal) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name ?: 'Removed product',
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    private function topVendors(Collection $sales): array
    {
        $totals = $sales->groupBy('vendor_id')
            ->map(fn ($rows, $vendorId) => ['vendor_id' => $vendorId, 'sales' => round((float) $rows->sum('total_amount'), 2), 'orders' => $rows->count()])
            ->sortByDesc('sales')
            ->take(5);
        $vendors = VendorDirectory::details($totals->keys());

        return $totals->map(fn ($row) => $row + ['vendor' => $vendors[$row['vendor_id']] ?? VendorDirectory::unknown($row['vendor_id'])])->values()->all();
    }

    private function recentOrders(): array
    {
        $orders = Order::query()->withCount('items')->latest()->limit(6)
            ->get(['id', 'order_number', 'user_id', 'vendor_id', 'status', 'payment_status', 'payment_method', 'total_amount', 'created_at']);
        $customers = User::query()->whereIn('id', $orders->pluck('user_id'))->get(['id', 'name', 'surname'])->keyBy('id');
        $vendors = VendorDirectory::details($orders->pluck('vendor_id'));

        return $orders->map(fn ($o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'method_label' => PaymentLedger::methodLabel($o->payment_method),
            'total_amount' => (float) $o->total_amount,
            'items_count' => $o->items_count,
            'customer' => PaymentLedger::personName($customers[$o->user_id] ?? null),
            'vendor' => $vendors[$o->vendor_id] ?? VendorDirectory::unknown($o->vendor_id),
            'created_at' => $o->created_at?->toIso8601String(),
        ])->all();
    }

    /**
     * Plain-language observations. Each has a tone (good, warn, info) and
     * only appears when the data supports it.
     */
    private function insights(array $k, array $topProducts, array $topVendors, ?float $codShare, array $orderSummary): array
    {
        $peso = fn ($v) => '₱' . number_format((float) $v, 2);
        $insights = [];

        if ($k['total_sales_previous'] !== null) {
            if ($k['total_sales_previous'] > 0) {
                $change = ($k['total_sales'] - $k['total_sales_previous']) / $k['total_sales_previous'] * 100;
                $insights[] = [
                    'tone' => $change >= 0 ? 'good' : 'warn',
                    'title' => sprintf('Sales %s %.0f%%', $change >= 0 ? 'up' : 'down', abs($change)),
                    'detail' => "{$peso($k['total_sales'])} this period vs {$peso($k['total_sales_previous'])} the period before.",
                ];
            } elseif ($k['total_sales'] > 0) {
                $insights[] = ['tone' => 'good', 'title' => 'First sales in a while', 'detail' => "{$peso($k['total_sales'])} in sales, after none the period before."];
            } else {
                $insights[] = ['tone' => 'warn', 'title' => 'No paid sales this period', 'detail' => 'No order was paid in this period or the one before it.'];
            }
        }

        if ($topProducts) {
            $top = $topProducts[0];
            $insights[] = ['tone' => 'info', 'title' => "Best seller: {$top['name']}", 'detail' => "{$top['quantity']} sold for {$peso($top['revenue'])}."];
        }
        if (count($topVendors) > 0 && $k['total_sales'] > 0) {
            $lead = $topVendors[0];
            $share = round($lead['sales'] / $k['total_sales'] * 100);
            $orders = $lead['orders'] . ' paid order' . ($lead['orders'] === 1 ? '' : 's');
            $insights[] = count($topVendors) === 1
                ? ['tone' => 'info', 'title' => "Only {$lead['vendor']['store_name']} made sales", 'detail' => "{$orders} worth {$peso($lead['sales'])}."]
                : ['tone' => $share >= 80 ? 'warn' : 'info', 'title' => "{$lead['vendor']['store_name']} leads with {$share}% of sales", 'detail' => "{$orders} worth {$peso($lead['sales'])}."];
        }
        if ($codShare !== null) {
            $insights[] = ['tone' => 'info'] + match (true) {
                $codShare >= 100 => ['title' => 'All sales were cash on delivery', 'detail' => 'No order was paid online through PayMongo.'],
                $codShare <= 0 => ['title' => 'All sales were paid online', 'detail' => 'Every paid order went through PayMongo.'],
                default => ['title' => "{$codShare}% of sales were cash on delivery", 'detail' => 'The rest was paid online through PayMongo.'],
            };
        }
        if ($k['pending_orders'] > 0) {
            $stale = collect($orderSummary['issue_breakdown'])->firstWhere('key', 'stale_pending')['count'] ?? 0;
            $insights[] = [
                'tone' => $stale ? 'warn' : 'info',
                'title' => "{$k['pending_orders']} orders waiting on vendors",
                'detail' => $stale ? "{$stale} of them have been pending for more than " . \App\Support\PlatformSettings::get('stale_pending_days') . " days." : 'None of them looks stuck.',
            ];
        }
        if ($k['trialing_subscriptions'] > 0) {
            $insights[] = [
                'tone' => 'info',
                'title' => "{$k['trialing_subscriptions']} vendor" . ($k['trialing_subscriptions'] === 1 ? ' is' : 's are') . ' on a free trial',
                'detail' => 'Converting them would add to the monthly recurring revenue.',
            ];
        }

        return array_slice($insights, 0, 6);
    }
}
