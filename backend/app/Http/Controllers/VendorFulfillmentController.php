<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\VendorApplication;
use App\Models\VendorClosedDate;
use App\Services\CustomerRecords;
use App\Services\NotificationService;
use App\Services\ReturnCaseService;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The vendor portal's Orders & Fulfillment screens: the order list, one
 * order's full details, the delivery / preparation schedule and the
 * reservation calendar. Status changes still go through
 * VendorOrdersController::updateOrderStatus, so every existing rule applies.
 */
class VendorFulfillmentController extends Controller
{
    use ResolvesOwner;

    /** Display statuses, in workflow order, with the vendor-facing words. */
    public const STAGES = [
        'pending' => 'Pending',
        'to_processed' => 'Preparing',
        'to_ship' => 'Ready',
        'to_received' => 'Out for delivery',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
        'refunded' => 'Refunded',
    ];

    private const TABS = ['all', 'pending', 'to_processed', 'to_ship', 'to_received', 'completed', 'cancelled', 'returns'];

    /** What the shop can do next from each stage (labels match the buttons). */
    private const NEXT = [
        'pending' => [['to' => 'to_processed', 'label' => 'Confirm & start preparing']],
        'to_processed' => [['to' => 'to_ship', 'label' => 'Mark as ready']],
        'to_ship' => [['to' => 'to_received', 'label' => 'Send out for delivery']],
        // Out for delivery: the customer marks it received, not the shop
        'to_received' => [],
    ];

