<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\VendorExpense;
use App\Models\VendorExpenseFile;
use App\Services\VendorBooksService;
use App\Traits\ResolvesOwner;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The vendor portal's Finance & business pages: sales & transactions,
 * expenses, profit & loss, reports and refund tracking.
 * Every figure comes from VendorBooksService so the pages agree.
 */
class VendorBooksController extends Controller
{
    use ResolvesOwner;

    public function __construct(private VendorBooksService $books) {}

    /** GET /vendor/books/transactions */
    public function transactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'direction' => 'nullable|in:in,out',
            'kind' => 'nullable|in:sale,refund,stock,payroll,expense,withdrawal,adjustment',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ] + $this->rangeRules());
        [$from, $to] = $this->range($request);
        $entries = $this->books->entries($this->resolveOwnerId($request), $from, $to);

        $sales = $entries->where('kind', 'sale');
        $online = $sales->filter(fn ($e) => ($e['payment_method'] ?? 'cod') !== 'cod');
        $kinds = $entries->countBy('kind');

        $search = mb_strtolower(trim($data['search'] ?? ''));
        $list = $entries
            ->when($data['direction'] ?? null, fn ($c, $d) => $c->where('direction', $d))
            ->when($data['kind'] ?? null, fn ($c, $k) => $c->where('kind', $k))
            ->filter(fn ($e) => $search === '' || str_contains(mb_strtolower($e['title'] . ' ' . $e['party'] . ' ' . ($e['order_number'] ?? '')), $search))
            ->values();

        $perPage = 15;
        $page = $data['page'] ?? 1;

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'stats' => [
                    'money_in' => round((float) $entries->where('direction', 'in')->sum('amount'), 2),
                    'money_out' => round((float) $entries->where('direction', 'out')->sum('amount'), 2),
                    'sales' => round((float) $sales->sum('amount'), 2),
                    'orders' => $sales->count(),
                    'average_order' => $sales->count() ? round((float) $sales->avg('amount'), 2) : 0,
                    'cod' => round((float) $sales->sum('amount') - (float) $online->sum('amount'), 2),
                    'online' => round((float) $online->sum('amount'), 2),
                ],
                'kinds' => $kinds,
                'entries' => $list->slice(($page - 1) * $perPage, $perPage)->map(fn ($e) => $this->publicEntry($e))->values(),
                'meta' => ['page' => $page, 'total' => $list->count(), 'last_page' => max(1, (int) ceil($list->count() / $perPage))],
            ],
        ]);
    }

    /** GET /vendor/books/expenses */
    public function expenses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(VendorExpense::CATEGORIES))],
            'search' => 'nullable|string|max:100',
        ] + $this->rangeRules());
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);

        $search = mb_strtolower(trim($data['search'] ?? ''));
        $ready = $this->books->booksReady();
        $rows = ! $ready ? collect() : VendorExpense::where('owner_id', $vendorId)
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->with('files')
            ->get();
        $list = $rows
            ->when($data['category'] ?? null, fn ($c, $cat) => $c->where('category', $cat))
            ->filter(fn ($x) => $search === '' || str_contains(mb_strtolower($x->description . ' ' . $x->payee . ' ' . $x->reference), $search))
            ->values();

        // Costs that are recorded automatically, so the shop sees everything in one place
        $totals = $this->books->totals($this->books->entries($vendorId, $from, $to));
        [$pFrom, $pTo] = $this->books->previousPeriod($from, $to);
        $previous = ! $ready ? 0.0 : (float) VendorExpense::where('owner_id', $vendorId)->whereBetween('spent_on', [$pFrom->toDateString(), $pTo->toDateString()])->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'expenses' => $list->map(fn ($x) => $this->formatExpense($x)),
                'total' => round((float) $rows->sum('amount'), 2),
                'previous_total' => round($previous, 2),
                // Everything recorded, flowers included; stock tells which ones count as stock purchases
                'by_category' => $rows->groupBy('category')
                    ->map(fn ($c, $cat) => ['category' => $cat, 'label' => VendorExpense::CATEGORIES[$cat] ?? ucfirst($cat), 'amount' => round((float) $c->sum('amount'), 2), 'count' => $c->count(), 'stock' => in_array($cat, VendorExpense::STOCK_CATEGORIES, true)])
                    ->sortByDesc('amount')->values(),
                'stock_total' => round((float) $rows->filter->isStock()->sum('amount'), 2),
                'without_files' => $rows->filter(fn ($x) => ! $x->receipt_path && $x->files->isEmpty())->count(),
                'stock_categories' => VendorExpense::STOCK_CATEGORIES,
                'max_files' => VendorExpense::MAX_FILES,
                'automatic' => [
                    ['key' => 'payroll', 'label' => 'Payroll', 'amount' => $totals['payroll'], 'hint' => 'Approved or paid salaries'],
                    ['key' => 'refunds', 'label' => 'Refunds', 'amount' => $totals['refunds'], 'hint' => 'Refunds customers confirmed'],
                    ['key' => 'stock', 'label' => 'Stock purchases', 'amount' => $totals['stock_purchases'], 'hint' => 'Approved funding requests'],
                ],
                'categories' => collect(VendorExpense::CATEGORIES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
                'payment_methods' => collect(VendorExpense::PAYMENT_METHODS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            ],
        ]);
    }

    /** POST /vendor/books/expenses */
    public function storeExpense(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $vendorId = $this->resolveOwnerId($request);
        $data = $this->validateExpense($request);
        $data['owner_id'] = $vendorId;
        $data['recorded_by'] = $this->actorName($request);

        try {
            [$data['receipt_path'], $data['receipt_type']] = $this->storeReceipt($request);
        } catch (\Throwable $e) {
            Log::warning('Expense receipt upload failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'The receipt could not be uploaded. Try again or save without it.'], 422);
        }

        $expense = VendorExpense::create($data);
        if ($error = $this->attachFiles($request, $expense)) {
            return $error;
        }

        return response()->json(['success' => true, 'message' => 'Expense recorded.', 'data' => $this->formatExpense($expense->load('files'))], 201);
    }

    /** POST /vendor/books/expenses/{id} (multipart update) */
    public function updateExpense(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $expense = VendorExpense::where('owner_id', $this->resolveOwnerId($request))->find($id);
        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found.'], 404);
        }
        $data = $this->validateExpense($request);

        try {
            [$path, $type] = $this->storeReceipt($request);
        } catch (\Throwable $e) {
            Log::warning('Expense receipt upload failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'The receipt could not be uploaded. Try again or save without it.'], 422);
        }
        if ($path || $request->boolean('remove_receipt')) {
            if ($expense->receipt_path) {
                rescue(fn () => CloudinaryHelper::destroy($expense->receipt_path), report: false);
            }
            $data['receipt_path'] = $path;
            $data['receipt_type'] = $type;
        }

        $expense->update($data);

        // Files ticked for removal go first, then any new ones (up to the limit)
        foreach ($expense->files()->whereIn('id', (array) $request->input('remove_files', []))->get() as $file) {
            rescue(fn () => CloudinaryHelper::destroy($file->public_id, ['type' => 'authenticated', 'resource_type' => $file->resource_type]), report: false);
            $file->delete();
        }
        if ($error = $this->attachFiles($request, $expense)) {
            return $error;
        }

        return response()->json(['success' => true, 'message' => 'Expense saved.', 'data' => $this->formatExpense($expense->fresh('files'))]);
    }

    /** DELETE /vendor/books/expenses/{id} */
    public function destroyExpense(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $expense = VendorExpense::where('owner_id', $this->resolveOwnerId($request))->find($id);
        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found.'], 404);
        }
        if ($expense->receipt_path) {
            rescue(fn () => CloudinaryHelper::destroy($expense->receipt_path), report: false);
        }
        foreach ($expense->files as $file) {
            rescue(fn () => CloudinaryHelper::destroy($file->public_id, ['type' => 'authenticated', 'resource_type' => $file->resource_type]), report: false);
        }
        $expense->delete();

        return response()->json(['success' => true, 'message' => 'Expense deleted.']);
    }

    /** GET /vendor/books/profit-loss */
    /**
     * GET /vendor/books/profit-loss (also /finance/profit-loss for ERP Finance staff)
     *
     * The statement for the period against the one before, twelve months and
     * the last three years side by side, and the costs that weigh most.
     * ?basis=sold|bought picks how flower and stock costs are counted.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $data = $request->validate(['basis' => ['nullable', Rule::in(VendorBooksService::COST_BASES)]] + $this->rangeRules());
        $basis = $data['basis'] ?? 'sold';
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);
        [$pFrom, $pTo] = $this->books->previousPeriod($from, $to);

        // One pass covers the twelve months and the three years up to the end of the period
        $monthsFrom = $to->copy()->startOfMonth()->subMonths(11);
        $yearsFrom = $to->copy()->startOfYear()->subYears(2);
        $history = $this->books->entries($vendorId, $monthsFrom->copy()->min($yearsFrom), $to);
        $between = fn (Carbon $a, Carbon $b) => $history->filter(fn ($e) => $e['at']->between($a, $b));
        $current = $this->books->totals($this->books->entries($vendorId, $from, $to), $basis);
        $sixFrom = $to->copy()->startOfMonth()->subMonths(5);

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'previous_range' => $this->rangeInfo($pFrom, $pTo),
                'basis' => $basis,
                'current' => $current,
                'previous' => $this->books->totals($this->books->entries($vendorId, $pFrom, $pTo), $basis),
                // Six months, kept for the vendor portal's chart
                'trend' => $this->books->buckets($between($sixFrom, $to), $sixFrom, $to, 'month', $basis),
                'monthly' => $this->books->buckets($between($monthsFrom, $to), $monthsFrom, $to, 'month', $basis),
                'yearly' => collect([2, 1, 0])->map(function ($back) use ($to, $between, $basis) {
                    $start = $to->copy()->startOfYear()->subYears($back);
                    $end = $back === 0 ? $to->copy() : $start->copy()->endOfYear();
                    $t = $this->books->totals($between($start, $end), $basis);

                    return ['year' => $start->year, 'to_date' => $back === 0, 'until' => $end->toDateString()] + Arr::only($t, [
                        'sales', 'refunds', 'net_sales', 'cogs', 'gross_profit', 'payroll', 'expenses', 'payment_fees', 'operating_expenses', 'net_profit', 'gross_margin', 'net_margin', 'orders',
                    ]);
                })->values(),
                'top_costs' => $this->topCosts($current),
            ],
        ]);
    }

    /** Every cost in the statement, biggest first, with its share of all costs */
    private function topCosts(array $t): array
    {
        $rows = collect([
            ['key' => 'cogs', 'label' => $t['cost_basis'] === 'bought' ? 'Flowers and stock bought' : 'Cost of items sold', 'amount' => $t['cogs'], 'group' => 'cost_of_goods'],
            ['key' => 'payroll', 'label' => 'Payroll', 'amount' => $t['payroll'], 'group' => 'operating'],
            ['key' => 'payment_fees', 'label' => 'Payment fees', 'amount' => $t['payment_fees'], 'group' => 'operating'],
        ])->concat(collect($t['expenses_by_category'])->map(fn ($c) => ['key' => 'cat-' . $c['category'], 'label' => $c['label'], 'amount' => $c['amount'], 'group' => 'operating']))
            ->filter(fn ($r) => $r['amount'] > 0)
            ->sortByDesc('amount')
            ->values();
        $total = (float) $rows->sum('amount');

        return $rows->map(fn ($r) => $r + ['share' => $total > 0 ? round($r['amount'] / $total * 100, 1) : 0])->all();
    }

    /**
     * GET /vendor/books/dashboard (also /finance/dashboard for ERP Finance staff)
     *
     * The finance overview: sales and net revenue, expenses and estimated
     * profit for the period, money still on its way in or out, twelve months
     * of revenue against expenses, and the latest money movements.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate($this->rangeRules());
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);
        [$pFrom, $pTo] = $this->books->previousPeriod($from, $to);

        $entries = $this->books->entries($vendorId, $from, $to);
        $current = $this->books->totals($entries);

        // Twelve months up to the end of the period
        $trendFrom = $to->copy()->startOfMonth()->subMonths(11);
        $trend = $this->books->buckets($this->books->entries($vendorId, $trendFrom, $to), $trendFrom, $to, 'month');

        // Cash on delivery the shop hasn't collected yet (online payments are counted once paid)
        $uncollected = Order::query()
            ->where('vendor_id', $vendorId)
            ->where('payment_method', 'cod')
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'failed', 'returned', 'refunded'])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as amount')
            ->first();

        [$pending, $negotiating] = $this->openRefunds($this->refundCases($vendorId));

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'previous_range' => $this->rangeInfo($pFrom, $pTo),
                'current' => $current,
                'previous' => $this->books->totals($this->books->entries($vendorId, $pFrom, $pTo)),
                'pending_payments' => [
                    'amount' => round((float) $uncollected->amount, 2),
                    'orders' => (int) $uncollected->orders,
                ],
                'pending_refunds' => [
                    'amount' => round((float) $pending->sum('amount') + (float) $negotiating->sum('amount'), 2),
                    'count' => $pending->count() + $negotiating->count(),
                    'agreed' => round((float) $pending->sum('amount'), 2),
                    'agreed_count' => $pending->count(),
                    'negotiating' => round((float) $negotiating->sum('amount'), 2),
                    'negotiating_count' => $negotiating->count(),
                ],
                'trend' => $trend,
                'recent' => $entries->take(8)->map(fn ($e) => $this->publicEntry($e))->values(),
            ],
        ]);
    }

    /** GET /vendor/books/reports */
    public function reports(Request $request): JsonResponse
    {
        $data = $request->validate(['unit' => ['nullable', Rule::in(VendorBooksService::BUCKETS)]] + $this->rangeRules());
        [$from, $to] = $this->range($request);
        $unit = $data['unit'] ?? 'day';
        if ($error = $this->tooManyBuckets($from, $to, $unit)) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }
        $entries = $this->books->entries($this->resolveOwnerId($request), $from, $to);

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'unit' => $unit,
                'rows' => $this->books->buckets($entries, $from, $to, $unit),
                'totals' => $this->books->totals($entries),
            ],
        ]);
    }

    /** GET /vendor/books/reports/export?format=csv|pdf */
    public function exportReport(Request $request): Response
    {
        $data = $request->validate([
            'unit' => ['nullable', Rule::in(VendorBooksService::BUCKETS)],
            'format' => 'required|in:csv,pdf',
        ] + $this->rangeRules());
        [$from, $to] = $this->range($request);
        $unit = $data['unit'] ?? 'day';
        if ($error = $this->tooManyBuckets($from, $to, $unit)) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }
        $entries = $this->books->entries($this->resolveOwnerId($request), $from, $to);
        $rows = $this->books->buckets($entries, $from, $to, $unit);
        $totals = $this->books->totals($entries);
        $shop = $request->user()?->vendor_data['store_name'] ?? 'Your shop';
        $name = 'bloomcraft-report-' . $from->format('Ymd') . '-' . $to->format('Ymd');

        if ($data['format'] === 'pdf') {
            return Pdf::loadView('reports.vendor-books', [
                'shop' => $shop, 'range' => $this->rangeInfo($from, $to), 'unit' => $unit,
                'rows' => $rows, 't' => $totals, 'generated' => now(),
            ])->setPaper('a4', 'landscape')->download("{$name}.pdf");
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, [$shop . ' — financial report', $from->format('M j, Y') . ' to ' . $to->format('M j, Y')]);
        fputcsv($csv, []);
        fputcsv($csv, ['Period', 'Orders', 'Sales', 'Refunds', 'Net sales', 'Cost of goods', 'Expenses', 'Net profit']);
        foreach ($rows as $r) {
            fputcsv($csv, [$r['label'], $r['orders'], $r['sales'], $r['refunds'], $r['net_sales'], $r['cogs'], $r['expenses'], $r['net_profit']]);
        }
        fputcsv($csv, ['Total', $totals['orders'], $totals['sales'], $totals['refunds'], $totals['net_sales'], $totals['cogs'], $totals['operating_expenses'], $totals['net_profit']]);
        fputcsv($csv, []);
        fputcsv($csv, ['Expenses by category']);
        fputcsv($csv, ['Payroll', $totals['payroll']]);
        foreach ($totals['expenses_by_category'] as $c) {
            fputcsv($csv, [$c['label'], $c['amount']]);
        }
        rewind($csv);
        $body = "\xEF\xBB\xBF" . stream_get_contents($csv);
        fclose($csv);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
        ]);
    }

    /** GET /vendor/books/refunds */
    public function refunds(Request $request): JsonResponse
    {
        $request->validate($this->rangeRules());
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);
        $entries = $this->books->entries($vendorId, $from, $to);
        $totals = $this->books->totals($entries);

        $cases = $this->refundCases($vendorId);
        [$pending, $negotiating] = $this->openRefunds($cases);
        $replacements = $cases->filter(fn (OrderRequest $c) => $c->settled_resolution === OrderRequest::REPLACEMENT
            && $c->completed_at && $c->completed_at->between($from, $to))->count();

        $refundEntries = $entries->whereIn('kind', ['refund', 'adjustment'])->values();
        $byMethod = $entries->where('kind', 'refund')->groupBy(fn ($e) => $e['refund_method'] ?? 'other')
            ->map(fn ($rows, $m) => ['method' => $m, 'label' => \App\Services\ReturnCaseService::REFUND_METHODS[$m] ?? 'Other', 'amount' => round((float) $rows->sum('amount'), 2), 'count' => $rows->count()])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'stats' => [
                    'refunded' => $totals['refunds'],
                    'refund_count' => $entries->where('kind', 'refund')->count(),
                    'refund_rate' => $totals['sales'] > 0 ? round($totals['refunds'] / $totals['sales'] * 100, 1) : null,
                    'pending' => round((float) $pending->sum('amount'), 2),
                    'pending_count' => $pending->count(),
                    'negotiating' => round((float) $negotiating->sum('amount'), 2),
                    'negotiating_count' => $negotiating->count(),
                    'replacements' => $replacements,
                    'adjustments_in' => $totals['adjustments_in'],
                    'adjustments_out' => $totals['adjustments_out'],
                ],
                'by_method' => $byMethod,
                'pending' => $pending,
                'negotiating' => $negotiating,
                'history' => $refundEntries->map(fn ($e) => $this->publicEntry($e)),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    /** Before the expenses migration has run, recording expenses can't work yet. */
    private function notReady(): ?JsonResponse
    {
        return $this->books->booksReady() ? null : response()->json([
            'success' => false,
            'message' => 'Expenses are still being set up. Please try again in a few minutes.',
        ], 503);
    }

    private function rangeRules(): array
    {
        return ['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from'];
    }

    /** [from, to] from the request; this month to date by default. */
    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    private function rangeInfo(Carbon $from, Carbon $to): array
    {
        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('M j, Y') . ' – ' . $to->format('M j, Y')];
    }

    private function tooManyBuckets(Carbon $from, Carbon $to, string $unit): ?string
    {
        $days = (int) ceil($from->diffInDays($to));
        $limits = ['day' => 93, 'week' => 106 * 7, 'month' => 60 * 31, 'year' => 50 * 366];

        return $days > $limits[$unit]
            ? ['day' => 'Daily reports cover up to 3 months. Pick a shorter range or a weekly view.', 'week' => 'Weekly reports cover up to 2 years.', 'month' => 'Monthly reports cover up to 5 years.', 'year' => 'Pick a shorter range.'][$unit]
            : null;
    }

    private function publicEntry(array $e): array
    {
        return [
            'key' => $e['key'],
            'kind' => $e['kind'],
            'direction' => $e['direction'],
            'title' => $e['title'],
            'party' => $e['party'],
            'category_label' => $e['category_label'],
            'amount' => $e['amount'],
            'order_number' => $e['order_number'],
            'payment_method' => $e['payment_method'] ?? null,
            'refund_method' => $e['refund_method'] ?? null,
            'created_at' => $e['at']->toIso8601String(),
        ];
    }

    private function refundCases(int $vendorId): Collection
    {
        return OrderRequest::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->with(['order:id,order_number,total_amount,vendor_id', 'user:id,name,surname'])
            ->get();
    }

    /** [agreed but not yet paid back, still being agreed], each formatted with what it would cost */
    private function openRefunds(Collection $cases): array
    {
        $refund = [OrderRequest::FULL_REFUND, OrderRequest::PARTIAL_REFUND];

        // Money the shop has agreed to give back but customers haven't confirmed yet
        $pending = $cases->filter(fn (OrderRequest $c) => $c->status === 'approved'
            && in_array($c->settled_resolution, $refund, true))
            ->map(fn (OrderRequest $c) => $this->formatCase($c, (float) $c->settled_amount))
            ->values();
        // Still being agreed: what the latest offer would cost
        $negotiating = $cases->filter(fn (OrderRequest $c) => $c->status === 'pending'
            && in_array($c->offer_resolution ?? $c->resolution_requested, $refund, true))
            ->map(fn (OrderRequest $c) => $this->formatCase($c, (float) ($c->offer_amount ?? $c->amount_requested ?? $c->order?->total_amount)))
            ->values();

        return [$pending, $negotiating];
    }

    private function formatCase(OrderRequest $c, float $amount): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type,
            'stage' => $c->stage(),
            'customer' => trim(($c->user?->name ?? '') . ' ' . ($c->user?->surname ?? '')) ?: 'Customer',
            'order_id' => $c->order?->id,
            'order_number' => $c->order?->order_number,
            'resolution' => $c->settled_resolution ?? $c->offer_resolution ?? $c->resolution_requested,
            'amount' => round($amount, 2),
            'since' => ($c->settled_at ?? $c->created_at)?->toIso8601String(),
        ];
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(VendorExpense::CATEGORIES))],
            'description' => 'required|string|min:2|max:255',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'spent_on' => 'required|date|before_or_equal:today',
            'payment_method' => ['nullable', Rule::in(array_keys(VendorExpense::PAYMENT_METHODS))],
            'payee' => 'nullable|string|max:120',
            'reference' => 'nullable|string|max:80',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'files' => 'nullable|array|max:' . VendorExpense::MAX_FILES,
            'files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'remove_files' => 'nullable|array',
            'remove_files.*' => 'integer',
        ], [
            'files.*.mimes' => 'Attach photos (JPG, PNG, WebP) or PDFs.',
            'files.*.max' => 'Each file can be up to 10 MB.',
            'files.max' => 'Attach up to ' . VendorExpense::MAX_FILES . ' files.',
            'spent_on.before_or_equal' => "The date can't be in the future.",
            'receipt.mimes' => 'The receipt must be a photo (JPG, PNG or WebP).',
            'receipt.max' => 'The receipt must be 5 MB or smaller.',
        ]);
    }

    private function storeReceipt(Request $request): array
    {
        $file = $request->file('receipt');
        if (! $file || ! $file->isValid()) {
            return [null, null];
        }
        $result = CloudinaryHelper::upload($file->getRealPath(), ['folder' => 'expense_receipts', 'resource_type' => 'image']);
        $path = is_array($result) ? ($result['public_id'] ?? null) : $result;
        if (! $path) {
            throw new \RuntimeException('Upload failed');
        }

        return [$path, 'image'];
    }

    /** GET …/expenses/{id}/files/{fileId}/link: a short-lived link to open a receipt or document */
    public function expenseFileLink(Request $request, int $id, int $fileId): JsonResponse
    {
        $file = VendorExpenseFile::where('owner_id', $this->resolveOwnerId($request))
            ->where('vendor_expense_id', $id)
            ->find($fileId);
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        try {
            $url = CloudinaryHelper::privateDownloadUrl($file->public_id, $file->format, $file->resource_type);
        } catch (\Throwable $e) {
            Log::error('Expense file link failed', ['error' => $e->getMessage(), 'file' => $file->id]);

            return response()->json(['success' => false, 'message' => "Couldn't open the file right now."], 502);
        }

        return response()->json(['success' => true, 'data' => ['url' => $url]]);
    }

    /** Uploads files[] privately (up to MAX_FILES per expense); a response when it can't */
    private function attachFiles(Request $request, VendorExpense $expense): ?JsonResponse
    {
        $files = array_filter((array) $request->file('files', []), fn ($f) => $f && $f->isValid());
        if (! $files) {
            return null;
        }
        if ($expense->files()->count() + count($files) > VendorExpense::MAX_FILES) {
            return response()->json(['success' => false, 'message' => 'An expense can have up to ' . VendorExpense::MAX_FILES . ' files. Remove one first.'], 422);
        }

        $actor = $this->actorName($request);
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            try {
                // PDFs and photos stay "image" so they open in the browser
                $upload = CloudinaryHelper::upload($file, [
                    'folder' => "expense-files/{$expense->owner_id}",
                    'type' => 'authenticated',
                    'resource_type' => 'image',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Expense file upload failed: ' . $e->getMessage());

                return response()->json(['success' => false, 'message' => "The expense is saved, but a file couldn't be uploaded. Open it and try attaching again."], 502);
            }
            $expense->files()->create([
                'owner_id' => $expense->owner_id,
                'public_id' => $upload['public_id'],
                'resource_type' => $upload['resource_type'] ?? 'image',
                'format' => $upload['format'] ?? $extension,
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 160),
                'size' => (int) $file->getSize(),
                'uploaded_by' => $actor,
            ]);
        }

        return null;
    }

    private function formatExpense(VendorExpense $x): array
    {
        return [
            'id' => $x->id,
            'category' => $x->category,
            'category_label' => VendorExpense::CATEGORIES[$x->category] ?? ucfirst($x->category),
            'description' => $x->description,
            'amount' => (float) $x->amount,
            'spent_on' => $x->spent_on->toDateString(),
            'payment_method' => $x->payment_method,
            'payment_label' => VendorExpense::PAYMENT_METHODS[$x->payment_method] ?? null,
            'payee' => $x->payee,
            'reference' => $x->reference,
            'notes' => $x->notes,
            'receipt_url' => $x->receiptUrl(),
            'files' => $x->relationLoaded('files') ? $x->files->map(fn (VendorExpenseFile $f) => [
                'id' => $f->id, 'name' => $f->file_name, 'size' => $f->size, 'image' => $f->isImage(), 'format' => $f->format,
            ])->values() : [],
            'stock' => $x->isStock(),
            'recorded_by' => $x->recorded_by,
            'created_at' => $x->created_at?->toIso8601String(),
        ];
    }

    private function actorName(Request $request): ?string
    {
        $u = $request->user();

        return trim(($u->name ?? '') . ' ' . ($u->surname ?? '')) ?: null;
    }
}
