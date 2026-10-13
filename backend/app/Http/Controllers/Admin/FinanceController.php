<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\User;
use App\Models\VendorBalance;
use App\Models\VendorSubscriptionCheckout;
use App\Models\VendorTransaction;
use App\Services\ReturnCaseService;
use App\Support\PaymentLedger;
use App\Support\ReportPeriod;
use App\Support\VendorDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin finance: every payment on the platform, read-only.
 *
 * Two kinds of money move through BloomCraft:
 *  - vendor sales: customers paying vendors for orders (COD or PayMongo).
 *    Vendors are credited the full order total; the platform takes no cut.
 *  - platform earnings: vendors paying BloomCraft for their subscription.
 *
 * Gateway references are masked and raw gateway payloads/checkout URLs are
 * never returned. Nothing here changes a payment, refund or balance.
 */
class FinanceController extends Controller
{
    /** Refund Management filters → case statuses */
    private const CASE_FILTERS = [
        'pending' => ['pending'],
        'approved' => ['approved'],
        'completed' => ['completed'],
        'rejected' => ['rejected', 'withdrawn'],
        'open' => ['pending', 'approved'],
    ];


    /**
     * GET /api/admin/finance/overview?range=30d
     */
    public function overview(Request $request): JsonResponse
    {
        $period = $this->period($request);

        $paidOrders = Order::query()
            ->whereIn('payment_status', ['paid', 'refunded'])
            ->whereNotNull('paid_at')
            ->get(['id', 'vendor_id', 'payment_method', 'total_amount', 'paid_at']);
        $subscriptions = VendorSubscriptionCheckout::query()
            ->where('status', 'paid')->whereNotNull('paid_at')
            ->get(['id', 'vendor_id', 'amount', 'paid_at']);
        $refunds = $this->refundEvents();

        $in = fn (Collection $rows, string $at, CarbonImmutable $from, CarbonImmutable $to) =>
            $rows->filter(fn ($row) => $row->{$at} && $row->{$at}->gte($from) && $row->{$at}->lte($to));
        $sum = fn (Collection $rows, string $field) => round((float) $rows->sum(fn ($row) => (float) $row->{$field}), 2);

        $sales = $in($paidOrders, 'paid_at', $period->start, $period->end);
        $earnings = $in($subscriptions, 'paid_at', $period->start, $period->end);
        $refunded = $refunds->filter(fn ($r) => $r->at->gte($period->start) && $r->at->lte($period->end));

        $previous = null;
        if ($period->previousStart) {
            $before = $period->start->subSecond();
            $previous = [
                'vendor_sales' => $sum($in($paidOrders, 'paid_at', $period->previousStart, $before), 'total_amount'),
                'platform_earnings' => $sum($in($subscriptions, 'paid_at', $period->previousStart, $before), 'amount'),
            ];
        }

        $gross = $sum($sales, 'total_amount');
        $cod = $sum($sales->where('payment_method', 'cod'), 'total_amount');
        $platform = $sum($earnings, 'amount');
        $refundTotal = round((float) $refunded->sum('amount'), 2);

        // Payment attempts created in the period, by outcome
        $states = PaymentLedger::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->selectRaw('state, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('state')
            ->get()
            ->keyBy('state');
        $successful = (int) ($states['successful']->total ?? 0);
        $failed = (int) ($states['failed']->total ?? 0);

        // Money still to come in, whatever the period
        $pendingNow = PaymentLedger::query()->where('state', 'pending')
            ->selectRaw("channel, count(*) as total, coalesce(sum(amount), 0) as amount")
            ->groupBy('channel')->get()->keyBy('channel');

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $period->range,
                'period' => ['start' => $period->start->toIso8601String(), 'end' => $period->end->toIso8601String()],
                'generated_at' => now()->toIso8601String(),
                'kpis' => [
                    'total_processed' => round($gross + $platform, 2),
                    'vendor_sales' => $gross,
                    'vendor_sales_net' => round($gross - $refundTotal, 2),
                    'vendor_sales_orders' => $sales->count(),
                    'platform_earnings' => $platform,
                    'platform_payments' => $earnings->count(),
                    'refunds' => $refundTotal,
                    'refund_count' => $refunded->count(),
                    'cod_sales' => $cod,
                    'paymongo_sales' => round($gross - $cod, 2),
                    'previous' => $previous,
                    'success_rate' => ($successful + $failed) ? round($successful / ($successful + $failed) * 100, 1) : null,
                    'pending_cod' => ['count' => (int) ($pendingNow['cod']->total ?? 0), 'amount' => round((float) ($pendingNow['cod']->amount ?? 0), 2)],
                    'pending_paymongo' => ['count' => (int) ($pendingNow['paymongo']->total ?? 0), 'amount' => round((float) ($pendingNow['paymongo']->amount ?? 0), 2)],
                    'open_disputes' => OrderRequest::query()->where('status', 'pending')->count(),
                    'vendor_balances' => round((float) VendorBalance::query()->sum('balance'), 2),
                ],
                'states' => collect(PaymentLedger::STATES)->map(fn ($state) => [
                    'state' => $state,
                    'count' => (int) ($states[$state]->total ?? 0),
                    'amount' => round((float) ($states[$state]->amount ?? 0), 2),
                ])->all(),
                'methods' => $sales->groupBy('payment_method')->map(fn ($rows, $method) => [
                    'method' => $method,
                    'count' => $rows->count(),
                    'amount' => $sum($rows, 'total_amount'),
                ])->sortByDesc('amount')->values()->all(),
                'series' => [
                    'unit' => $period->unit(),
                    'points' => array_map(function ($bucket) use ($in, $sum, $paidOrders, $subscriptions, $refunds) {
                        $to = $bucket['end']->subSecond();
                        return [
                            'label' => $bucket['label'],
                            'vendor_sales' => $sum($in($paidOrders, 'paid_at', $bucket['start'], $to), 'total_amount'),
                            'platform_earnings' => $sum($in($subscriptions, 'paid_at', $bucket['start'], $to), 'amount'),
                            'refunds' => round((float) $refunds->filter(fn ($r) => $r->at->gte($bucket['start']) && $r->at->lte($to))->sum('amount'), 2),
                        ];
                    }, $period->buckets()),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/finance/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $page = $this->filteredLedger($request)->paginate($perPage);
        $rows = collect($page->items());

        return response()->json([
            'success' => true,
            'data' => PaymentLedger::format($rows),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/finance/transactions/export
     */
    public function exportTransactions(Request $request): StreamedResponse
    {
        $rows = PaymentLedger::format($this->filteredLedger($request)->limit(10000)->get());

        return $this->csv('bloomcraft-transactions-' . now()->format('Y-m-d') . '.csv', function ($out) use ($rows) {
            fputcsv($out, ['Date', 'Type', 'Reference', 'Vendor', 'Customer', 'Channel', 'Method', 'Status', 'Amount (PHP)', 'Gateway ref. (masked)']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['occurred_at'] ? CarbonImmutable::parse($row['occurred_at'])->format('Y-m-d H:i') : '',
                    $row['source'] === 'subscription' ? 'Platform subscription' : 'Vendor sale',
                    $row['reference'],
                    $row['vendor']['store_name'],
                    $row['customer']['name'] ?? '',
                    $row['channel'] === 'cod' ? 'Cash on delivery' : 'PayMongo',
                    $row['method_label'],
                    ucfirst($row['state']),
                    number_format($row['amount'], 2, '.', ''),
                    $row['gateway_ref'] ?? '',
                ]);
            }
        });
    }

    /**
     * GET /api/admin/finance/vendors?range=30d
     */
    public function vendors(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->vendorSales($this->period($request))]);
    }

    /**
     * GET /api/admin/finance/refunds
     * Refunds that happened and return/refund requests (disputes).
     */
    public function refunds(Request $request): JsonResponse
    {
        $refunds = $this->refundEvents()->sortByDesc(fn ($r) => $r->at->getTimestamp())->take(100)->values();

        $disputes = OrderRequest::query()
            ->with(['order:id,order_number,vendor_id,user_id,total_amount,payment_method,payment_status,status', 'user:id,name,surname'])
            ->when(isset(self::CASE_FILTERS[$request->input('dispute_status')]),
                fn ($q) => $q->whereIn('status', self::CASE_FILTERS[$request->input('dispute_status')]))
            ->orderByRaw("case when status in ('pending','approved') then 0 else 1 end")
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $caseService = app(ReturnCaseService::class);

        $vendors = VendorDirectory::details($refunds->pluck('vendor_id')->merge($disputes->pluck('order.vendor_id')));
        $customers = User::query()->whereIn('id', $refunds->pluck('customer_id')->filter()->unique())
            ->get(['id', 'name', 'surname'])->keyBy('id');

        $allRefunds = $this->refundEvents();
        $since = now()->subDays(30);
        $requestCounts = OrderRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $oldestOpen = OrderRequest::query()->whereIn('status', OrderRequest::OPEN_STATUSES)->min('created_at');
        // How long customers and shops take to agree (or the shop to decline)
        $decided = OrderRequest::query()->whereNotNull('settled_at')->where('settled_at', '>=', now()->subDays(90))->get(['created_at', 'settled_at'])
            ->map(fn ($d) => $d->created_at->diffInMinutes($d->settled_at) / 60);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'refunded_total' => round((float) $allRefunds->sum('amount'), 2),
                    'refund_count' => $allRefunds->count(),
                    'refunded_30d' => round((float) $allRefunds->filter(fn ($r) => $r->at->gte($since))->sum('amount'), 2),
                    'refund_count_30d' => $allRefunds->filter(fn ($r) => $r->at->gte($since))->count(),
                    'not_debited' => $allRefunds->where('vendor_debited', false)->count(),
                    'requests' => [
                        'negotiating' => (int) ($requestCounts['pending'] ?? 0),
                        'agreed' => (int) ($requestCounts['approved'] ?? 0),
                        'completed' => (int) ($requestCounts['completed'] ?? 0),
                        'closed' => (int) (($requestCounts['rejected'] ?? 0) + ($requestCounts['withdrawn'] ?? 0)),
                        // Kept for older screens
                        'pending' => (int) (($requestCounts['pending'] ?? 0) + ($requestCounts['approved'] ?? 0)),
                        'approved' => (int) ($requestCounts['completed'] ?? 0),
                        'rejected' => (int) (($requestCounts['rejected'] ?? 0) + ($requestCounts['withdrawn'] ?? 0)),
                    ],
                    'oldest_open_at' => $oldestOpen ? \Carbon\Carbon::parse($oldestOpen)->toIso8601String() : null,
                    'avg_decision_hours_90d' => $decided->count() ? round($decided->avg(), 1) : null,
                ],
                'refunds' => $refunds->map(fn ($r) => [
                    'order_id' => $r->order_id,
                    'order_number' => $r->order_number,
                    'amount' => $r->amount,
                    'method' => $r->method,
                    'method_label' => PaymentLedger::methodLabel($r->method),
                    'refunded_at' => $r->at->toIso8601String(),
                    'vendor_debited' => $r->vendor_debited,
                    'vendor' => $vendors[$r->vendor_id] ?? VendorDirectory::unknown($r->vendor_id),
                    'customer' => PaymentLedger::personName($customers[$r->customer_id] ?? null),
                ])->all(),
                'disputes' => $disputes->map(fn (OrderRequest $d) => $caseService->summary($d) + [
                    'vendor_response' => $d->closed_reason ?? $d->admin_notes,
                    'order_id' => $d->order_id,
                    'order_number' => $d->order?->order_number,
                    'amount' => (float) ($d->order?->total_amount ?? 0),
                    'payment_status' => $d->order?->payment_status,
                    'method_label' => PaymentLedger::methodLabel($d->order?->payment_method),
                    'vendor' => $vendors[$d->order?->vendor_id] ?? VendorDirectory::unknown($d->order?->vendor_id),
                    'customer' => PaymentLedger::personName($d->user),
                    'updated_at' => ($d->last_activity_at ?? $d->updated_at)?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * GET /api/admin/finance/refunds/{id}
     * One return/refund case with its full conversation. View only: the
     * customer and the shop settle it between them.
     */
    public function refundCase(int $id): JsonResponse
    {
        $case = OrderRequest::query()->with(['user:id,name,surname', 'order:id,order_number,vendor_id,total_amount,payment_method,payment_status,status'])->find($id);
        if (!$case) {
            return response()->json(['success' => false, 'message' => 'Case not found'], 404);
        }
        $vendor = VendorDirectory::details([$case->order?->vendor_id])[$case->order?->vendor_id] ?? VendorDirectory::unknown($case->order?->vendor_id);

        return response()->json(['success' => true, 'data' => app(ReturnCaseService::class)->detail($case) + [
            'customer' => PaymentLedger::personName($case->user),
            'vendor' => $vendor,
            // The vendor's book entry, once the customer confirmed the refund
            'ledger_amount' => VendorTransaction::query()->where('order_id', $case->order_id)->where('category', 'refund')->value('amount'),
        ]]);
    }

    /**
     * GET /api/admin/finance/report/export?range=30d
     * One CSV with the summary, the monthly/daily breakdown, vendor sales and refunds.
     */
    public function exportReport(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $overview = $this->overview($request)->getData(true)['data'];
        $vendors = $this->vendorSales($period);
        $refunds = $this->refundEvents()->filter(fn ($r) => $r->at->gte($period->start) && $r->at->lte($period->end));
        $k = $overview['kpis'];
        $money = fn ($v) => number_format((float) $v, 2, '.', '');

        return $this->csv('bloomcraft-financial-report-' . $period->range . '-' . now()->format('Y-m-d') . '.csv', function ($out) use ($period, $overview, $vendors, $refunds, $k, $money) {
            fputcsv($out, ['BloomCraft financial report']);
            fputcsv($out, ['Period', $period->start->format('Y-m-d') . ' to ' . $period->end->format('Y-m-d')]);
            fputcsv($out, ['Generated', now()->format('Y-m-d H:i')]);
            fputcsv($out, []);

            fputcsv($out, ['SUMMARY', 'Amount (PHP)']);
            fputcsv($out, ['Total processed', $money($k['total_processed'])]);
            fputcsv($out, ['Vendor sales (gross)', $money($k['vendor_sales'])]);
            fputcsv($out, ['  of which cash on delivery', $money($k['cod_sales'])]);
            fputcsv($out, ['  of which PayMongo', $money($k['paymongo_sales'])]);
            fputcsv($out, ['Refunds', $money($k['refunds'])]);
            fputcsv($out, ['Vendor sales (net of refunds)', $money($k['vendor_sales_net'])]);
            fputcsv($out, ['Platform earnings (subscriptions)', $money($k['platform_earnings'])]);
            fputcsv($out, ['Commission on vendor sales', '0.00']);
            fputcsv($out, ['Payment success rate', $k['success_rate'] === null ? 'n/a' : $k['success_rate'] . '%']);
            fputcsv($out, []);

            fputcsv($out, ['PAYMENTS BY STATUS (created in period)', 'Count', 'Amount (PHP)']);
            foreach ($overview['states'] as $state) {
                fputcsv($out, [ucfirst($state['state']), $state['count'], $money($state['amount'])]);
            }
            fputcsv($out, []);

            fputcsv($out, ['BREAKDOWN', 'Vendor sales', 'Platform earnings', 'Refunds']);
            foreach ($overview['series']['points'] as $point) {
                fputcsv($out, [$point['label'], $money($point['vendor_sales']), $money($point['platform_earnings']), $money($point['refunds'])]);
            }
            fputcsv($out, []);

            fputcsv($out, ['VENDOR SALES', 'Paid orders', 'Gross sales', 'Cash on delivery', 'PayMongo', 'Refunds', 'Net sales', 'Awaiting payment', 'Subscription paid', 'Current balance']);
            foreach ($vendors as $v) {
                fputcsv($out, [
                    $v['vendor']['store_name'], $v['orders'], $money($v['gross']), $money($v['cod']), $money($v['paymongo']),
                    $money($v['refunds']), $money($v['net']), $money($v['pending']), $money($v['subscription_paid']), $money($v['balance']),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['REFUNDS', 'Order', 'Amount (PHP)', 'Method', 'Vendor debited']);
            foreach ($refunds as $r) {
                fputcsv($out, [$r->at->format('Y-m-d H:i'), $r->order_number, $money($r->amount), PaymentLedger::methodLabel($r->method), $r->vendor_debited ? 'Yes' : 'No']);
            }
        });
    }

    // ── Ledger ───────────────────────────────────────────────────────────

    private function filteredLedger(Request $request): Builder
    {
        $query = PaymentLedger::query();

        if (in_array($request->input('source'), ['order', 'subscription'], true)) {
            $query->where('source', $request->input('source'));
        }
        if (in_array($request->input('channel'), ['cod', 'paymongo'], true)) {
            $query->where('channel', $request->input('channel'));
        }
        if (in_array($request->input('state'), PaymentLedger::STATES, true)) {
            $query->where('state', $request->input('state'));
        }
        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', CarbonImmutable::parse($request->input('to'))->endOfDay());
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $people = User::query()
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(surname) like ?', [$like])
                ->orWhereRaw('lower(email) like ?', [$like])
                ->pluck('id');
            $vendorIds = $people->merge(VendorDirectory::idsMatching($search));

            $query->where(fn ($q) => $q
                ->whereRaw('lower(reference) like ?', [$like])
                ->orWhereIn('vendor_id', $vendorIds)
                ->orWhereIn('customer_id', $people));
        }

        $sort = match ($request->input('sort')) {
            'oldest' => ['occurred_at', 'asc'],
            'amount_high' => ['amount', 'desc'],
            'amount_low' => ['amount', 'asc'],
            default => ['occurred_at', 'desc'],
        };

        return $query->orderBy(...$sort)->orderByDesc('source_id');
    }

    // ── Vendors & refunds ────────────────────────────────────────────────

    private function vendorSales(ReportPeriod $period): array
    {
        $from = $period->start;
        $to = $period->end;

        $paid = Order::query()
            ->whereIn('payment_status', ['paid', 'refunded'])
            ->whereBetween('paid_at', [$from, $to])
            ->get(['vendor_id', 'payment_method', 'total_amount']);
        $pending = Order::query()->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'failed', 'returned', 'refunded'])
            ->get(['vendor_id', 'total_amount']);
        $refunds = $this->refundEvents()->filter(fn ($r) => $r->at->gte($from) && $r->at->lte($to));
        $subscriptions = VendorSubscriptionCheckout::query()->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])->get(['vendor_id', 'amount']);
        $balances = VendorBalance::query()->get(['vendor_id', 'balance'])->keyBy('vendor_id');

        $vendorIds = $paid->pluck('vendor_id')
            ->merge($pending->pluck('vendor_id'))
            ->merge($refunds->pluck('vendor_id'))
            ->merge($subscriptions->pluck('vendor_id'))
            ->merge(User::query()->where('role', 'vendor')->pluck('id'))
            ->unique();
        $vendors = VendorDirectory::details($vendorIds);

        return $vendorIds->map(function ($id) use ($paid, $pending, $refunds, $subscriptions, $balances, $vendors) {
            $sales = $paid->where('vendor_id', $id);
            $gross = round((float) $sales->sum('total_amount'), 2);
            $cod = round((float) $sales->where('payment_method', 'cod')->sum('total_amount'), 2);
            $refunded = round((float) $refunds->where('vendor_id', $id)->sum('amount'), 2);

            return [
                'vendor' => $vendors[$id] ?? VendorDirectory::unknown($id),
                'orders' => $sales->count(),
                'gross' => $gross,
                'cod' => $cod,
                'paymongo' => round($gross - $cod, 2),
                'refunds' => $refunded,
                'net' => round($gross - $refunded, 2),
                'pending' => round((float) $pending->where('vendor_id', $id)->sum('total_amount'), 2),
                'subscription_paid' => round((float) $subscriptions->where('vendor_id', $id)->sum('amount'), 2),
                'balance' => round((float) ($balances[$id]->balance ?? 0), 2),
            ];
        })->sortByDesc('gross')->values()->all();
    }

