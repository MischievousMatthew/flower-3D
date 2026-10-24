<?php

namespace App\Http\Controllers;

use App\Models\OrderRequest;
use App\Services\RefundManagementService;
use App\Services\ReturnCaseService;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Finance → Refunds: refund cases with their decisions and status, the order
 * and payment each one belongs to, recording the refund payout, and
 * reconciliation against the books.
 *
 * Served at /vendor/books/refund-cases* for the shop owner and /finance/refunds*
 * for ERP Finance staff (refunds module: view to look, edit to record payouts).
 */
class RefundsController extends Controller
{
    use ResolvesOwner;

    public function __construct(private RefundManagementService $refunds, private ReturnCaseService $cases) {}

    /** GET …/refunds */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(RefundManagementService::STATUSES)],
            'issues' => 'nullable|boolean',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ] + $this->rangeRules());
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);

        $rows = $this->refunds->rows($vendorId, $this->refunds->cases($vendorId, $from, $to), $from);
        $unmatched = $this->refunds->unmatchedBooks($vendorId, $from, $to);

        $term = mb_strtolower(trim($data['search'] ?? ''));
        $list = $rows
            ->when($data['status'] ?? null, fn ($c, $s) => $c->where('status', $s))
            ->when(! empty($data['issues']), fn ($c) => $c->filter(fn ($r) => $r['issues']))
            ->filter(fn ($r) => $term === '' || str_contains(mb_strtolower($r['order']['order_number'] . ' ' . $r['customer'] . ' ' . ($r['refund_reference'] ?? '')), $term))
            ->values();
        $perPage = 15;
        $page = $data['page'] ?? 1;
        $withIssues = $rows->filter(fn ($r) => $r['issues']);
        $checked = $rows->whereIn('status', ['approved', 'processing', 'completed'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('M j, Y') . ' – ' . $to->format('M j, Y')],
                'summary' => $this->refunds->summary($rows, $vendorId, $from, $to),
                'reconciliation' => [
                    'checked' => $checked,
                    'matched' => max(0, $checked - $withIssues->whereIn('status', ['approved', 'processing', 'completed'])->count()),
                    'issue_count' => $withIssues->count() + count($unmatched),
                    'unmatched_books' => $unmatched,
                ],
                'rows' => $list->slice(($page - 1) * $perPage, $perPage)->values(),
                'meta' => ['page' => $page, 'total' => $list->count(), 'last_page' => max(1, (int) ceil($list->count() / $perPage))],
                'refund_methods' => collect(ReturnCaseService::REFUND_METHODS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            ],
        ]);
    }

    /** GET …/refunds/{id}: the case with its decisions, for the detail panel */
    public function show(Request $request, int $id): JsonResponse
    {
        $case = $this->find($request, $id);

        return response()->json(['success' => true, 'data' => $this->refunds->detail($case) + [
            'finance_status' => RefundManagementService::status($case),
            'amount' => RefundManagementService::amount($case),
        ]]);
    }

    /** POST …/refunds/{id}/sent: the shop paid the agreed refund back */
    public function sent(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(ReturnCaseService::REFUND_METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $case = $this->find($request, $id);

        try {
            $this->cases->refundSent($case, $this->cases->vendorActor($request->user()), $data['method'], $data['reference'] ?? null, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => "Refund recorded as sent. It reaches the books once the customer confirms they received it."]);
    }

    private function find(Request $request, int $id): OrderRequest
    {
        $vendorId = $this->resolveOwnerId($request);

        return OrderRequest::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->with('order')
            ->findOrFail($id);
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
}
