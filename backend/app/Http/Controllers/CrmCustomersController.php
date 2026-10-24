<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\CustomerDate;
use App\Models\CustomerNote;
use App\Models\Employee;
use App\Models\FavoriteProduct;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorCustomerProfile;
use App\Services\CustomerRecords;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The shop's side of customer management (/crm/customers…): who its
 * customers are, how to reach them, what they bought, what they like and
 * their special days (shared by the customer or recorded by the shop), and
 * internal notes.
 */
class CrmCustomersController extends Controller
{
    use ResolvesOwner;

    private const NOT_A_SALE = ['cancelled', 'failed', 'refunded'];
    private const UPCOMING_DAYS = 30;

    public function __construct(private CustomerRecords $records) {}

    /** GET /crm/customers?search&filter=all|repeat|upcoming|notes&page */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:100',
            'filter' => 'nullable|in:all,repeat,upcoming,notes',
            'page' => 'nullable|integer|min:1',
        ]);
        $vendorId = $this->resolveOwnerId($request);
        $ids = $this->records->customerIds($vendorId);

        $users = User::whereIn('id', $ids)->get(['id', 'name', 'surname', 'email', 'contact_number', 'profile_picture']);
        $sales = $this->sales($vendorId)->whereIn('user_id', $ids)
            ->selectRaw('user_id, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as spent, MAX(paid_at) as last_order')
            ->groupBy('user_id')->get()->keyBy('user_id');
        $noteCounts = CustomerNote::where('vendor_id', $vendorId)->select('customer_id', DB::raw('COUNT(*) as n'))->groupBy('customer_id')->pluck('n', 'customer_id');
        $nextDates = $this->upcomingByCustomer($vendorId, $ids);

        $rows = $users->map(function (User $u) use ($sales, $noteCounts, $nextDates) {
            $s = $sales->get($u->id);

            return [
                'id' => $u->id,
                'name' => $this->nameOf($u),
                'email' => $u->email,
                'phone' => $u->contact_number,
                'avatar' => $this->photoOf($u),
                'orders' => (int) ($s->orders ?? 0),
                'spent' => round((float) ($s->spent ?? 0), 2),
                'last_order_at' => $s?->last_order ? Carbon::parse($s->last_order)->toIso8601String() : null,
                'notes' => (int) ($noteCounts[$u->id] ?? 0),
                'next_date' => $nextDates->get($u->id),
            ];
        });

        $search = mb_strtolower(trim((string) ($data['search'] ?? '')));
        $filter = $data['filter'] ?? 'all';
        $filtered = $rows
            ->filter(fn ($r) => $search === '' || str_contains(mb_strtolower("{$r['name']} {$r['email']} {$r['phone']}"), $search))
            ->filter(fn ($r) => match ($filter) {
                'repeat' => $r['orders'] > 1,
                'upcoming' => $r['next_date'] && $r['next_date']['days_away'] <= self::UPCOMING_DAYS,
                'notes' => $r['notes'] > 0,
                default => true,
            })
            ->sortBy([['spent', 'desc'], ['name', 'asc']])
            ->values();

        $perPage = 20;
        $page = (int) ($data['page'] ?? 1);

        return response()->json(['success' => true, 'data' => [
            'rows' => $filtered->forPage($page, $perPage)->values(),
            'meta' => ['total' => $filtered->count(), 'page' => $page, 'last_page' => max(1, (int) ceil($filtered->count() / $perPage))],
            'counts' => [
                'all' => $rows->count(),
                'repeat' => $rows->where('orders', '>', 1)->count(),
                'upcoming' => $rows->filter(fn ($r) => $r['next_date'] && $r['next_date']['days_away'] <= self::UPCOMING_DAYS)->count(),
                'notes' => $rows->where('notes', '>', 0)->count(),
            ],
            'upcoming' => $rows->filter(fn ($r) => $r['next_date'] && $r['next_date']['days_away'] <= self::UPCOMING_DAYS)
                ->sortBy(fn ($r) => $r['next_date']['days_away'])->take(6)
                ->map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'avatar' => $r['avatar']] + $r['next_date'])->values(),
        ]]);
    }

    /** GET /crm/customers/{id}/profile */
    public function show(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = $this->customerOf($vendorId, $id);

        return response()->json(['success' => true, 'data' => $this->profile($vendorId, $customer)]);
    }

    /** PUT /crm/customers/{id}/preferences — the shop's own record of what they like */
    public function updatePreferences(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = $this->customerOf($vendorId, $id);
        $data = $request->validate([
            'flowers' => $this->records->tagRules(), 'flowers.*' => 'string|max:60',
            'colors' => $this->records->tagRules(), 'colors.*' => 'string|max:60',
            'avoid' => $this->records->tagRules(), 'avoid.*' => 'string|max:60',
            'occasions' => $this->records->tagRules(), 'occasions.*' => 'string|max:60',
            'favorite_product_ids' => 'nullable|array|max:' . CustomerRecords::MAX_TAGS,
            'favorite_product_ids.*' => 'integer',
        ]);

        // Only this shop's products can be its favorites
        $productIds = collect($data['favorite_product_ids'] ?? [])->unique()->values();
        $productIds = $productIds->isEmpty() ? [] : $this->records->products($vendorId)->whereIn('id', $productIds)->pluck('id')->map(fn ($x) => (int) $x)->values()->all();

        VendorCustomerProfile::updateOrCreate(['vendor_id' => $vendorId, 'customer_id' => $customer->id], [
            'flowers' => $this->records->tags($data['flowers'] ?? []),
            'colors' => $this->records->tags($data['colors'] ?? []),
            'avoid' => $this->records->tags($data['avoid'] ?? []),
            'occasions' => $this->records->tags($data['occasions'] ?? []),
            'favorite_product_ids' => $productIds,
            'updated_by' => $this->authorName($request),
        ]);

        return response()->json(['success' => true, 'message' => 'Preferences saved.', 'data' => $this->profile($vendorId, $customer)]);
    }

    /** POST /crm/customers/{id}/dates — a day the customer told the shop about */
    public function storeDate(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = $this->customerOf($vendorId, $id);
        $data = $request->validate($this->records->dateRules());
        if (! $this->records->validDay((int) $data['month'], (int) $data['day'])) {
            throw ValidationException::withMessages(['day' => 'That day doesn\'t exist in that month.']);
        }
        if (CustomerDate::where('user_id', $customer->id)->where('vendor_id', $vendorId)->count() >= 20) {
            throw ValidationException::withMessages(['label' => 'Up to 20 dates per customer.']);
        }

        CustomerDate::create($data + ['user_id' => $customer->id, 'vendor_id' => $vendorId, 'added_by' => $this->authorName($request)]);

        return response()->json(['success' => true, 'message' => 'Date saved.', 'data' => $this->profile($vendorId, $customer)], 201);
    }

    /** DELETE /crm/customers/{id}/dates/{dateId} — only dates the shop recorded */
    public function destroyDate(Request $request, int $id, int $dateId): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = $this->customerOf($vendorId, $id);
        CustomerDate::where('user_id', $customer->id)->where('vendor_id', $vendorId)->findOrFail($dateId)->delete();

        return response()->json(['success' => true, 'message' => 'Date removed.', 'data' => $this->profile($vendorId, $customer)]);
    }

    /** POST /crm/customers/{id}/notes {body} */
    public function storeNote(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = $this->customerOf($vendorId, $id);
        $data = $request->validate(['body' => 'required|string|max:2000']);
        $user = $request->user();

        CustomerNote::create([
            'vendor_id' => $vendorId,
            'customer_id' => $customer->id,
            'body' => trim($data['body']),
            'author_type' => $user instanceof Employee ? 'employee' : 'vendor',
            'author_id' => $user->id,
            'author_name' => $this->authorName($request),
        ]);

        return response()->json(['success' => true, 'message' => 'Note added.', 'data' => $this->profile($vendorId, $customer)], 201);
    }

    /** PATCH /crm/customers/{id}/notes/{noteId} {pinned} */
    public function pinNote(Request $request, int $id, int $noteId): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = $this->customerOf($vendorId, $id);
        $data = $request->validate(['pinned' => 'required|boolean']);
        CustomerNote::where('vendor_id', $vendorId)->where('customer_id', $customer->id)->findOrFail($noteId)->update(['pinned' => $data['pinned']]);

        return response()->json(['success' => true, 'data' => $this->profile($vendorId, $customer)]);
    }

    /** DELETE /crm/customers/{id}/notes/{noteId} */
    public function destroyNote(Request $request, int $id, int $noteId): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = $this->customerOf($vendorId, $id);
        CustomerNote::where('vendor_id', $vendorId)->where('customer_id', $customer->id)->findOrFail($noteId)->delete();

        return response()->json(['success' => true, 'message' => 'Note deleted.', 'data' => $this->profile($vendorId, $customer)]);
    }

    private function profile(int $vendorId, User $customer): array
    {
        $orders = Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $customer->id)
            ->orderByDesc('created_at')->limit(50)
            ->get(['id', 'order_number', 'status', 'payment_status', 'payment_method', 'total_amount', 'delivery_address', 'delivery_contact_name', 'delivery_contact_number', 'paid_at', 'created_at']);
        $sold = $orders->filter(fn ($o) => $o->paid_at && ! in_array($o->status, self::NOT_A_SALE, true));

        $items = $sold->isEmpty() ? collect() : DB::table('order_items')->whereIn('order_id', $sold->pluck('id'))
            ->select('product_id', 'product_name', DB::raw('SUM(quantity) as qty'), DB::raw('COUNT(DISTINCT order_id) as times'))
            ->groupBy('product_id', 'product_name')->orderByDesc('qty')->limit(5)->get();

        $shared = $this->records->shared($customer->id);
        $latest = $orders->first(fn ($o) => $o->delivery_address);

        return [
            'id' => $customer->id,
            'name' => $this->nameOf($customer),
            'avatar' => $this->photoOf($customer),
            'member_since' => $customer->created_at?->toDateString(),
            'contact' => [
                'email' => $customer->email,
                'phone' => $customer->contact_number ?: $latest?->delivery_contact_number,
                'address' => collect([$customer->address, $customer->city, $customer->region])->filter()->join(', ') ?: null,
                'last_delivery' => $latest ? ['address' => $latest->delivery_address, 'name' => $latest->delivery_contact_name, 'phone' => $latest->delivery_contact_number] : null,
            ],
            'stats' => [
                'orders' => $sold->count(),
                'spent' => round((float) $sold->sum('total_amount'), 2),
                'average' => $sold->count() ? round((float) $sold->avg('total_amount'), 2) : 0,
                'first_order_at' => $sold->min('paid_at')?->toIso8601String(),
                'last_order_at' => $sold->max('paid_at')?->toIso8601String(),
            ],
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'payment_method' => $o->payment_method,
                'total' => round((float) $o->total_amount, 2),
                'created_at' => $o->created_at?->toIso8601String(),
            ])->values(),
            'top_products' => $items->map(fn ($i) => ['product_id' => $i->product_id, 'name' => $i->product_name, 'qty' => (int) $i->qty, 'times' => (int) $i->times])->values(),
            // Hearted products from this shop, when the customer shares
            'hearted' => $shared ? $this->hearted($vendorId, $customer->id) : [],
            'shared' => $shared,
            'shop' => $this->records->shopRecord($vendorId, $customer->id),
            'notes' => CustomerNote::where('vendor_id', $vendorId)->where('customer_id', $customer->id)
                ->orderByDesc('pinned')->orderByDesc('created_at')->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'body' => $n->body,
                    'pinned' => $n->pinned,
                    'author' => $n->author_name,
                    'at' => $n->created_at?->toIso8601String(),
                ])->values(),
        ];
    }

    private function hearted(int $vendorId, int $customerId): array
    {
        $ids = $this->records->products($vendorId)->pluck('id');

        return FavoriteProduct::where('user_id', $customerId)->whereIn('product_id', $ids)->with('product')->latest()->get()
            ->filter(fn ($f) => $f->product)
            ->map(fn ($f) => ['id' => $f->product_id, 'name' => $f->product->product_name])->values()->all();
    }

    /** The next special day per customer: their shared ones and the shop's own */
    private function upcomingByCustomer(int $vendorId, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }
        $sharing = DB::table('customer_preferences')->whereIn('user_id', $ids)->where('share_with_shops', true)->pluck('user_id');

        return CustomerDate::query()
            ->whereIn('user_id', $ids)
            ->where(fn ($q) => $q->where('vendor_id', $vendorId)->orWhere(fn ($q) => $q->whereNull('vendor_id')->whereIn('user_id', $sharing)))
            ->get()
            ->map(fn ($d) => ['user_id' => $d->user_id] + $this->records->dateRow($d))
            ->groupBy('user_id')
            ->map(fn ($dates) => collect($dates)->sortBy('days_away')->first())
            ->map(fn ($d) => collect($d)->only(['kind', 'label', 'next', 'days_away', 'turning'])->all());
    }

    private function sales(int $vendorId)
    {
        return Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->whereNotNull('paid_at')->whereNotIn('status', self::NOT_A_SALE);
    }

    private function customerOf(int $vendorId, int $customerId): User
    {
        abort_unless($this->records->isCustomerOf($vendorId, $customerId), 404, 'This person isn\'t one of your customers.');

        return User::findOrFail($customerId);
    }

    private function authorName(Request $request): string
    {
        $user = $request->user();
        if ($user instanceof Employee) {
            return $user->name;
        }

        return trim("{$user->name} {$user->surname}") . ' (owner)';
    }

    private function nameOf(User $u): string
    {
        return trim("{$u->name} {$u->surname}") ?: ($u->email ?? 'Customer');
    }

    private function photoOf(User $u): ?string
    {
        $pic = $u->profile_picture;
        if (! $pic) {
            return null;
        }

        return str_starts_with($pic, 'http') ? $pic : CloudinaryHelper::getUrl($pic);
    }
}
