<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\OrderRequest;
use App\Models\VendorExpense;
use App\Services\VendorBooksService;
use App\Traits\ResolvesOwner;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                'by_category' => $totals['expenses_by_category'],
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

        return response()->json(['success' => true, 'message' => 'Expense recorded.', 'data' => $this->formatExpense($expense)], 201);
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

        return response()->json(['success' => true, 'message' => 'Expense saved.', 'data' => $this->formatExpense($expense->fresh())]);
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
        $expense->delete();

        return response()->json(['success' => true, 'message' => 'Expense deleted.']);
    }

    /** GET /vendor/books/profit-loss */
    public function profitLoss(Request $request): JsonResponse
    {
        $request->validate($this->rangeRules());
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);
        [$pFrom, $pTo] = $this->books->previousPeriod($from, $to);

        // Six months up to the end of the period, for the trend chart
        $trendFrom = $to->copy()->startOfMonth()->subMonths(5);
        $trendEntries = $this->books->entries($vendorId, $trendFrom, $to);

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'previous_range' => $this->rangeInfo($pFrom, $pTo),
                'current' => $this->books->totals($this->books->entries($vendorId, $from, $to)),
                'previous' => $this->books->totals($this->books->entries($vendorId, $pFrom, $pTo)),
                'trend' => $this->books->buckets($trendEntries, $trendFrom, $to, 'month'),
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

        $cases = OrderRequest::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->with(['order:id,order_number,total_amount,vendor_id', 'user:id,name,surname'])
            ->get();

        // Money the shop has agreed to give back but customers haven't confirmed yet
        $pending = $cases->filter(fn (OrderRequest $c) => $c->status === 'approved'
            && in_array($c->settled_resolution, [OrderRequest::FULL_REFUND, OrderRequest::PARTIAL_REFUND], true))
            ->map(fn (OrderRequest $c) => $this->formatCase($c, (float) $c->settled_amount))
            ->values();
        // Still being agreed: what the latest offer would cost
        $negotiating = $cases->filter(fn (OrderRequest $c) => $c->status === 'pending'
            && in_array($c->offer_resolution ?? $c->resolution_requested, [OrderRequest::FULL_REFUND, OrderRequest::PARTIAL_REFUND], true))
            ->map(fn (OrderRequest $c) => $this->formatCase($c, (float) ($c->offer_amount ?? $c->amount_requested ?? $c->order?->total_amount)))
            ->values();
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
        ], [
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
