<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\CustomerOffer;
use App\Models\CustomerOfferRecipient;
use App\Models\Employee;
use App\Models\User;
use App\Models\VendorPromotion;
use App\Services\CustomerRecords;
use App\Services\CustomerSegments;
use App\Services\NotificationService;
use App\Support\VendorDirectory;
use App\Traits\ResolvesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRM → Segments (/crm/segments…): the shop's customers grouped from purchase
 * history and customer-approved preferences, and offers sent to a group.
 * Offers reach only customers who said yes to offers and haven't muted the
 * shop, and at most one every few days.
 */
class CrmSegmentsController extends Controller
{
    use ResolvesOwner;

    /** A customer gets at most one offer from a shop in this many days */
    public const COOLDOWN_DAYS = 3;

    public function __construct(private CustomerSegments $segments, private CustomerRecords $records, private NotificationService $notifications) {}

    /** GET /crm/segments */
    public function index(Request $request): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $members = $this->segments->members($vendorId);
        $reachable = $this->segments->reachable($vendorId, $members->keys()->map(fn ($id) => (int) $id));

        $groups = collect(CustomerSegments::SEGMENTS)->map(function ($meta, $key) use ($members, $reachable) {
            $ids = $members->filter(fn ($m) => in_array($key, $m['segments'], true))->keys()->map(fn ($id) => (int) $id);

            return [
                'key' => $key,
                'label' => $meta[0],
                'rule' => $meta[1],
                'count' => $ids->count(),
                'reachable' => $ids->intersect($reachable)->count(),
                'spent' => round((float) $members->only($ids->all())->sum('spent'), 2),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => [
            'customers' => $members->count(),
            'reachable' => $reachable->count(),
            'segments' => $groups,
            'offers' => CustomerOffer::where('vendor_id', $vendorId)->latest()->limit(8)->get()
                ->map(fn ($o) => $this->offerRow($o))->values(),
            'promotions' => VendorPromotion::where('owner_id', $vendorId)->where('is_active', true)->where('ends_at', '>=', now())->orderBy('ends_at')
                ->get(['id', 'name', 'discount_type', 'discount_value', 'ends_at'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'label' => $this->promotionLabel($p), 'ends_at' => $p->ends_at?->toIso8601String()])->values(),
            'cooldown_days' => self::COOLDOWN_DAYS,
        ]]);
    }

    /** GET /crm/segments/{segment}?search&page */
    public function show(Request $request, string $segment): JsonResponse
    {
        abort_unless(isset(CustomerSegments::SEGMENTS[$segment]), 404);
        $data = $request->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $vendorId = $this->resolveOwnerId($request);
        $members = $this->segments->members($vendorId)->filter(fn ($m) => in_array($segment, $m['segments'], true));
        $reachable = $this->segments->reachable($vendorId, $members->keys()->map(fn ($id) => (int) $id));
        $users = User::whereIn('id', $members->keys())->get(['id', 'name', 'surname', 'email', 'profile_picture'])->keyBy('id');

        $term = mb_strtolower(trim((string) ($data['search'] ?? '')));
        $rows = $members->map(function ($m, $id) use ($users, $reachable) {
            $u = $users->get($id);

            return [
                'id' => (int) $id,
                'name' => $u ? trim("{$u->name} {$u->surname}") : 'Customer',
                'email' => $u?->email,
                'avatar' => $u?->profile_picture ? (str_starts_with($u->profile_picture, 'http') ? $u->profile_picture : CloudinaryHelper::getUrl($u->profile_picture)) : null,
                'orders' => $m['orders'],
                'spent' => $m['spent'],
                'last_order_at' => $m['last_at']?->toIso8601String(),
                'segments' => $m['segments'],
                'why' => $m['event_reasons'],
                'gets_offers' => $reachable->contains((int) $id),
            ];
        })->filter(fn ($r) => $term === '' || str_contains(mb_strtolower("{$r['name']} {$r['email']}"), $term))
            ->sortByDesc('spent')->values();

        $page = (int) ($data['page'] ?? 1);

        return response()->json(['success' => true, 'data' => [
            'segment' => ['key' => $segment, 'label' => CustomerSegments::SEGMENTS[$segment][0], 'rule' => CustomerSegments::SEGMENTS[$segment][1]],
            'rows' => $rows->forPage($page, 20)->values(),
            'meta' => ['total' => $rows->count(), 'page' => $page, 'last_page' => max(1, (int) ceil($rows->count() / 20))],
        ]]);
    }