    /**
     * Every refund: the vendor-debit ledger entries, plus refunded orders
     * that never got one (shown as "vendor not debited").
     */
    private function refundEvents(): Collection
    {
        $entries = VendorTransaction::query()->where('category', 'refund')->get(['order_id', 'vendor_id', 'amount', 'created_at']);
        $orders = Order::query()
            ->where(fn ($q) => $q->where('payment_status', 'refunded')->orWhere('status', 'refunded')->orWhereIn('id', $entries->pluck('order_id')))
            ->get(['id', 'order_number', 'vendor_id', 'user_id', 'payment_method', 'total_amount', 'updated_at'])
            ->keyBy('id');

        $events = $entries->map(fn ($t) => (object) [
            'order_id' => $t->order_id,
            'order_number' => $orders[$t->order_id]->order_number ?? null,
            'vendor_id' => $t->vendor_id,
            'customer_id' => $orders[$t->order_id]->user_id ?? null,
            'method' => $orders[$t->order_id]->payment_method ?? null,
            'amount' => round((float) $t->amount, 2),
            'at' => CarbonImmutable::parse($t->created_at),
            'vendor_debited' => true,
        ]);

        $debited = $entries->pluck('order_id')->flip();
        foreach ($orders as $order) {
            if (!isset($debited[$order->id])) {
                $events->push((object) [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'vendor_id' => $order->vendor_id,
                    'customer_id' => $order->user_id,
                    'method' => $order->payment_method,
                    'amount' => round((float) $order->total_amount, 2),
                    'at' => CarbonImmutable::parse($order->updated_at),
                    'vendor_debited' => false,
                ]);
            }
        }

        return $events->values();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function period(Request $request): ReportPeriod
    {
        $first = collect([Order::query()->min('created_at'), VendorSubscriptionCheckout::query()->min('created_at')])->filter()->min();

        return ReportPeriod::make($request->input('range'), $first ? CarbonImmutable::parse($first) : null);
    }

    private function csv(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads ₱ and accents correctly
            $write($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
