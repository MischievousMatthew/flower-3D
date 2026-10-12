<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\AccountEvent;
use App\Models\VendorApplication;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionCheckout;
use App\Services\AccountSuspensionService;
use App\Subscriptions\SubscriptionPlans;
use App\Support\PaymentLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Approved vendors: their accounts, performance, products, orders and
 * subscription. Applications (approve/reject/resubmission) stay with
 * VendorApplicationController.
 */
class AdminVendorController extends Controller
{
    private const FINISHED = ['completed', 'cancelled', 'failed', 'returned', 'refunded'];

    public function __construct(private readonly AccountSuspensionService $accounts)
    {
    }

    /**
     * GET /api/admin/vendors/summary
     */
    public function summary(): JsonResponse
    {
        $applications = VendorApplication::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $vendors = User::query()->where('role', User::ROLE_VENDOR);

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => (int) ($applications['pending'] ?? 0),
                'rejected' => (int) ($applications['rejected'] ?? 0),
                'active' => (clone $vendors)->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'))->count(),
                'suspended' => (clone $vendors)->where('is_suspended', true)->count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/vendors?status=active|suspended
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('role', User::ROLE_VENDOR);

        if ($request->input('status') === 'suspended') {
            $query->where('is_suspended', true);
        } elseif ($request->input('status') === 'active') {
            $query->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $storeEmails = VendorApplication::query()->whereRaw('lower(store_name) like ?', [$like])->pluck('email');
            $query->where(fn ($q) => $q
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(email) like ?', [$like])
                ->orWhereIn('email', $storeEmails));
        }

        $users = $query->orderByDesc('created_at')->get(['id', 'name', 'surname', 'email', 'contact_number', 'is_suspended', 'ban_count', 'created_at']);
        $rows = $this->summarize($users);

        $rows = match ($request->input('sort')) {
            'sales' => $rows->sortByDesc('sales_30d'),
            'orders' => $rows->sortByDesc('orders_30d'),
            'rating' => $rows->sortByDesc(fn ($r) => $r['rating'] ?? -1),
            'name' => $rows->sortBy(fn ($r) => mb_strtolower($r['store_name'])),
            default => $rows,
        };

        return response()->json(['success' => true, 'data' => $rows->values()]);
    }

