<?php

namespace App\Http\Controllers;

use App\Models\CodCollection;
use App\Models\Order;
use App\Services\VendorPaymentsService;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finance → Payments: PayMongo and cash-on-delivery payments with their
 * status and fees, reconciliation against orders and the books, and the
 * cash-on-delivery the shop has received and settled.
 *
 * Served at /vendor/books/payments* for the shop owner and /finance/payments*
 * for ERP Finance staff (payments module: view to look, edit to record cash).
 */
class PaymentsController extends Controller
{
    use ResolvesOwner;

    public function __construct(private VendorPaymentsService $payments) {}

    /** GET …/payments */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method' => 'nullable|in:paymongo,cod',
            'status' => ['nullable', Rule::in(VendorPaymentsService::STATUSES)],
            'cod_stage' => ['nullable', Rule::in(VendorPaymentsService::COD_STAGES)],
            'issues' => 'nullable|boolean',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ] + $this->rangeRules());
        [$from, $to] = $this->range($request);

        $rows = $this->payments->rows($this->resolveOwnerId($request), $from, $to);

        $term = mb_strtolower(trim($data['search'] ?? ''));
        $list = $rows
            ->when($data['method'] ?? null, fn ($c, $m) => $c->where('method', $m))
            ->when($data['status'] ?? null, fn ($c, $s) => $c->where('status', $s))
            ->when($data['cod_stage'] ?? null, fn ($c, $s) => $c->filter(fn ($r) => ($r['cod']['stage'] ?? null) === $s))
            ->when(! empty($data['issues']), fn ($c) => $c->filter(fn ($r) => $r['issues']))
            ->filter(fn ($r) => $term === '' || str_contains(mb_strtolower($r['order_number'] . ' ' . $r['customer'] . ' ' . ($r['reference'] ?? '')), $term))
            ->values();

        $perPage = 15;
        $page = $data['page'] ?? 1;

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'cod_ready' => $this->payments->codReady(),
                'summary' => $this->payments->summary($rows),
                'reconciliation' => $this->payments->reconciliation($rows),
                'rows' => $this->present($list->slice(($page - 1) * $perPage, $perPage)),
                'meta' => ['page' => $page, 'total' => $list->count(), 'last_page' => max(1, (int) ceil($list->count() / $perPage))],
            ],
        ]);
    }

    /** POST …/payments/cod/{orderId}/collect: the shop received this order's cash */
    public function collect(Request $request, int $orderId): JsonResponse
    {
        $this->ensureCodReady();
        $data = $request->validate([
            'amount' => 'required|numeric|min:0|max:10000000',
            'collected_by' => 'nullable|string|max:120',
            'collected_on' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ]);
        $vendorId = $this->resolveOwnerId($request);
        $order = $this->codOrder($vendorId, $orderId);

        $existing = CodCollection::where('order_id', $order->id)->first();
        if ($existing?->settled_on) {
            throw ValidationException::withMessages(['amount' => 'This cash is already settled. Undo the settlement first to change it.']);
        }

        CodCollection::updateOrCreate(['order_id' => $order->id], [
            'owner_id' => $vendorId,
            'expected_amount' => round((float) $order->total_amount, 2),
            'collected_amount' => round((float) $data['amount'], 2),
            'collected_by' => $data['collected_by'] ?? null,
            'collected_on' => $data['collected_on'] ?? now()->toDateString(),
            'recorded_by' => $this->actorName($request),
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->rowResponse($vendorId, $order, 'Cash received is recorded.');
    }

    /** POST …/payments/cod/settle: these collections were deposited or counted into the books */
    public function settle(Request $request): JsonResponse
    {
        $this->ensureCodReady();
        $data = $request->validate([
            'order_ids' => 'required|array|min:1|max:200',
            'order_ids.*' => 'integer',
            'settled_on' => 'nullable|date|before_or_equal:today',
            'reference' => 'nullable|string|max:80',
            'notes' => 'nullable|string|max:500',
        ]);
        $vendorId = $this->resolveOwnerId($request);

        $collections = CodCollection::where('owner_id', $vendorId)
            ->whereIn('order_id', $data['order_ids'])
            ->whereNull('settled_on')
            ->get();
        if ($collections->isEmpty()) {
            throw ValidationException::withMessages(['order_ids' => 'Record the cash as received before settling it.']);
        }

        $actor = $this->actorName($request);
        foreach ($collections as $c) {
            $c->update([
                'settled_on' => $data['settled_on'] ?? now()->toDateString(),
                'settlement_reference' => $data['reference'] ?? null,
                'settled_by' => $actor,
                'notes' => $data['notes'] ?? $c->notes,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $collections->count() === 1 ? 'Settled.' : "{$collections->count()} collections settled.",
            'data' => ['settled' => $collections->count(), 'amount' => round((float) $collections->sum('collected_amount'), 2)],
        ]);
    }

    /** DELETE …/payments/cod/{orderId}: undo the settlement, or the collection if not settled */
    public function undo(Request $request, int $orderId): JsonResponse
    {
        $this->ensureCodReady();
        $vendorId = $this->resolveOwnerId($request);
        $order = $this->codOrder($vendorId, $orderId, allowVoid: true);
        $c = CodCollection::where('owner_id', $vendorId)->where('order_id', $order->id)->firstOrFail();

        if ($c->settled_on) {
            $c->update(['settled_on' => null, 'settlement_reference' => null, 'settled_by' => null]);
            $message = 'Settlement undone. The cash is back to received.';
        } else {
            $c->delete();
            $message = 'Cash received is removed.';
        }

        return $this->rowResponse($vendorId, $order, $message);
    }

    private function codOrder(int $vendorId, int $orderId, bool $allowVoid = false): Order
    {
        $order = Order::where('vendor_id', $vendorId)->whereKey($orderId)->firstOrFail();
        if (! VendorPaymentsService::isCod($order->payment_method)) {
            throw ValidationException::withMessages(['amount' => 'Only cash-on-delivery orders have cash to record.']);
        }
        if (! $allowVoid && in_array($order->status, ['cancelled', 'failed', 'payment_failed'], true)) {
            throw ValidationException::withMessages(['amount' => 'This order was cancelled, so there is no cash to collect.']);
        }

        return $order;
    }

    private function ensureCodReady(): void
    {
        abort_unless($this->payments->codReady(), 503, 'Cash-on-delivery records are still being set up. Please try again in a few minutes.');
    }

    private function rowResponse(int $vendorId, Order $order, string $message): JsonResponse
    {
        $row = $this->payments->rows($vendorId, $order->created_at->copy()->startOfDay(), $order->created_at->copy()->endOfDay())
            ->firstWhere('id', $order->id);

        return response()->json(['success' => true, 'message' => $message, 'data' => $this->present(collect([$row]))->first()]);
    }

    private function present(Collection $rows): Collection
    {
        return $rows->map(fn ($r) => array_merge($r, [
            'placed_at' => $r['placed_at']?->toIso8601String(),
            'paid_at' => $r['paid_at']?->toIso8601String(),
        ]))->values();
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

    private function actorName(Request $request): ?string
    {
        $u = $request->user();

        return trim(($u->name ?? '') . ' ' . ($u->surname ?? '')) ?: null;
    }
}