    /** GET /vendor/fulfillment/orders */
    public function orders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab' => 'nullable|in:' . implode(',', self::TABS),
            'search' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'sort' => 'nullable|in:newest,oldest,delivery_soon,total_high',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);
        $vendorId = $this->resolveOwnerId($request);

        $orders = $this->visibleOrders($vendorId)
            ->with(['user:id,name,surname', 'delivery', 'orderRequests', 'items.product:id,preparation_days,supplier_lead_time'])
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('reservation_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('reservation_date', '<=', $d))
            ->get()
            ->map(fn (Order $o) => $this->summary($o));

        // Counts ignore the tab and search so the tab badges stay put
        $counts = collect(self::TABS)->mapWithKeys(fn ($tab) => [$tab => $orders->filter(fn ($o) => $this->inTab($o, $tab))->count()]);
        $counts['needs_action'] = $orders->filter(fn ($o) => $o['needs_action'])->count();

        $search = mb_strtolower(trim($data['search'] ?? ''));
        $list = $orders
            ->filter(fn ($o) => $this->inTab($o, $data['tab'] ?? 'all'))
            ->filter(fn ($o) => $search === '' || str_contains(mb_strtolower($o['order_number'] . ' ' . $o['customer'] . ' ' . $o['phone'] . ' ' . $o['item_names']), $search));

        $list = match ($data['sort'] ?? 'newest') {
            'oldest' => $list->sortBy('created_at'),
            'delivery_soon' => $list->sortBy(fn ($o) => $o['delivery_date'] ?? '9999-12-31'),
            'total_high' => $list->sortByDesc('total'),
            default => $list->sortByDesc('created_at'),
        };

        $perPage = $data['per_page'] ?? 12;
        $page = $data['page'] ?? 1;
        $total = $list->count();

        return response()->json([
            'success' => true,
            'data' => $list->slice(($page - 1) * $perPage, $perPage)->values(),
            'counts' => $counts,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    /**
     * POST /vendor/fulfillment/orders/{id}/approve-area
     * The shop agrees to deliver an order outside its delivery areas; the
     * order then carries on as usual and the customer is told.
     */
    public function approveArea(Request $request, int $id): JsonResponse
    {
        $order = $this->visibleOrders($this->resolveOwnerId($request))->whereKey($id)->first();
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
        if (! $order->awaitingAreaApproval()) {
            return response()->json(['success' => false, 'message' => 'This order doesn\'t need approval.'], 422);
        }
        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only orders that are still Pending can be approved.'], 422);
        }

        $order->update(['area_approved_at' => now()]);
        app(NotificationService::class)->notify(
            [$order->user_id],
            'order',
            'Your order is approved for delivery',
            "{$order->store_name} will deliver order {$order->order_number} to your address, even though it's outside their usual delivery areas.",
            '/customer/orders',
        );

        return response()->json(['success' => true, 'message' => 'Approved. You can start preparing this order.']);
    }

    /** GET /vendor/fulfillment/orders/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $with = [
            'user:id,name,surname,email,contact_number',
            'delivery.logs' => fn ($q) => $q->orderBy('scanned_at'),
            'orderRequests',
            'items.product' => fn ($q) => $q->withoutGlobalScopes()->with(['images', 'models']),
        ];
        $order = $this->visibleOrders($vendorId)->whereKey($id)->with($with)->first();

        // A customer can share an e-wallet checkout they never paid for (in
        // chat, for example). It isn't an order the shop works on, but the
        // shop may still open it, read-only, to help the customer.
        $unpaidCheckout = null;
        if (! $order) {
            $order = Order::query()
                ->where('vendor_id', $vendorId)
                ->where('payment_method', 'ewallet')
                ->whereIn('payment_status', ['unpaid', 'failed'])
                ->whereKey($id)
                ->with($with)
                ->first();
            $unpaidCheckout = $order ? $this->unpaidCheckoutNote($order) : null;
        }

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $summary = $this->summary($order);
        if ($unpaidCheckout) {
            $summary = array_merge($summary, [
                'status' => 'unpaid_checkout',
                'status_label' => 'Payment not completed',
                'next' => [],
                'can_cancel' => false,
                'needs_action' => false,
                'blocked_reason' => null,
                'prepare_by' => null,
                'unpaid_checkout' => $unpaidCheckout,
            ]);
        }
        $application = VendorApplication::findApprovedForVendorUser($order->vendor);

        $items = $order->items->map(function ($item) {
            $custom = is_array($item->customizations) ? $item->customizations : (json_decode($item->customizations ?: '[]', true) ?: []);
            $model = $item->product?->models?->first();
            $modelUrl = $model?->model_path ? CloudinaryHelper::getUrl($model->model_path, 'raw') : ($model?->model_url ?: $item->model_3d_url);
            $image = $item->product_image ?: ($item->product?->images?->firstWhere('is_primary', true) ?? $item->product?->images?->first())?->image_url;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'image' => $image,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'color' => $item->color ?: null,
                'size' => $item->size ?: null,
                'notes' => $item->notes ?: null,
                'customizations' => $custom,
                'is_custom_bouquet' => ($custom['type'] ?? null) === 'custom_flower_bouquet',
                'model_3d_url' => $modelUrl,
                'model_type' => $model?->model_type ?? 'glb',
                'preparation_days' => (int) ($item->product?->preparation_days ?? 0),
            ];
        });

        $timeline = collect([['status' => 'placed', 'label' => 'Order placed', 'at' => $order->created_at?->toIso8601String()]]);
        if ($order->paid_at) {
            $timeline->push(['status' => 'paid', 'label' => 'Payment received', 'at' => $order->paid_at->toIso8601String()]);
        }
        foreach ($order->delivery?->logs ?? [] as $log) {
            $timeline->push([
                'status' => $log->status,
                'label' => self::STAGES[$log->status] ?? ucfirst(str_replace('_', ' ', $log->status)),
                'at' => $log->scanned_at?->toIso8601String(),
            ]);
        }
        if ($order->cancelled_at) {
            $timeline->push(['status' => 'cancelled', 'label' => 'Cancelled', 'at' => $order->cancelled_at->toIso8601String()]);
        }
        if ($unpaidCheckout && $order->payment_status === 'failed') {
            $timeline->push(['status' => 'cancelled', 'label' => 'Payment not completed', 'at' => $order->updated_at?->toIso8601String()]);
        }

        return response()->json([
            'success' => true,
            'data' => $summary + [
                'email' => $order->user?->email,
                'subtotal' => (float) $order->subtotal,
                'delivery_fee' => (float) $order->delivery_fee,
                'discount' => (float) $order->discount_amount,
                'notes' => $order->customer_notes ?: null,
                'loves' => $this->loves($order, $unpaidCheckout),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'items' => $items,
                'timeline' => $timeline->sortBy('at')->values(),
                'stages' => collect(['pending', 'to_processed', 'to_ship', 'to_received', 'completed'])
                    ->map(fn ($s) => ['key' => $s, 'label' => self::STAGES[$s]]),
                'fulfillment' => [
                    'method' => 'delivery',
                    'handled_by' => $this->handledBy($application),
                ],
            ],
        ]);
    }

    /**
     * The flowers the customer loves, for whoever makes the bouquet. Only what
     * they chose to share with shops. `state` tells the shop why there is
     * nothing to show: "private" (not shared) or "none" (shared, but empty).
     */
    private function loves(Order $order, ?array $unpaidCheckout): ?array
    {
        if ($unpaidCheckout || ! $order->user_id) {
            return null;
        }
        $shared = app(CustomerRecords::class)->shared((int) $order->user_id);
        if (! $shared) {
            return ['state' => 'private'];
        }
        $loves = collect($shared)->only(['flowers', 'colors', 'avoid', 'occasions', 'note'])->all();
        $hasTags = $loves['flowers'] || $loves['colors'] || $loves['avoid'] || $loves['occasions'];

        return ['state' => $hasTags || $loves['note'] ? 'shared' : 'none'] + $loves;
    }

    /** GET /vendor/fulfillment/schedule — upcoming work by delivery date, with when to start preparing. */
    public function schedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:60',
            'include_done' => 'nullable|boolean',
        ]);
        $vendorId = $this->resolveOwnerId($request);
        $today = Carbon::today(VendorApplication::RESERVATION_TIMEZONE);
        $until = $today->copy()->addDays(($data['days'] ?? 14) - 1);

        $orders = $this->visibleOrders($vendorId)
            ->with(['user:id,name,surname', 'delivery', 'orderRequests', 'items.product:id,preparation_days,supplier_lead_time'])
            ->whereNotNull('reservation_date')
            ->whereDate('reservation_date', '<=', $until->toDateString())
            ->get()
            ->map(fn (Order $o) => $this->summary($o));

        $active = fn ($o) => ! in_array($o['status'], ['completed', 'cancelled', 'returned', 'refunded'], true);
        $overdue = $orders->filter(fn ($o) => $active($o) && $o['delivery_date'] < $today->toDateString())->sortBy('delivery_date')->values();
        $upcoming = $orders->filter(fn ($o) => $o['delivery_date'] >= $today->toDateString()
            && ($active($o) || ! empty($data['include_done'])));

        $days = collect();
        for ($d = $today->copy(); $d->lte($until); $d->addDay()) {
            $key = $d->toDateString();
            $days->push([
                'date' => $key,
                'orders' => $upcoming->where('delivery_date', $key)->sortBy(fn ($o) => array_search($o['status'], array_keys(self::STAGES), true))->values(),
            ]);
        }

        $prepToday = $orders->filter(fn ($o) => $active($o) && in_array($o['status'], ['pending', 'to_processed'], true)
            && $o['prepare_by'] && $o['prepare_by'] <= $today->toDateString());

        $application = VendorApplication::findApprovedForVendorUser($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $today->toDateString(),
                'overdue' => $overdue,
                'days' => $days->values(),
                'stats' => [
                    'today' => $upcoming->where('delivery_date', $today->toDateString())->filter($active)->count(),
                    'prepare_now' => $prepToday->count(),
                    'ready' => $orders->where('status', 'to_ship')->count(),
                    'out' => $orders->where('status', 'to_received')->count(),
                    'overdue' => $overdue->count(),
                ],
                'fulfillment' => [
                    'method' => 'delivery',
                    'handled_by' => $this->handledBy($application),
                    'lead_time_days' => $application?->reservationLeadTimeDays() ?? 0,
                    'same_day' => (bool) ($application?->same_day_delivery ?? false),
                    'max_orders_per_day' => max(1, (int) ($application?->max_orders_per_day ?? 10)),
                ],
            ],
        ]);
    }

    /** GET /vendor/fulfillment/calendar?month=YYYY-MM — orders per day, capacity and closed days. */
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'regex:/^\d{4}-\d{2}$/']]);
        $vendorId = $this->resolveOwnerId($request);
        $start = Carbon::createFromFormat('Y-m-d', ($data['month'] ?? now()->format('Y-m')) . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $orders = $this->visibleOrders($vendorId)
            ->with(['delivery'])
            ->whereNotNull('reservation_date')
            ->whereBetween('reservation_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'status', 'reservation_date', 'payment_method', 'payment_status', 'total_amount']);

        $closed = VendorClosedDate::where('vendor_id', $vendorId)
            ->whereBetween('closed_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($c) => Carbon::parse($c->closed_date)->toDateString());

        $application = VendorApplication::findApprovedForVendorUser($request->user());
        $capacity = max(1, (int) ($application?->max_orders_per_day ?? 10));
        $openDays = collect($application?->operating_schedules ?? [])->flatMap(fn ($s) => $s['days'] ?? [])->unique()->values();

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $dayOrders = $orders->filter(fn ($o) => $o->reservation_date?->toDateString() === $key);
            $live = $dayOrders->reject(fn ($o) => in_array($o->status, ['cancelled', 'failed'], true));
            $c = $closed->get($key);
            $days[] = [
                'date' => $key,
                'total' => $live->count(),
                'open_orders' => $live->reject(fn ($o) => in_array($this->displayStatus($o), ['completed', 'returned', 'refunded'], true))->count(),
                'value' => round((float) $live->sum('total_amount'), 2),
                'closed' => $c ? ['id' => $c->id, 'reason' => $c->reason, 'type' => $c->type] : null,
                'weekly_closed' => $openDays->isNotEmpty() && ! $openDays->contains($d->format('l')),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $start->format('Y-m'),
                'capacity' => $capacity,
                'days' => $days,
                'upcoming_closed' => VendorClosedDate::forVendor($vendorId)->upcoming()->orderBy('closed_date')->limit(20)->get()
                    ->map(fn ($c) => ['id' => $c->id, 'date' => Carbon::parse($c->closed_date)->toDateString(), 'reason' => $c->reason, 'type' => $c->type]),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Orders the shop may see. Only e-wallet checkouts are held back until
     * PayMongo confirms payment (an abandoned checkout isn't a real order);
     * once paid they stay visible, even after a refund.
     */
    private function visibleOrders(int $vendorId)
    {
        return Order::query()
            ->where('vendor_id', $vendorId)
            ->where(fn ($q) => $q->where('payment_method', '!=', 'ewallet')->orWhereNull('payment_method')
                ->orWhereIn('payment_status', ['paid', 'refunded'])
                ->orWhereNotNull('paid_at'));
    }

    /** What the shop should know about a checkout the customer didn't pay for */
    private function unpaidCheckoutNote(Order $order): array
    {
        $what = match ($order->paymentFailure()['reason'] ?? null) {
            'cancelled' => 'The customer left the PayMongo page before paying.',
            'declined' => "The customer's bank or e-wallet declined the payment.",
            'not_started' => "PayMongo couldn't open the payment page for the customer.",
            'unconfirmed' => "PayMongo didn't confirm the customer's payment.",
            default => "The customer hasn't finished paying for this checkout yet.",
        };

        return [
            'title' => $order->payment_status === 'failed' ? 'The customer didn’t complete payment' : 'Waiting for the customer to pay',
            'reason' => $what,
            'meaning' => "This isn't an order yet. There's nothing to prepare, the delivery date isn't held for it, and no money was received. If the customer pays, it moves into your orders on its own.",
        ];
    }

    private function displayStatus(Order $order): string
    {
        if (in_array($order->status, ['cancelled', 'refunded', 'returned'], true)) {
            return $order->status;
        }
        if ($order->delivery?->status) {
            return $order->delivery->status;
        }

        return match ($order->status) {
            'processing' => 'to_processed',
            'delivered' => 'to_received',
            'failed' => 'cancelled',
            default => $order->status,
        };
    }

    private function summary(Order $order): array
    {
        $status = $this->displayStatus($order);
        $case = $order->orderRequests?->sortByDesc('id')->first();
        $caseSummary = $case ? app(ReturnCaseService::class)->summary($case) : null;
        $caseOpen = $case && in_array($case->status, OrderRequest::OPEN_STATUSES, true);
        $deliveryDate = $order->reservation_date?->toDateString();
        $prepDays = (int) $order->items->max(fn ($i) => (int) ($i->product?->preparation_days ?? 0));
        $prepareBy = $deliveryDate ? Carbon::parse($deliveryDate)->subDays($prepDays)->toDateString() : null;
        $onlineUnpaid = $order->payment_method === 'ewallet' && $order->payment_status !== 'paid';
        $first = $order->items->first();
        $customer = $order->delivery_contact_name
            ?: trim(($order->user?->name ?? '') . ' ' . ($order->user?->surname ?? '')) ?: 'Customer';

        // Outside the shop's delivery areas: approve delivering it (or cancel) first
        $awaitingArea = $status === 'pending' && $order->awaitingAreaApproval();
        $next = ($caseOpen || $onlineUnpaid || $awaitingArea) ? [] : (self::NEXT[$status] ?? []);
        $canCancel = $status === 'pending' && ! $caseOpen
            && ! ($order->payment_method !== 'cod' && $order->payment_status === 'paid');

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $status,
            'status_label' => self::STAGES[$status] ?? ucfirst($status),
            'customer' => $customer,
            'phone' => $order->delivery_contact_number ?: null,
            'address' => $order->delivery_address ?: null,
            'delivery_date' => $deliveryDate,
            'prepare_by' => $prepareBy,
            'preparation_days' => $prepDays,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'total' => (float) $order->total_amount,
            'items_count' => (int) $order->items->sum('quantity'),
            'item_names' => $order->items->pluck('product_name')->implode(', '),
            'first_item' => $first ? ['name' => $first->product_name, 'image' => $first->product_image] : null,
            'has_custom_bouquet' => $order->items->contains(function ($i) {
                $c = is_array($i->customizations) ? $i->customizations : (json_decode($i->customizations ?: '[]', true) ?: []);
                return ($c['type'] ?? null) === 'custom_flower_bouquet';
            }),
            'return_case' => $caseSummary,
            'case_open' => $caseOpen,
            // The photo of the order, needed before it goes out for delivery
            'photo_url' => $order->delivery?->photo_url,
            'next' => $next,
            'can_cancel' => $canCancel,
            // null: inside the areas. Otherwise whether the shop approved delivering it
            'outside_area' => $order->outside_area ? ['approved' => $order->area_approved_at !== null, 'approved_at' => $order->area_approved_at?->toIso8601String()] : null,
            'blocked_reason' => $caseOpen ? 'A return or refund is open on this order. Settle it first.'
                : ($onlineUnpaid ? 'Waiting for the online payment to be confirmed.' : null),
            'needs_action' => $awaitingArea
                || ! empty($next) && in_array($status, ['pending', 'to_processed', 'to_ship'], true)
                || ($caseSummary['next_actor'] ?? null) === 'vendor',
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    private function inTab(array $o, string $tab): bool
    {
        return match ($tab) {
            'all' => true,
            'returns' => (bool) $o['return_case'] || in_array($o['status'], ['returned', 'refunded'], true),
            'cancelled' => $o['status'] === 'cancelled',
            default => $o['status'] === $tab,
        };
    }

    private function handledBy(?VendorApplication $application): string
    {
        $who = strtolower((string) ($application?->delivery_handled_by ?? 'vendor'));

        return in_array($who, ['vendor', 'self', ''], true) ? 'Your shop delivers' : 'Delivered by ' . ucfirst(str_replace('_', ' ', $who));
    }
}