    /**
     * GET /api/admin/vendors/{id}
     */
    public function show(int $id): JsonResponse
    {
        $vendor = User::query()->where('role', User::ROLE_VENDOR)->find($id);
        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        $application = $this->applicationFor($vendor);
        $summary = $this->summarize(collect([$vendor]))->first();

        return response()->json([
            'success' => true,
            'data' => [
                'vendor' => $summary,
                'application_id' => $application?->id,
                'performance' => $this->performance($vendor),
                'products' => $this->products($vendor),
                'orders' => $this->orders($vendor),
                'subscription' => $this->subscription($vendor),
                'history' => AccountEvent::query()->with('admin:id,name,surname')
                    ->where('user_id', $vendor->id)->latest()->orderByDesc('id')->get()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'action' => $e->action,
                        'reason' => $e->reason,
                        'by' => $e->admin ? PaymentLedger::personName($e->admin) : 'Automatic',
                        'at' => $e->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    /**
     * POST /api/admin/vendors/{id}/suspend
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $vendor = User::query()->where('role', User::ROLE_VENDOR)->find($id);
        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        try {
            $this->accounts->suspend($vendor, $request->user(), trim($data['reason']));
        } catch (LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Vendor suspended. They and their staff have been signed out.']);
    }

    /**
     * POST /api/admin/vendors/{id}/reactivate
     */
    public function reactivate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $vendor = User::query()->where('role', User::ROLE_VENDOR)->find($id);
        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        try {
            $this->accounts->reactivate($vendor, $request->user(), isset($data['note']) ? trim($data['note']) : null);
        } catch (LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Vendor reactivated. Their store is visible again.']);
    }

    // ── Pieces ───────────────────────────────────────────────────────────

    /** One row per vendor for the list (and the header of the detail view) */
    private function summarize(Collection $users): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $ids = $users->pluck('id');
        $since = now()->subDays(30);
        $applications = VendorApplication::query()->whereIn('email', $users->pluck('email'))->orderByDesc('id')->get()->unique('email')->keyBy('email');

        $orderStats = Order::query()->whereIn('vendor_id', $ids)
            ->selectRaw("vendor_id,
                count(*) as orders,
                sum(case when created_at >= ? then 1 else 0 end) as orders_30d,
                sum(case when payment_status in ('paid','refunded') then total_amount else 0 end) as sales,
                sum(case when payment_status in ('paid','refunded') and paid_at >= ? then total_amount else 0 end) as sales_30d,
                sum(case when status = 'pending' then 1 else 0 end) as pending,
                max(created_at) as last_order_at", [$since, $since])
            ->groupBy('vendor_id')->get()->keyBy('vendor_id');
        $products = Product::query()->whereIn('owner_id', $ids)
            ->selectRaw("owner_id, count(*) as total, sum(case when status = 'active' then 1 else 0 end) as active")
            ->groupBy('owner_id')->get()->keyBy('owner_id');
        $ratings = DB::table('product_reviews')->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->whereIn('products.owner_id', $ids)->whereNull('product_reviews.hidden_at')
            ->selectRaw('products.owner_id, avg(product_reviews.rating) as rating, count(*) as reviews')
            ->groupBy('products.owner_id')->get()->keyBy('owner_id');
        $disputes = OrderRequest::query()->join('orders', 'orders.id', '=', 'order_requests.order_id')
            ->whereIn('orders.vendor_id', $ids)->where('order_requests.status', 'pending')
            ->selectRaw('orders.vendor_id, count(*) as total')->groupBy('orders.vendor_id')->pluck('total', 'vendor_id');
        $subscriptions = VendorSubscription::query()->whereIn('vendor_id', $ids)->get()->keyBy('vendor_id');

        return $users->map(function (User $user) use ($applications, $orderStats, $products, $ratings, $disputes, $subscriptions) {
            $app = $applications[$user->email] ?? null;
            $stats = $orderStats[$user->id] ?? null;
            $rating = $ratings[$user->id] ?? null;

            return [
                'id' => $user->id,
                'application_id' => $app?->id,
                'store_name' => $app->store_name ?? $user->name,
                'logo_url' => $app?->store_logo_url,
                'owner_name' => $app->owner_name ?? PaymentLedger::personName($user),
                'email' => $user->email,
                'contact_number' => $app->contact_number ?? $user->contact_number,
                'business_type' => $app?->business_type,
                'address' => $app?->store_address,
                'joined_at' => ($app?->reviewed_at ?? $user->created_at)?->toIso8601String(),
                'status' => $user->is_suspended ? 'suspended' : 'active',
                'strikes' => (int) $user->ban_count,
                'products_total' => (int) ($products[$user->id]->total ?? 0),
                'products_active' => (int) ($products[$user->id]->active ?? 0),
                'orders' => (int) ($stats->orders ?? 0),
                'orders_30d' => (int) ($stats->orders_30d ?? 0),
                'pending_orders' => (int) ($stats->pending ?? 0),
                'sales' => round((float) ($stats->sales ?? 0), 2),
                'sales_30d' => round((float) ($stats->sales_30d ?? 0), 2),
                'last_order_at' => isset($stats->last_order_at) ? CarbonImmutable::parse($stats->last_order_at)->toIso8601String() : null,
                'rating' => $rating ? round((float) $rating->rating, 1) : null,
                'reviews' => (int) ($rating->reviews ?? 0),
                'open_disputes' => (int) ($disputes[$user->id] ?? 0),
                'subscription' => $this->planBadge($subscriptions[$user->id] ?? null),
            ];
        });
    }

    private function performance(User $vendor): array
    {
        $orders = Order::query()->where('vendor_id', $vendor->id)
            ->get(['id', 'status', 'payment_status', 'total_amount', 'paid_at', 'created_at', 'delivered_at']);
        $finished = $orders->whereIn('status', self::FINISHED);
        $paid = $orders->whereIn('payment_status', ['paid', 'refunded']);
        $completed = $orders->where('status', 'completed');
        $rate = fn (int $part) => $finished->count() ? round($part / $finished->count() * 100, 1) : null;

        $fulfilmentHours = $completed
            ->filter(fn ($o) => $o->delivered_at && $o->created_at)
            ->map(fn ($o) => $o->created_at->diffInMinutes($o->delivered_at) / 60);

        $months = collect(range(5, 0))->map(function ($back) use ($orders, $paid) {
            $start = now()->startOfMonth()->subMonthsNoOverflow($back);
            $end = $start->copy()->endOfMonth();
            return [
                'label' => $start->format('M'),
                'sales' => round((float) $paid->filter(fn ($o) => $o->paid_at && $o->paid_at->between($start, $end))->sum('total_amount'), 2),
                'orders' => $orders->filter(fn ($o) => $o->created_at->between($start, $end))->count(),
            ];
        })->values();

        $staleSince = now()->subDays(3);

        return [
            'sales' => round((float) $paid->sum('total_amount'), 2),
            'paid_orders' => $paid->count(),
            'average_order_value' => $paid->count() ? round($paid->sum('total_amount') / $paid->count(), 2) : 0,
            'completion_rate' => $rate($completed->count()),
            'cancellation_rate' => $rate($finished->whereIn('status', ['cancelled', 'failed'])->count()),
            'return_rate' => $rate($finished->whereIn('status', ['returned', 'refunded'])->count()),
            'finished_orders' => $finished->count(),
            'avg_fulfilment_hours' => $fulfilmentHours->count() ? round($fulfilmentHours->avg(), 1) : null,
            'stale_pending' => $orders->where('status', 'pending')->filter(fn ($o) => $o->created_at->lt($staleSince))->count(),
            'months' => $months,
        ];
    }

    private function products(User $vendor): array
    {
        $products = Product::query()->where('owner_id', $vendor->id)
            ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('display_order')])
            ->orderByDesc('created_at')->limit(60)->get();
        $sold = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $products->pluck('id'))
            ->whereIn('orders.payment_status', ['paid', 'refunded'])
            ->selectRaw('order_items.product_id, sum(order_items.quantity) as qty')
            ->groupBy('order_items.product_id')->pluck('qty', 'product_id');
        $ratings = DB::table('product_reviews')->whereIn('product_id', $products->pluck('id'))->whereNull('hidden_at')
            ->selectRaw('product_id, avg(rating) as rating, count(*) as reviews')->groupBy('product_id')->get()->keyBy('product_id');

        return $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->product_name,
            'image' => $p->images->first()?->image_url,
            'category' => $p->category,
            'price' => (float) ($p->has_discount && $p->discount_price ? $p->discount_price : $p->selling_price),
            'stock' => (int) $p->quantity_in_stock,
            'low_stock' => $p->min_stock_level !== null && $p->quantity_in_stock <= $p->min_stock_level,
            'status' => $p->status,
            'selling_type' => $p->selling_type,
            'sold' => (int) ($sold[$p->id] ?? 0),
            'rating' => isset($ratings[$p->id]) ? round((float) $ratings[$p->id]->rating, 1) : null,
            'reviews' => (int) ($ratings[$p->id]->reviews ?? 0),
        ])->all();
    }

    private function orders(User $vendor): array
    {
        $counts = Order::query()->where('vendor_id', $vendor->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $recent = Order::query()->where('vendor_id', $vendor->id)->withCount('items')->latest()->limit(15)
            ->get(['id', 'order_number', 'user_id', 'status', 'payment_status', 'payment_method', 'total_amount', 'created_at']);
        $customers = User::query()->whereIn('id', $recent->pluck('user_id'))->get(['id', 'name', 'surname'])->keyBy('id');

        return [
            'counts' => $counts,
            'recent' => $recent->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer' => PaymentLedger::personName($customers[$o->user_id] ?? null),
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'method_label' => PaymentLedger::methodLabel($o->payment_method),
                'total_amount' => (float) $o->total_amount,
                'items_count' => $o->items_count,
                'created_at' => $o->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function subscription(User $vendor): array
    {
        $sub = VendorSubscription::query()->where('vendor_id', $vendor->id)->first();
        $payments = VendorSubscriptionCheckout::query()->where('vendor_id', $vendor->id)->latest()->limit(12)->get();

        return [
            'current' => $sub ? $this->planBadge($sub) + [
                'started_at' => ($sub->subscription_started_at ?? $sub->trial_started_at)?->toIso8601String(),
                'ends_at' => ($sub->status === SubscriptionStatus::Trialing ? $sub->trial_ends_at : $sub->subscription_ends_at)?->toIso8601String(),
                'paid_amount' => $sub->paid_amount !== null ? (float) $sub->paid_amount : null,
                'resource_limits' => SubscriptionPlans::exists($sub->plan_key) ? SubscriptionPlans::get($sub->plan_key)['resource_limits'] : null,
            ] : null,
            'lifetime_paid' => round((float) VendorSubscriptionCheckout::query()->where('vendor_id', $vendor->id)->where('status', 'paid')->sum('amount'), 2),
            'payments' => $payments->map(fn ($c) => [
                'id' => $c->id,
                'reference' => $c->reference_number,
                'plan_name' => SubscriptionPlans::exists($c->plan_key) ? SubscriptionPlans::get($c->plan_key)['name'] : ucfirst($c->plan_key),
                'amount' => (float) $c->amount,
                'status' => $c->status,
                'created_at' => $c->created_at?->toIso8601String(),
                'paid_at' => $c->paid_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /** Plan name and an effective status (a run-out trial or period reads as expired) */
    private function planBadge(?VendorSubscription $sub): ?array
    {
        if (!$sub) {
            return null;
        }

        $now = now();
        $status = $sub->status->value;
        if ($sub->status === SubscriptionStatus::Trialing && !$sub->isTrialActive($now)) {
            $status = 'expired';
        } elseif ($sub->status === SubscriptionStatus::Active && $sub->subscription_ends_at && $now->gte($sub->subscription_ends_at)) {
            $status = 'expired';
        }

        return [
            'plan_key' => $sub->plan_key,
            'plan_name' => SubscriptionPlans::exists($sub->plan_key) ? SubscriptionPlans::get($sub->plan_key)['name'] : ucfirst($sub->plan_key),
            'status' => $status,
        ];
    }

    private function applicationFor(User $vendor): ?VendorApplication
    {
        return VendorApplication::query()->where('email', $vendor->email)->orderByDesc('id')->first();
    }
}
