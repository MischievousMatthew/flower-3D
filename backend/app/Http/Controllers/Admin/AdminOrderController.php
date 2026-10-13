<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAdminNote;
use App\Models\OrderRequest;
use App\Models\User;
use App\Models\VendorTransaction;
use App\Support\PaymentMask;
use App\Support\PlatformSettings;
use App\Support\VendorDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin oversight of every vendor's orders.
 *
 * Deliberately read-only for the order itself: admins can look at orders,
 * payments, deliveries and disputes and keep private notes, but they cannot
 * change an order's status, payment or delivery here. Those transitions stay
 * with the vendor's flows, which carry the payment, stock and finance
 * safeguards (Order::markAs*, VendorFinanceService, Delivery FSM).
 */
class AdminOrderController extends Controller
{
    /** Status tabs and the order statuses each one covers */
    private const BUCKETS = [
        'pending' => ['pending'],
        'processing' => ['processing', 'out_for_delivery'],
        'completed' => ['completed'],
        'cancelled' => ['cancelled', 'failed'],
        'refunded' => ['refunded', 'returned'],
    ];

    private const STALE_PROCESSING_DAYS = 7;

    /**
     * GET /api/admin/orders/summary
     */
    public function summary(): JsonResponse
    {
        $byStatus = Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $buckets = collect(self::BUCKETS)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0));

        $paid = Order::query()->where('payment_status', 'paid');

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (int) $byStatus->sum(),
                'buckets' => $buckets,
                'open_disputes' => OrderRequest::query()->whereIn('status', OrderRequest::OPEN_STATUSES)->count(),
                'issues' => $this->withIssues(Order::query())->count(),
                'issue_breakdown' => collect($this->issueRules())->map(fn ($rule, $key) => [
                    'key' => $key,
                    'label' => $rule['label'],
                    'severity' => $rule['severity'],
                    'count' => $rule['query'](Order::query())->count(),
                ])->values(),
                'paid_volume' => round((float) (clone $paid)->sum('total_amount'), 2),
                'paid_orders' => (clone $paid)->count(),
                'today' => Order::query()->whereDate('created_at', today())->count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->withCount([
                'items',
                'orderRequests as open_requests_count' => fn ($q) => $q->whereIn('status', OrderRequest::OPEN_STATUSES),
                'orderRequests as requests_count',
            ]);

        $bucket = $request->input('bucket');
        if (isset(self::BUCKETS[$bucket])) {
            $query->whereIn('status', self::BUCKETS[$bucket]);
        } elseif ($bucket === 'disputes') {
            $query->whereHas('orderRequests');
        } elseif ($bucket === 'issues') {
            $this->withIssues($query);
        }

        if ($issue = $request->input('issue')) {
            $rule = $this->issueRules()[$issue] ?? null;
            if ($rule) {
                $rule['query']($query);
            }
        }
        foreach (['payment_status', 'payment_method'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', (int) $request->input('vendor_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $this->applySearch($query, $search);
        }

        $sort = match ($request->input('sort')) {
            'oldest' => ['created_at', 'asc'],
            'amount_high' => ['total_amount', 'desc'],
            'amount_low' => ['total_amount', 'asc'],
            default => ['created_at', 'desc'],
        };
        $page = $query->orderBy(...$sort)->orderByDesc('id')
            ->paginate(min(max((int) $request->input('per_page', 15), 5), 100));

        $orders = $page->getCollection();
        $customers = User::query()->whereIn('id', $orders->pluck('user_id')->unique())
            ->get(['id', 'name', 'surname', 'email'])->keyBy('id');
        $vendors = VendorDirectory::details($orders->pluck('vendor_id'));

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'total_amount' => (float) $order->total_amount,
                'items_count' => $order->items_count,
                'customer' => $this->formatCustomer($customers[$order->user_id] ?? null, $order),
                'vendor' => ($vendors[$order->vendor_id] ?? VendorDirectory::unknown($order->vendor_id)) + ['store_name_at_order' => $order->store_name],
                'open_requests' => $order->open_requests_count,
                'requests' => $order->requests_count,
                'issues' => $this->issuesFor($order),
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::query()
            ->with([
                'items',
                'user:id,name,surname,email,contact_number',
                'delivery.logs.scanner:id,name',
                'orderRequests' => fn ($q) => $q->latest(),
                'orderRequests.user:id,name,surname',
                'adminNotes.admin:id,name,surname',
            ])
            ->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $vendor = VendorDirectory::details([$order->vendor_id])[$order->vendor_id] ?? VendorDirectory::unknown($order->vendor_id);
        $transactions = VendorTransaction::query()->where('order_id', $order->id)->orderBy('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'amounts' => [
                    'subtotal' => (float) $order->subtotal,
                    'delivery_fee' => (float) $order->delivery_fee,
                    'tax' => (float) $order->tax_amount,
                    'discount' => (float) $order->discount_amount,
                    'total' => (float) $order->total_amount,
                ],
                'payment' => [
                    'paid_at' => $order->paid_at?->toIso8601String(),
                    'paymongo_payment_intent_id' => PaymentMask::reference($order->paymongo_payment_intent_id),
                    'paymongo_source_id' => PaymentMask::reference($order->paymongo_source_id),
                ],
                'customer' => $this->formatCustomer($order->user, $order) + [
                    'contact_number' => $order->user?->contact_number,
                ],
                'vendor' => $vendor + ['store_name_at_order' => $order->store_name, 'store_address' => $order->store_address],
                'delivery' => [
                    'type' => $order->delivery_type,
                    'address' => $order->delivery_address,
                    'contact_name' => $order->delivery_contact_name,
                    'contact_number' => $order->delivery_contact_number,
                    'reservation_date' => $order->reservation_date?->toDateString(),
                    'status' => $order->delivery?->status,
                    'barcode' => $order->delivery?->barcode,
                ],
                'customer_notes' => $order->customer_notes,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'image' => $item->product_image,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'color' => $item->color,
                    'size' => $item->size,
                    'notes' => $item->notes,
                    'custom' => $this->customSummary($item->customizations),
                ])->values(),
                'requests' => $order->orderRequests->map(fn (OrderRequest $r) => app(\App\Services\ReturnCaseService::class)->summary($r) + [
                    'vendor_response' => $r->closed_reason ?? $r->admin_notes,
                    'customer' => trim(($r->user?->name ?? '') . ' ' . ($r->user?->surname ?? '')) ?: null,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'updated_at' => $r->updated_at?->toIso8601String(),
                ])->values(),
                'transactions' => $transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'category' => $t->category,
                    'amount' => (float) $t->amount,
                    'status' => $t->status,
                    'description' => $t->description,
                    'created_at' => $t->created_at?->toIso8601String(),
                ])->values(),
                'timeline' => $this->timeline($order, $transactions),
                'issues' => $this->issuesFor($order),
                'notes' => $order->adminNotes->map(fn ($note) => $this->formatNote($note))->values(),
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/orders/{id}/notes
     * A private investigation note. It does not touch the order.
     */
    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);

        $order = Order::query()->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $note = OrderAdminNote::create([
            'order_id' => $order->id,
            'admin_id' => $request->user()->id,
            'body' => trim($data['body']),
        ])->load('admin:id,name,surname');

        return response()->json(['success' => true, 'data' => $this->formatNote($note), 'message' => 'Note added.'], 201);
    }

    // ── Issues ───────────────────────────────────────────────────────────

    /**
     * Orders that look wrong and are worth a look. Each rule has a query
     * (for filtering and counting) and a check on a loaded order.
     */
    private function issueRules(): array
    {
        $stalePendingDays = (int) PlatformSettings::get('stale_pending_days');
        $stalePending = now()->subDays($stalePendingDays);
        $staleProcessing = now()->subDays(self::STALE_PROCESSING_DAYS);

        return [
            'paid_not_refunded' => [
                'label' => 'Paid, then cancelled or returned, with no refund recorded',
                'severity' => 'high',
                'query' => fn (Builder $q) => $q->whereIn('status', ['cancelled', 'failed', 'returned'])->where('payment_status', 'paid'),
                'check' => fn (Order $o) => in_array($o->status, ['cancelled', 'failed', 'returned'], true) && $o->payment_status === 'paid',
            ],
            'completed_unpaid' => [
                'label' => 'Completed without a confirmed online payment',
                'severity' => 'high',
                'query' => fn (Builder $q) => $q->where('status', 'completed')->where('payment_status', '!=', 'paid')->where('payment_method', '!=', 'cod'),
                'check' => fn (Order $o) => $o->status === 'completed' && $o->payment_status !== 'paid' && $o->payment_method !== 'cod',
            ],
            'open_dispute' => [
                'label' => 'Return or refund request waiting on the vendor',
                'severity' => 'medium',
                'query' => fn (Builder $q) => $q->whereHas('orderRequests', fn ($r) => $r->whereIn('status', OrderRequest::OPEN_STATUSES)),
                'check' => fn (Order $o) => ($o->open_requests_count ?? $o->orderRequests->whereIn('status', OrderRequest::OPEN_STATUSES)->count()) > 0,
            ],
            'stock_not_restored' => [
                'label' => 'Cancelled, but the stock taken for it was never put back',
                'severity' => 'medium',
                'query' => fn (Builder $q) => $q->whereIn('status', ['cancelled', 'failed'])->whereNotNull('stock_deducted_at')->whereNull('stock_restored_at'),
                'check' => fn (Order $o) => in_array($o->status, ['cancelled', 'failed'], true) && $o->stock_deducted_at && !$o->stock_restored_at,
            ],
            'stale_pending' => [
                'label' => "Pending for more than {$stalePendingDays} days",
                'severity' => 'low',
                'query' => fn (Builder $q) => $q->where('status', 'pending')->where('created_at', '<', $stalePending),
                'check' => fn (Order $o) => $o->status === 'pending' && $o->created_at?->lt($stalePending),
            ],
            'stale_in_progress' => [
                'label' => 'In progress with no update for ' . self::STALE_PROCESSING_DAYS . ' days',
                'severity' => 'low',
                'query' => fn (Builder $q) => $q->whereIn('status', ['processing', 'out_for_delivery'])->where('updated_at', '<', $staleProcessing),
                'check' => fn (Order $o) => in_array($o->status, ['processing', 'out_for_delivery'], true) && $o->updated_at?->lt($staleProcessing),
            ],
        ];
    }

    private function withIssues(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            foreach ($this->issueRules() as $rule) {
                $q->orWhere(fn (Builder $inner) => $rule['query']($inner));
            }
        });
    }

    private function issuesFor(Order $order): array
    {
        return collect($this->issueRules())
            ->filter(fn ($rule) => $rule['check']($order))
            ->map(fn ($rule, $key) => ['key' => $key, 'label' => $rule['label'], 'severity' => $rule['severity']])
            ->values()
            ->all();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function applySearch(Builder $query, string $search): void
    {
        $like = '%' . mb_strtolower($search) . '%';
        $digits = ltrim(preg_replace('/^#/', '', $search), '0');

        $customerIds = User::query()
            ->whereRaw('lower(name) like ?', [$like])
            ->orWhereRaw('lower(surname) like ?', [$like])
            ->orWhereRaw("lower(concat(name, ' ', surname)) like ?", [$like])
            ->orWhereRaw('lower(email) like ?', [$like])
            ->pluck('id');

        $statusMatches = collect(['pending', 'processing', 'out_for_delivery', 'completed', 'cancelled', 'failed', 'returned', 'refunded'])
            ->filter(fn ($status) => str_contains(str_replace('_', ' ', $status), str_replace('_', ' ', mb_strtolower($search))));

        $query->where(function (Builder $q) use ($like, $digits, $customerIds, $statusMatches, $search) {
            $q->whereRaw('lower(order_number) like ?', [$like])
                ->orWhereRaw('lower(store_name) like ?', [$like])
                ->orWhereRaw('lower(delivery_contact_name) like ?', [$like])
                ->orWhereIn('user_id', $customerIds)
                ->orWhereIn('vendor_id', VendorDirectory::idsMatching($search));

            if ($digits !== '' && ctype_digit($digits)) {
                $q->orWhere('id', (int) $digits);
            }
            if ($statusMatches->isNotEmpty()) {
                $q->orWhereIn('status', $statusMatches->values());
            }
        });
    }

    private function formatCustomer(?User $user, Order $order): array
    {
        return [
            'id' => $user?->id,
            'name' => $user ? trim($user->name . ' ' . ($user->surname ?? '')) : $order->delivery_contact_name,
            'email' => $user?->email,
        ];
    }

    private function formatNote(OrderAdminNote $note): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'author' => $note->admin ? trim($note->admin->name . ' ' . ($note->admin->surname ?? '')) : 'Former admin',
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }

    /** The bits of a custom bouquet worth seeing at a glance */
    private function customSummary($customizations): ?array
    {
        if (is_string($customizations)) {
            $customizations = json_decode($customizations, true);
        }
        if (!is_array($customizations) || !$customizations) {
            return null;
        }

        $summary = array_filter([
            'wrapper' => data_get($customizations, 'wrapper.name'),
            'ribbon' => data_get($customizations, 'ribbon.name'),
            'fillers' => collect(data_get($customizations, 'fillers', []))->pluck('name')->filter()->values()->all() ?: null,
            'card' => data_get($customizations, 'card.name') ? [
                'name' => data_get($customizations, 'card.name'),
                'size' => data_get($customizations, 'card.size'),
                'message' => data_get($customizations, 'card.message'),
            ] : null,
        ]);

        return $summary ?: null;
    }

    /** Everything that happened to the order, oldest first */
    private function timeline(Order $order, $transactions): array
    {
        $events = collect([
            ['at' => $order->created_at, 'kind' => 'created', 'title' => 'Order placed', 'detail' => ucfirst(str_replace('_', ' ', $order->payment_method)) . ' · ₱' . number_format((float) $order->total_amount, 2)],
            ['at' => $order->paid_at, 'kind' => 'paid', 'title' => 'Payment confirmed', 'detail' => null],
            ['at' => $order->stock_deducted_at, 'kind' => 'stock', 'title' => 'Stock taken for the order', 'detail' => null],
            ['at' => $order->delivered_at, 'kind' => 'delivered', 'title' => 'Marked as delivered', 'detail' => null],
            ['at' => $order->cancelled_at, 'kind' => 'cancelled', 'title' => 'Order cancelled', 'detail' => null],
            ['at' => $order->stock_restored_at, 'kind' => 'stock', 'title' => 'Stock put back', 'detail' => null],
        ]);

        foreach ($order->delivery?->logs ?? [] as $log) {
            $events->push([
                'at' => $log->scanned_at ?? $log->created_at,
                'kind' => 'delivery',
                'title' => match ($log->status) {
                    'pending' => 'Delivery created',
                    'to_processed' => 'Vendor started preparing it',
                    'to_ship' => 'Handed over for delivery',
                    'to_received' => 'Out for delivery',
                    'completed' => 'Delivery completed',
                    'returned' => 'Delivery returned',
                    'refunded' => 'Delivery refunded',
                    default => 'Delivery: ' . ucfirst(str_replace('_', ' ', $log->status)),
                },
                'detail' => collect([
                    $log->scanner ? 'by ' . $log->scanner->name : null,
                    $log->notes,
                ])->filter()->implode(' · ') ?: null,
            ]);
        }

        foreach ($order->orderRequests as $r) {
            $events->push(['at' => $r->created_at, 'kind' => 'dispute', 'title' => ucfirst($r->type) . ' requested', 'detail' => $r->reason]);
            $describe = fn ($resolution, $amount) => app(\App\Services\ReturnCaseService::class)->describe($resolution, $amount);
            if ($r->settled_at) {
                $events->push(['at' => $r->settled_at, 'kind' => 'resolved', 'title' => 'Customer and shop agreed on ' . $describe($r->settled_resolution, $r->settled_amount), 'detail' => null]);
            }
            if ($r->completed_at) {
                $events->push(['at' => $r->completed_at, 'kind' => 'resolved', 'title' => OrderRequest::isRefund($r->settled_resolution) ? 'Customer confirmed the refund' : 'Customer confirmed the replacement', 'detail' => $r->refund_reference ? 'Reference ' . $r->refund_reference : null]);
            }
            if (in_array($r->status, ['rejected', 'withdrawn'], true)) {
                $events->push(['at' => $r->last_activity_at ?? $r->updated_at, 'kind' => 'rejected', 'title' => $r->status === 'rejected' ? 'Declined by the shop' : 'Withdrawn by the customer', 'detail' => $r->closed_reason ?? $r->admin_notes]);
            }
        }

        foreach ($transactions as $t) {
            $events->push([
                'at' => $t->created_at,
                'kind' => 'finance',
                'title' => 'Vendor ' . ($t->type === 'debit' ? 'debit' : 'credit') . ' ₱' . number_format((float) $t->amount, 2),
                'detail' => $t->description,
            ]);
        }

        return $events
            ->filter(fn ($e) => $e['at'])
            ->sortBy(fn ($e) => $e['at']->getTimestamp())
            ->map(fn ($e) => [...$e, 'at' => $e['at']->toIso8601String()])
            ->values()
            ->all();
    }
}