    /** POST /crm/segments/{segment}/offers {title, body, promotion_id?, product_id?} */
    public function send(Request $request, string $segment): JsonResponse
    {
        abort_unless(isset(CustomerSegments::SEGMENTS[$segment]), 404);
        $data = $request->validate([
            'title' => 'required|string|min:4|max:120',
            'body' => 'required|string|min:10|max:1000',
            'promotion_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
        ]);
        $vendorId = $this->resolveOwnerId($request);
        if (! empty($data['promotion_id']) && ! VendorPromotion::where('owner_id', $vendorId)->whereKey($data['promotion_id'])->exists()) {
            return response()->json(['success' => false, 'errors' => ['promotion_id' => ['Pick one of your promotions.']]], 422);
        }
        if (! empty($data['product_id']) && ! $this->records->products($vendorId)->whereNull('deleted_at')->whereKey($data['product_id'])->exists()) {
            return response()->json(['success' => false, 'errors' => ['product_id' => ['Pick one of your products.']]], 422);
        }

        $members = $this->segments->members($vendorId)->filter(fn ($m) => in_array($segment, $m['segments'], true));
        $reachable = $this->segments->reachable($vendorId, $members->keys()->map(fn ($id) => (int) $id));
        // Nobody hears from the shop twice in a few days
        $recent = CustomerOfferRecipient::where('vendor_id', $vendorId)->whereIn('user_id', $reachable)
            ->where('created_at', '>=', now()->subDays(self::COOLDOWN_DAYS))->pluck('user_id')->map(fn ($id) => (int) $id);
        $to = $reachable->diff($recent)->values();
        if ($to->isEmpty()) {
            return response()->json(['success' => false, 'message' => $reachable->isEmpty()
                ? 'No one in this group has said yes to offers yet.'
                : 'Everyone in this group already got an offer from you in the last ' . self::COOLDOWN_DAYS . ' days.'], 422);
        }

        $user = $request->user();
        $offer = DB::transaction(function () use ($vendorId, $segment, $data, $to, $user) {
            $offer = CustomerOffer::create([
                'vendor_id' => $vendorId,
                'segment' => $segment,
                'title' => trim($data['title']),
                'body' => trim($data['body']),
                'promotion_id' => $data['promotion_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'recipients' => $to->count(),
                'sent_by' => $user instanceof Employee ? $user->name : trim("{$user->name} {$user->surname}"),
            ]);
            $now = now();
            CustomerOfferRecipient::insert($to->map(fn ($id) => ['offer_id' => $offer->id, 'user_id' => $id, 'vendor_id' => $vendorId, 'created_at' => $now, 'updated_at' => $now])->all());

            return $offer;
        });

        $shop = VendorDirectory::nameOf($vendorId);
        $this->notifications->notify($to, 'offer', "{$shop}: {$offer->title}", $offer->body, '/customer/profile');

        $skipped = $reachable->count() - $to->count();

        return response()->json([
            'success' => true,
            'message' => "Sent to {$to->count()} " . ($to->count() === 1 ? 'customer' : 'customers') . ($skipped ? " ({$skipped} got an offer from you recently)" : '') . '.',
            'data' => $this->offerRow($offer),
        ], 201);
    }

    private function offerRow(CustomerOffer $o): array
    {
        return [
            'id' => $o->id,
            'segment' => $o->segment,
            'segment_label' => CustomerSegments::SEGMENTS[$o->segment][0] ?? $o->segment,
            'title' => $o->title,
            'body' => $o->body,
            'recipients' => $o->recipients,
            'seen' => $o->deliveries()->whereNotNull('seen_at')->count(),
            'sent_by' => $o->sent_by,
            'created_at' => $o->created_at?->toIso8601String(),
        ];
    }

    private function promotionLabel(VendorPromotion $p): string
    {
        return $p->discount_type === VendorPromotion::PERCENT ? rtrim(rtrim((string) $p->discount_value, '0'), '.') . '% off' : '₱' . number_format((float) $p->discount_value, 2) . ' off';
    }
}
