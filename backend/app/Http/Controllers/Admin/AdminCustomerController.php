<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountEvent;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\ProductReport;
use App\Models\User;
use App\Services\AccountSuspensionService;
use App\Support\PaymentLedger;
use App\Support\VendorDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Admin view of customer accounts.
 *
 * Personal details are masked by default. Seeing them in full needs a
 * stated reason and is recorded in the customer's history, so access can
 * be limited per admin role later. Admins never see passwords, 2FA secrets
 * or tokens, and can't edit a customer's profile or orders here.
 */
class AdminCustomerController extends Controller
{
    public function __construct(private readonly AccountSuspensionService $accounts)
    {
    }

    /**
     * GET /api/admin/customers/summary
     */
    public function summary(): JsonResponse
    {
        $customers = User::query()->where('role', User::ROLE_CUSTOMER);
        $complaintCustomers = OrderRequest::query()->where('status', 'pending')->distinct()->pluck('user_id')
            ->merge(ProductReport::query()->where('status', 'pending')->distinct()->pluck('reporter_id'))
            ->unique();

        return response()->json([
            'success' => true,
            'data' => [
                'all' => (clone $customers)->count(),
                'active' => (clone $customers)->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'))->count(),
                'suspended' => (clone $customers)->where('is_suspended', true)->count(),
                'new' => (clone $customers)->where('created_at', '>=', now()->subDays(30))->count(),
                'with_complaints' => (clone $customers)->whereIn('id', $complaintCustomers)->count(),
                'open_complaints' => OrderRequest::query()->where('status', 'pending')->count()
                    + ProductReport::query()->where('status', 'pending')->count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/customers
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('role', User::ROLE_CUSTOMER);

        match ($request->input('tab')) {
            'active' => $query->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended')),
            'suspended' => $query->where('is_suspended', true),
            'new' => $query->where('created_at', '>=', now()->subDays(30)),
            'with_complaints' => $query->where(fn ($q) => $q
                ->whereIn('id', OrderRequest::query()->where('status', 'pending')->select('user_id'))
                ->orWhereIn('id', ProductReport::query()->where('status', 'pending')->select('reporter_id'))),
            default => null,
        };

        if ($request->filled('region')) {
            $query->where('region', $request->input('region'));
        }
        if ($request->input('verified') === 'yes') {
            $query->where('is_verified', true);
        } elseif ($request->input('verified') === 'no') {
            $query->where(fn ($q) => $q->where('is_verified', false)->orWhereNull('is_verified'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $digits = ltrim(preg_replace('/^#/', '', $search), '0');
            $query->where(function ($q) use ($like, $digits) {
                $q->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(surname) like ?', [$like])
                    ->orWhereRaw("lower(concat(name, ' ', surname)) like ?", [$like])
                    ->orWhereRaw('lower(username) like ?', [$like])
                    ->orWhereRaw('lower(email) like ?', [$like]);
                if ($digits !== '' && ctype_digit($digits)) {
                    $q->orWhere('id', (int) $digits);
                }
            });
        }

        $users = $query->get(['id', 'name', 'surname', 'username', 'email', 'contact_number', 'city', 'region', 'is_verified', 'is_suspended', 'profile_picture', 'created_at']);
        $rows = $this->summarize($users);

        $rows = match ($request->input('sort')) {
            'oldest' => $rows->sortBy('joined_at'),
            'spent' => $rows->sortByDesc('total_spent'),
            'orders' => $rows->sortByDesc('orders'),
            'active' => $rows->sortByDesc(fn ($r) => $r['last_active_at'] ?? ''),
            'name' => $rows->sortBy(fn ($r) => mb_strtolower($r['name'])),
            default => $rows->sortByDesc('joined_at'),
        };

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $page = max(1, (int) $request->input('page', 1));
        $total = $rows->count();

        return response()->json([
            'success' => true,
            'data' => $rows->values()->slice(($page - 1) * $perPage, $perPage)->values(),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
            'regions' => User::query()->where('role', User::ROLE_CUSTOMER)->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
        ]);
    }

    /**
     * GET /api/admin/customers/{id}
     */
    public function show(int $id): JsonResponse
    {
        $customer = $this->find($id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $row = $this->summarize(collect([$customer]))->first();
        $orders = Order::query()->where('user_id', $customer->id)->withCount('items')->latest()->get();
        $vendors = VendorDirectory::details($orders->pluck('vendor_id'));
        $finished = $orders->whereIn('status', ['completed', 'cancelled', 'failed', 'returned', 'refunded']);

        return response()->json([
            'success' => true,
            'data' => $row + [
                'profile' => [
                    'username' => $customer->username,
                    'gender' => $customer->gender,
                    'two_factor_enabled' => (bool) $customer->two_factor_enabled,
                    'has_pinned_location' => $customer->latitude !== null,
                ],
                'stats' => [
                    'completed' => $orders->where('status', 'completed')->count(),
                    'cancelled' => $orders->whereIn('status', ['cancelled', 'failed'])->count(),
                    'returned' => $orders->whereIn('status', ['returned', 'refunded'])->count(),
                    'cancellation_rate' => $finished->count() ? round($orders->whereIn('status', ['cancelled', 'failed'])->count() / $finished->count() * 100, 1) : null,
                    'average_order' => $row['paid_orders'] ? round($row['total_spent'] / $row['paid_orders'], 2) : 0,
                    'reviews' => DB::table('product_reviews')->where('user_id', $customer->id)->count(),
                    'favourite_store' => $this->favouriteStore($orders, $vendors),
                ],
                'recent_orders' => $orders->take(25)->map(fn (Order $o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'method_label' => PaymentLedger::methodLabel($o->payment_method),
                    'total_amount' => (float) $o->total_amount,
                    'items_count' => $o->items_count,
                    'vendor' => $vendors[$o->vendor_id] ?? VendorDirectory::unknown($o->vendor_id),
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->values(),
                'complaints' => $this->complaints($customer),
                'logins' => DB::table('login_audit_logs')->where('user_id', $customer->id)->orderByDesc('logged_in_at')->limit(8)
                    ->get(['logged_in_at', 'device_name', 'browser', 'platform', 'location_label', 'ip_address'])
                    ->map(fn ($l) => [
                        'at' => $l->logged_in_at ? CarbonImmutable::parse($l->logged_in_at)->toIso8601String() : null,
                        'device' => collect([$l->browser, $l->platform, $l->device_name])->filter()->unique()->implode(' · ') ?: 'Unknown device',
                        'location' => $l->location_label,
                        'ip' => $this->maskIp($l->ip_address),
                    ]),
                'history' => AccountEvent::query()->with('admin:id,name,surname')
                    ->where('user_id', $customer->id)->latest()->orderByDesc('id')->limit(30)->get()
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
     * POST /api/admin/customers/{id}/reveal  { reason }
     * Full contact details, recorded in the customer's history.
     */
    public function reveal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $customer = $this->find($id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        AccountEvent::create([
            'user_id' => $customer->id,
            'admin_id' => $request->user()->id,
            'action' => AccountEvent::CONTACT_VIEWED,
            'reason' => trim($data['reason']),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $customer->email,
                'contact_number' => $customer->contact_number,
                'address' => collect([$customer->address, $customer->city, $customer->region, $customer->postal_code])->filter()->implode(', ') ?: null,
                'date_of_birth' => $customer->date_of_birth ? CarbonImmutable::parse($customer->date_of_birth)->toDateString() : null,
            ],
        ]);
    }

    /**
     * POST /api/admin/customers/{id}/suspend  { reason }
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $customer = $this->find($id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        try {
            $this->accounts->suspend($customer, $request->user(), trim($data['reason']));
        } catch (LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Account suspended. They have been signed out.']);
    }

    /**
     * POST /api/admin/customers/{id}/reactivate  { note? }
     */
    public function reactivate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $customer = $this->find($id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        try {
            $this->accounts->reactivate($customer, $request->user(), isset($data['note']) ? trim($data['note']) : null);
        } catch (LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Account reactivated. They can sign in again.']);
    }

    /**
     * GET /api/admin/customers/complaints?status=pending
     * Every return/refund request and product report customers have filed.
     */
    public function complaintFeed(Request $request): JsonResponse
    {
        $status = in_array($request->input('status'), ['pending', 'resolved'], true) ? $request->input('status') : null;
        $requests = OrderRequest::query()->with(['order:id,order_number,vendor_id,total_amount', 'user:id,name,surname'])
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($status === 'resolved', fn ($q) => $q->where('status', '!=', 'pending'))
            ->latest()->limit(60)->get();
        $reports = ProductReport::query()->with(['product:id,product_name,owner_id', 'reporter:id,name,surname'])
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($status === 'resolved', fn ($q) => $q->where('status', '!=', 'pending'))
            ->latest()->limit(60)->get();

        $vendors = VendorDirectory::details($requests->pluck('order.vendor_id')->merge($reports->pluck('product.owner_id')));

        $items = $requests->map(fn ($r) => $this->formatRequest($r, $vendors) + [
            'customer' => ['id' => $r->user_id, 'name' => PaymentLedger::personName($r->user)],
        ])->merge($reports->map(fn ($r) => $this->formatReport($r, $vendors) + [
            'customer' => ['id' => $r->reporter_id, 'name' => PaymentLedger::personName($r->reporter)],
        ]))->sortByDesc('created_at')->values();

        return response()->json(['success' => true, 'data' => $items]);
    }

    // ── Pieces ───────────────────────────────────────────────────────────

    private function find(int $id): ?User
    {
        return User::query()->where('role', User::ROLE_CUSTOMER)->find($id);
    }

    private function summarize(Collection $users): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $ids = $users->pluck('id');
        $orders = Order::query()->whereIn('user_id', $ids)
            ->selectRaw("user_id,
                count(*) as orders,
                sum(case when payment_status in ('paid','refunded') then 1 else 0 end) as paid_orders,
                sum(case when payment_status = 'paid' then total_amount else 0 end) as spent,
                max(created_at) as last_order_at")
            ->groupBy('user_id')->get()->keyBy('user_id');
        $openRequests = OrderRequest::query()->whereIn('user_id', $ids)->where('status', 'pending')
            ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $openReports = ProductReport::query()->whereIn('reporter_id', $ids)->where('status', 'pending')
            ->selectRaw('reporter_id, count(*) as total')->groupBy('reporter_id')->pluck('total', 'reporter_id');
        $lastLogin = DB::table('login_audit_logs')->whereIn('user_id', $ids)
            ->selectRaw('user_id, max(logged_in_at) as at')->groupBy('user_id')->pluck('at', 'user_id');

        return $users->map(function (User $u) use ($orders, $openRequests, $openReports, $lastLogin) {
            $stats = $orders[$u->id] ?? null;
            $lastActive = collect([$lastLogin[$u->id] ?? null, $stats->last_order_at ?? null])->filter()->max();

            return [
                'id' => $u->id,
                'name' => PaymentLedger::personName($u) ?? $u->username,
                'avatar_url' => $u->profile_picture ? $u->avatar_url : null,
                'email_masked' => $this->maskEmail($u->email),
                'phone_masked' => $this->maskPhone($u->contact_number),
                'location' => collect([$u->city, $u->region])->filter()->implode(', ') ?: null,
                'verified' => (bool) $u->is_verified,
                'status' => $u->is_suspended ? 'suspended' : 'active',
                'orders' => (int) ($stats->orders ?? 0),
                'paid_orders' => (int) ($stats->paid_orders ?? 0),
                'total_spent' => round((float) ($stats->spent ?? 0), 2),
                'last_order_at' => isset($stats->last_order_at) ? CarbonImmutable::parse($stats->last_order_at)->toIso8601String() : null,
                'last_active_at' => $lastActive ? CarbonImmutable::parse($lastActive)->toIso8601String() : null,
                'open_complaints' => (int) ($openRequests[$u->id] ?? 0) + (int) ($openReports[$u->id] ?? 0),
                'joined_at' => $u->created_at?->toIso8601String(),
            ];
        });
    }

    /** Return/refund requests and product reports this customer filed */
    private function complaints(User $customer): array
    {
        $requests = OrderRequest::query()->with('order:id,order_number,vendor_id,total_amount')->where('user_id', $customer->id)->latest()->get();
        $reports = ProductReport::query()->with('product:id,product_name,owner_id')->where('reporter_id', $customer->id)->latest()->get();
        $vendors = VendorDirectory::details($requests->pluck('order.vendor_id')->merge($reports->pluck('product.owner_id')));

        return $requests->map(fn ($r) => $this->formatRequest($r, $vendors))
            ->merge($reports->map(fn ($r) => $this->formatReport($r, $vendors)))
            ->sortByDesc('created_at')->values()->all();
    }

    private function formatRequest(OrderRequest $r, array $vendors): array
    {
        return [
            'key' => 'request-' . $r->id,
            'kind' => $r->type, // return | refund
            'status' => $r->status === 'pending' ? 'open' : $r->status,
            'reason' => $r->reason,
            'response' => $r->admin_notes,
            'subject' => $r->order?->order_number,
            'order_id' => $r->order_id,
            'product_id' => null,
            'amount' => $r->order ? (float) $r->order->total_amount : null,
            'vendor' => $vendors[$r->order?->vendor_id] ?? null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function formatReport(ProductReport $r, array $vendors): array
    {
        return [
            'key' => 'report-' . $r->id,
            'kind' => 'product_report',
            'status' => $r->status === 'pending' ? 'open' : ($r->status === 'approved' ? 'upheld' : 'dismissed'),
            'reason' => trim($r->reason . ($r->description ? ': ' . $r->description : '')),
            'response' => null,
            'subject' => $r->product?->product_name ?? 'Removed product',
            'order_id' => null,
            'product_id' => $r->product_id,
            'amount' => null,
            'vendor' => $vendors[$r->product?->owner_id] ?? null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function favouriteStore(Collection $orders, array $vendors): ?array
    {
        $top = $orders->countBy('vendor_id')->sortDesc()->keys()->first();
        return $top && isset($vendors[$top]) ? $vendors[$top] + ['orders' => $orders->where('vendor_id', $top)->count()] : null;
    }

    // ── Masking ──────────────────────────────────────────────────────────

    /** "mariebernadet@gmail.com" → "ma•••••@gmail.com" */
    private function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return $email ? '•••••' : null;
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2) . '•••••@' . $domain;
    }

    /** "09326798353" → "•••• ••• 8353" */
    private function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        return $digits === '' ? null : '•••• ••• ' . substr($digits, -4);
    }

    /** "203.177.12.45" → "203.177.•.•" */
    private function maskIp(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return "{$parts[0]}.{$parts[1]}.•.•";
        }

        return implode(':', array_slice(explode(':', $ip), 0, 2)) . ':•••';
    }
}
