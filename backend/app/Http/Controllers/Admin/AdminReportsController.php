<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\ProductTaxonomy;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionCheckout;
use App\Subscriptions\SubscriptionPlans;
use App\Support\VendorDirectory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports & analytics: sales over time, best sellers, vendor comparisons,
 * cancellation/refund rates and subscription growth. The same numbers feed
 * the page, the CSV and the PDF.
 */
class AdminReportsController extends Controller
{
    private const UNITS = ['day', 'week', 'month', 'year'];
    private const FINISHED = ['completed', 'cancelled', 'failed', 'returned', 'refunded'];

    /** GET /api/admin/analytics */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->build($request)]);
    }

    /** GET /api/admin/analytics/export?format=csv|pdf */
    public function export(Request $request): Response|StreamedResponse
    {
        $report = $this->build($request);
        $name = 'bloomcraft-report-' . $report['period']['unit'] . '-' . $report['period']['from'] . '-to-' . $report['period']['to'];

        if ($request->input('format') === 'pdf') {
            $sections = array_values(array_intersect((array) $request->input('sections', []), ['sales', 'products', 'vendors', 'cancellations', 'subscriptions'])) ?: ['sales', 'products', 'vendors', 'cancellations', 'subscriptions'];

            return Pdf::loadView('reports.analytics', ['r' => $report, 'sections' => $sections, 'generated' => now()])
                ->setPaper('a4', 'portrait')
                ->download("{$name}.pdf");
        }

        return $this->csv($report, "{$name}.csv");
    }

    // ── Building the report ──────────────────────────────────────────────

    private function build(Request $request): array
    {
        [$unit, $from, $to] = $this->window($request);
        $length = $from->diffInSeconds($to);
        $prevFrom = $from->subSeconds($length + 1);
        $prevTo = $from->subSecond();
        $buckets = $this->buckets($unit, $from, $to);

        $orders = Order::query()
            ->where(fn ($q) => $q->whereBetween('created_at', [$prevFrom, $to])->orWhereBetween('paid_at', [$prevFrom, $to]))
            ->get(['id', 'vendor_id', 'user_id', 'status', 'payment_status', 'payment_method', 'total_amount', 'created_at', 'paid_at', 'delivered_at', 'updated_at']);
        $isPaid = fn ($o) => in_array($o->payment_status, ['paid', 'refunded'], true) && $o->paid_at;
        $paidIn = fn ($a, $b) => $orders->filter(fn ($o) => $isPaid($o) && $o->paid_at->between($a, $b));
        $placedIn = fn ($a, $b) => $orders->filter(fn ($o) => $o->created_at->between($a, $b));

        $subs = VendorSubscriptionCheckout::query()->where('status', 'paid')->whereBetween('paid_at', [$prevFrom, $to])
            ->get(['vendor_id', 'plan_key', 'amount', 'paid_at']);
        $subsIn = fn ($a, $b) => $subs->filter(fn ($c) => $c->paid_at->between($a, $b));
        $firstPaid = VendorSubscriptionCheckout::query()->where('status', 'paid')->whereNotNull('paid_at')
            ->selectRaw('vendor_id, min(paid_at) as first_at')->groupBy('vendor_id')->pluck('first_at', 'vendor_id')
            ->map(fn ($at) => CarbonImmutable::parse($at));
        $customers = User::query()->where('role', User::ROLE_CUSTOMER)->whereBetween('created_at', [$prevFrom, $to])->pluck('created_at');

        $sales = $paidIn($from, $to);
        $prevSales = $paidIn($prevFrom, $prevTo);
        $placed = $placedIn($from, $to);
        $prevPlaced = $placedIn($prevFrom, $prevTo);
        $subRevenue = round((float) $subsIn($from, $to)->sum('amount'), 2);
        $prevSubRevenue = round((float) $subsIn($prevFrom, $prevTo)->sum('amount'), 2);

        $summary = [
            'sales' => $this->money($sales->sum('total_amount')),
            'sales_previous' => $this->money($prevSales->sum('total_amount')),
            'paid_orders' => $sales->count(),
            'paid_orders_previous' => $prevSales->count(),
            'average_order' => $sales->count() ? $this->money($sales->sum('total_amount') / $sales->count()) : 0,
            'average_order_previous' => $prevSales->count() ? $this->money($prevSales->sum('total_amount') / $prevSales->count()) : 0,
            'orders_placed' => $placed->count(),
            'orders_placed_previous' => $prevPlaced->count(),
            'cancellation_rate' => $this->rate($placed, ['cancelled', 'failed']),
            'cancellation_rate_previous' => $this->rate($prevPlaced, ['cancelled', 'failed']),
            'refund_rate' => $this->rate($placed, ['refunded', 'returned']),
            'refund_rate_previous' => $this->rate($prevPlaced, ['refunded', 'returned']),
            'subscription_revenue' => $subRevenue,
            'subscription_revenue_previous' => $prevSubRevenue,
            'new_customers' => $customers->filter(fn ($at) => $at->between($from, $to))->count(),
            'new_customers_previous' => $customers->filter(fn ($at) => $at->between($prevFrom, $prevTo))->count(),
        ];

        $series = array_map(function ($b) use ($paidIn, $placedIn, $subsIn, $firstPaid) {
            $paid = $paidIn($b['start'], $b['end']);
            $made = $placedIn($b['start'], $b['end']);
            $finished = $made->whereIn('status', self::FINISHED)->count();

            return [
                'label' => $b['label'],
                'start' => $b['start']->toDateString(),
                'sales' => $this->money($paid->sum('total_amount')),
                'paid_orders' => $paid->count(),
                'average_order' => $paid->count() ? $this->money($paid->sum('total_amount') / $paid->count()) : 0,
                'orders_placed' => $made->count(),
                'cancelled' => $made->whereIn('status', ['cancelled', 'failed'])->count(),
                'refunded' => $made->whereIn('status', ['refunded', 'returned'])->count(),
                'cancellation_rate' => $finished ? round($made->whereIn('status', ['cancelled', 'failed'])->count() / $finished * 100, 1) : null,
                'refund_rate' => $finished ? round($made->whereIn('status', ['refunded', 'returned'])->count() / $finished * 100, 1) : null,
                'subscription_revenue' => $this->money($subsIn($b['start'], $b['end'])->sum('amount')),
                'new_paying_vendors' => $firstPaid->filter(fn ($at) => $at->between($b['start'], $b['end']))->count(),
            ];
        }, $buckets);

        return [
            'period' => [
                'unit' => $unit,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'previous_from' => $prevFrom->toDateString(),
                'previous_to' => $prevTo->toDateString(),
                'label' => $from->format('M j, Y') . ' – ' . $to->format('M j, Y'),
            ],
            'summary' => $summary,
            'series' => $series,
            'best_sellers' => $this->bestSellers($sales->pluck('id')),
            'vendors' => $this->vendors($sales, $placed, $prevSales),
            'cancellations' => $this->cancellations($placed, $from, $to),
            'subscriptions' => $this->subscriptions($subsIn($from, $to), $subRevenue, $prevSubRevenue, $firstPaid, $from, $to),
        ];
    }

    /** [unit, from, to] from ?unit=&from=&to= (sensible defaults per unit) */
    private function window(Request $request): array
    {
        $unit = in_array($request->input('unit'), self::UNITS, true) ? $request->input('unit') : 'day';
        $now = CarbonImmutable::now();

        $to = $request->filled('to') ? CarbonImmutable::parse($request->input('to'))->endOfDay() : $now->endOfDay();
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'))->startOfDay()
            : match ($unit) {
                'day' => $to->subDays(29)->startOfDay(),
                'week' => $to->subWeeks(11)->startOfWeek(),
                'month' => $to->subMonthsNoOverflow(11)->startOfMonth(),
                'year' => $to->subYears(4)->startOfYear(),
            };

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }
        // Keep charts readable: at most ~120 buckets
        $max = ['day' => 120, 'week' => 104, 'month' => 60, 'year' => 20][$unit];
        if (count($this->buckets($unit, $from, $to)) > $max) {
            $from = match ($unit) {
                'day' => $to->subDays($max - 1)->startOfDay(),
                'week' => $to->subWeeks($max - 1)->startOfWeek(),
                'month' => $to->subMonthsNoOverflow($max - 1)->startOfMonth(),
                'year' => $to->subYears($max - 1)->startOfYear(),
            };
        }

        return [$unit, $from, $to];
    }

    private function buckets(string $unit, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cursor = match ($unit) {
            'day' => $from->startOfDay(),
            'week' => $from->startOfWeek(),
            'month' => $from->startOfMonth(),
            'year' => $from->startOfYear(),
        };
        $buckets = [];
        while ($cursor->lte($to) && count($buckets) < 400) {
            $next = match ($unit) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
                'year' => $cursor->addYear(),
            };
            $buckets[] = [
                'start' => $cursor->max($from),
                'end' => $next->subSecond()->min($to),
                'label' => match ($unit) {
                    'day' => $cursor->format('M j'),
                    'week' => 'Wk of ' . $cursor->format('M j'),
                    'month' => $cursor->format('M Y'),
                    'year' => $cursor->format('Y'),
                },
            ];
            $cursor = $next;
        }

        return $buckets;
    }

    private function bestSellers(Collection $orderIds): array
    {
        if ($orderIds->isEmpty()) {
            return ['products' => [], 'categories' => [], 'flower_types' => [], 'occasions' => []];
        }

        $items = DB::table('order_items')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $orderIds)
            ->get([
                'order_items.product_id', 'order_items.product_name', 'order_items.quantity', 'order_items.subtotal',
                'products.category', 'products.flower_type', 'products.occasion_tags', 'products.owner_id',
            ]);
        $labels = ProductTaxonomy::query()->get(['type', 'value', 'label'])->groupBy('type')->map(fn ($r) => $r->pluck('label', 'value'));
        $totalRevenue = (float) $items->sum('subtotal') ?: 1;

        $group = function (Collection $rows, callable $key, string $type) use ($labels, $totalRevenue) {
            return $rows->groupBy($key)->filter(fn ($r, $k) => $k !== '' && $k !== null)
                ->map(fn ($r, $k) => [
                    'value' => $k,
                    'label' => $labels[$type][$k] ?? Str::headline((string) $k),
                    'quantity' => (int) $r->sum('quantity'),
                    'revenue' => $this->money($r->sum('subtotal')),
                    'share' => round($r->sum('subtotal') / $totalRevenue * 100, 1),
                ])
                ->sortByDesc('revenue')->values()->take(10)->all();
        };

        $vendors = VendorDirectory::details($items->pluck('owner_id'));
        $products = $items->groupBy(fn ($i) => $i->product_id ?? 'x-' . $i->product_name)
            ->map(fn ($r) => [
                'product_id' => $r->first()->product_id,
                'name' => $r->first()->product_name ?: 'Removed product',
                'vendor' => $vendors[$r->first()->owner_id]['store_name'] ?? '—',
                'category' => $labels['category'][$r->first()->category] ?? Str::headline((string) $r->first()->category),
                'quantity' => (int) $r->sum('quantity'),
                'revenue' => $this->money($r->sum('subtotal')),
                'share' => round($r->sum('subtotal') / $totalRevenue * 100, 1),
            ])
            ->sortByDesc('quantity')->values()->take(10)->all();

        // A product with two occasions counts towards both
        $occasionRows = $items->flatMap(function ($i) {
            $tags = is_string($i->occasion_tags) ? (json_decode($i->occasion_tags, true) ?: []) : (array) $i->occasion_tags;
            return collect($tags)->map(fn ($t) => (object) ['occasion' => $t, 'quantity' => $i->quantity, 'subtotal' => $i->subtotal]);
        });

        return [
            'products' => $products,
            'categories' => $group($items, fn ($i) => $i->category, 'category'),
            'flower_types' => $group($items, fn ($i) => $i->flower_type, 'flower_type'),
            'occasions' => $group($occasionRows, fn ($i) => $i->occasion, 'occasion'),
        ];
    }

    private function vendors(Collection $sales, Collection $placed, Collection $prevSales): array
    {
        $ids = $sales->pluck('vendor_id')->merge($placed->pluck('vendor_id'))->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $details = VendorDirectory::details($ids);
        $ratings = DB::table('product_reviews')->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->whereIn('products.owner_id', $ids)->whereNull('product_reviews.hidden_at')
            ->selectRaw('products.owner_id, avg(product_reviews.rating) as rating, count(*) as reviews')
            ->groupBy('products.owner_id')->get()->keyBy('owner_id');
        $total = (float) $sales->sum('total_amount') ?: 1;

        return $ids->map(function ($id) use ($sales, $placed, $prevSales, $details, $ratings, $total) {
            $s = $sales->where('vendor_id', $id);
            $p = $placed->where('vendor_id', $id);
            $finished = $p->whereIn('status', self::FINISHED);
            $rate = fn ($statuses) => $finished->count() ? round($finished->whereIn('status', $statuses)->count() / $finished->count() * 100, 1) : null;
            $hours = $p->where('status', 'completed')->filter(fn ($o) => $o->delivered_at)
                ->map(fn ($o) => $o->created_at->diffInMinutes($o->delivered_at) / 60);
            $revenue = (float) $s->sum('total_amount');
            $prev = (float) $prevSales->where('vendor_id', $id)->sum('total_amount');

            return [
                'vendor' => $details[$id] ?? VendorDirectory::unknown($id),
                'sales' => $this->money($revenue),
                'share' => round($revenue / $total * 100, 1),
                'growth' => $prev > 0 ? round(($revenue - $prev) / $prev * 100, 1) : null,
                'paid_orders' => $s->count(),
                'average_order' => $s->count() ? $this->money($revenue / $s->count()) : 0,
                'orders_placed' => $p->count(),
                'completion_rate' => $rate(['completed']),
                'cancellation_rate' => $rate(['cancelled', 'failed']),
                'refund_rate' => $rate(['refunded', 'returned']),
                'avg_fulfilment_hours' => $hours->count() ? round($hours->avg(), 1) : null,
                'rating' => isset($ratings[$id]) ? round((float) $ratings[$id]->rating, 1) : null,
                'reviews' => (int) ($ratings[$id]->reviews ?? 0),
            ];
        })->sortByDesc('sales')->values()->all();
    }

    private function cancellations(Collection $placed, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $requests = OrderRequest::query()->whereBetween('created_at', [$from, $to])->get(['type', 'status']);

        return [
            'cancelled' => $placed->whereIn('status', ['cancelled', 'failed'])->count(),
            'refunded' => $placed->whereIn('status', ['refunded', 'returned'])->count(),
            'finished' => $placed->whereIn('status', self::FINISHED)->count(),
            'by_method' => $placed->groupBy('payment_method')->map(function ($rows, $method) {
                $finished = $rows->whereIn('status', self::FINISHED)->count();
                return [
                    'method' => $method,
                    'orders' => $rows->count(),
                    'cancelled' => $rows->whereIn('status', ['cancelled', 'failed'])->count(),
                    'cancellation_rate' => $finished ? round($rows->whereIn('status', ['cancelled', 'failed'])->count() / $finished * 100, 1) : null,
                ];
            })->values()->all(),
            'requests' => [
                'returns' => $requests->where('type', 'return')->count(),
                'refunds' => $requests->where('type', 'refund')->count(),
                'approved' => $requests->where('status', 'approved')->count(),
                'rejected' => $requests->where('status', 'rejected')->count(),
                'open' => $requests->where('status', 'pending')->count(),
            ],
        ];
    }

    private function subscriptions(Collection $payments, float $revenue, float $previous, Collection $firstPaid, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $now = now();
        $paying = VendorSubscription::query()->where('status', SubscriptionStatus::Active)->whereNotNull('paid_at')
            ->where(fn ($q) => $q->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '>', $now))->get();
        $mrr = $this->money($paying->sum(fn ($s) => (float) $s->paid_amount));

        return [
            'revenue' => $revenue,
            'previous' => $previous,
            'growth' => $previous > 0 ? round(($revenue - $previous) / $previous * 100, 1) : null,
            'payments' => $payments->count(),
            'mrr' => $mrr,
            'arr' => $this->money($mrr * 12),
            'paying_vendors' => $paying->count(),
            'new_paying_vendors' => $firstPaid->filter(fn ($at) => $at->between($from, $to))->count(),
            'by_plan' => $payments->groupBy('plan_key')->map(fn ($rows, $plan) => [
                'plan' => SubscriptionPlans::exists($plan) ? SubscriptionPlans::get($plan)['name'] : ucfirst($plan),
                'payments' => $rows->count(),
                'revenue' => $this->money($rows->sum('amount')),
            ])->sortByDesc('revenue')->values()->all(),
        ];
    }

    // ── CSV ──────────────────────────────────────────────────────────────

    private function csv(array $r, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $s = $r['summary'];
            $put = fn (array $row) => fputcsv($out, $row);
            $m = fn ($v) => number_format((float) $v, 2, '.', '');

            $put(['BloomCraft reports & analytics']);
            $put(['Period', $r['period']['label'], 'Grouped by', $r['period']['unit']]);
            $put(['Compared with', $r['period']['previous_from'] . ' to ' . $r['period']['previous_to']]);
            $put(['Generated', now()->format('Y-m-d H:i')]);
            $put([]);

            $put(['SUMMARY', 'This period', 'Previous period']);
            $put(['Sales (PHP)', $m($s['sales']), $m($s['sales_previous'])]);
            $put(['Paid orders', $s['paid_orders'], $s['paid_orders_previous']]);
            $put(['Average order (PHP)', $m($s['average_order']), $m($s['average_order_previous'])]);
            $put(['Orders placed', $s['orders_placed'], $s['orders_placed_previous']]);
            $put(['Cancellation rate %', $s['cancellation_rate'], $s['cancellation_rate_previous']]);
            $put(['Refund/return rate %', $s['refund_rate'], $s['refund_rate_previous']]);
            $put(['Subscription revenue (PHP)', $m($s['subscription_revenue']), $m($s['subscription_revenue_previous'])]);
            $put(['New customers', $s['new_customers'], $s['new_customers_previous']]);
            $put([]);

            $put(['SALES OVER TIME', 'Sales (PHP)', 'Paid orders', 'Average order', 'Orders placed', 'Cancelled', 'Refunded/returned', 'Cancellation %', 'Refund %', 'Subscription revenue', 'New paying vendors']);
            foreach ($r['series'] as $p) {
                $put([$p['label'], $m($p['sales']), $p['paid_orders'], $m($p['average_order']), $p['orders_placed'], $p['cancelled'], $p['refunded'], $p['cancellation_rate'] ?? '', $p['refund_rate'] ?? '', $m($p['subscription_revenue']), $p['new_paying_vendors']]);
            }
            $put([]);

            $put(['BEST-SELLING PRODUCTS', 'Vendor', 'Category', 'Quantity', 'Revenue (PHP)', 'Share %']);
            foreach ($r['best_sellers']['products'] as $p) {
                $put([$p['name'], $p['vendor'], $p['category'], $p['quantity'], $m($p['revenue']), $p['share']]);
            }
            foreach (['categories' => 'BEST-SELLING CATEGORIES', 'flower_types' => 'FLOWER TYPES', 'occasions' => 'OCCASIONS'] as $key => $title) {
                $put([]);
                $put([$title, 'Quantity', 'Revenue (PHP)', 'Share %']);
                foreach ($r['best_sellers'][$key] as $row) {
                    $put([$row['label'], $row['quantity'], $m($row['revenue']), $row['share']]);
                }
            }
            $put([]);

            $put(['VENDOR PERFORMANCE', 'Sales (PHP)', 'Share %', 'Growth %', 'Paid orders', 'Average order', 'Orders placed', 'Completion %', 'Cancellation %', 'Refund %', 'Avg fulfilment (h)', 'Rating', 'Reviews']);
            foreach ($r['vendors'] as $v) {
                $put([$v['vendor']['store_name'], $m($v['sales']), $v['share'], $v['growth'] ?? '', $v['paid_orders'], $m($v['average_order']), $v['orders_placed'], $v['completion_rate'] ?? '', $v['cancellation_rate'] ?? '', $v['refund_rate'] ?? '', $v['avg_fulfilment_hours'] ?? '', $v['rating'] ?? '', $v['reviews']]);
            }
            $put([]);

            $c = $r['cancellations'];
            $put(['CANCELLATIONS & REFUNDS']);
            $put(['Finished orders', $c['finished'], 'Cancelled', $c['cancelled'], 'Refunded/returned', $c['refunded']]);
            $put(['Payment method', 'Orders', 'Cancelled', 'Cancellation %']);
            foreach ($c['by_method'] as $row) {
                $put([$row['method'], $row['orders'], $row['cancelled'], $row['cancellation_rate'] ?? '']);
            }
            $put(['Return requests', $c['requests']['returns'], 'Refund requests', $c['requests']['refunds'], 'Approved', $c['requests']['approved'], 'Rejected', $c['requests']['rejected'], 'Open', $c['requests']['open']]);
            $put([]);

            $sub = $r['subscriptions'];
            $put(['SUBSCRIPTIONS']);
            $put(['Revenue (PHP)', $m($sub['revenue']), 'Previous', $m($sub['previous']), 'Growth %', $sub['growth'] ?? '']);
            $put(['MRR (PHP)', $m($sub['mrr']), 'ARR (PHP)', $m($sub['arr']), 'Paying vendors', $sub['paying_vendors'], 'New paying vendors', $sub['new_paying_vendors']]);
            $put(['Plan', 'Payments', 'Revenue (PHP)']);
            foreach ($sub['by_plan'] as $row) {
                $put([$row['plan'], $row['payments'], $m($row['revenue'])]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function rate(Collection $orders, array $statuses): ?float
    {
        $finished = $orders->whereIn('status', self::FINISHED)->count();
        return $finished ? round($orders->whereIn('status', $statuses)->count() / $finished * 100, 1) : null;
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }
}
