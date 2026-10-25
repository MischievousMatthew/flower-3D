<?php

namespace App\Http\Controllers;

use App\Models\CustomerDate;
use App\Models\CustomerOfferRecipient;
use App\Models\VendorPromotion;
use App\Support\VendorDirectory;
use App\Models\CustomerPreference;
use App\Models\FavoriteProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorCustomerProfile;
use App\Services\CustomerRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The customer's side of customer management (/customer/preferences…):
 * their flower preferences and special dates, whether shops may see them,
 * their favorite products, and what each shop has saved about them, which
 * they can remove.
 */
class CustomerPreferencesController extends Controller
{
    public function __construct(private CustomerRecords $records) {}

    /** GET /customer/preferences */
    public function show(Request $request): JsonResponse
    {
        $user = $this->customer($request);

        return response()->json(['success' => true, 'data' => $this->payload($user)]);
    }

    /** PUT /customer/preferences {flowers[], colors[], avoid[], occasions[], note, share_with_shops} */
    public function update(Request $request): JsonResponse
    {
        $user = $this->customer($request);
        $data = $request->validate([
            'flowers' => $this->records->tagRules(), 'flowers.*' => 'string|max:60',
            'colors' => $this->records->tagRules(), 'colors.*' => 'string|max:60',
            'avoid' => $this->records->tagRules(), 'avoid.*' => 'string|max:60',
            'occasions' => $this->records->tagRules(), 'occasions.*' => 'string|max:60',
            'note' => 'nullable|string|max:500',
            'share_with_shops' => 'required|boolean',
            'accept_offers' => 'sometimes|boolean',
        ]);

        CustomerPreference::updateOrCreate(['user_id' => $user->id], [
            'flowers' => $this->records->tags($data['flowers'] ?? []),
            'colors' => $this->records->tags($data['colors'] ?? []),
            'avoid' => $this->records->tags($data['avoid'] ?? []),
            'occasions' => $this->records->tags($data['occasions'] ?? []),
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'share_with_shops' => $data['share_with_shops'],
        ] + (array_key_exists('accept_offers', $data) ? $this->offerConsent($user->id, (bool) $data['accept_offers'], 'profile') : []));

        return response()->json(['success' => true, 'message' => 'Preferences saved.', 'data' => $this->payload($user)]);
    }

    /** POST /customer/preferences/dates */
    public function storeDate(Request $request): JsonResponse
    {
        $user = $this->customer($request);
        $data = $request->validate($this->records->dateRules());
        $this->checkDay($data);
        if (CustomerDate::where('user_id', $user->id)->whereNull('vendor_id')->count() >= 20) {
            throw ValidationException::withMessages(['label' => 'You can save up to 20 dates.']);
        }

        CustomerDate::create($data + ['user_id' => $user->id, 'vendor_id' => null, 'added_by' => 'You']);

        return response()->json(['success' => true, 'message' => 'Date saved.', 'data' => $this->payload($user)], 201);
    }

    /** DELETE /customer/preferences/dates/{id}: their own dates, or one a shop recorded */
    public function destroyDate(Request $request, int $id): JsonResponse
    {
        $user = $this->customer($request);
        CustomerDate::where('user_id', $user->id)->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Date removed.', 'data' => $this->payload($user)]);
    }

    /** DELETE /customer/preferences/shops/{vendorId}: forget what that shop saved (preferences and dates) */
    public function forgetShop(Request $request, int $vendorId): JsonResponse
    {
        $user = $this->customer($request);
        VendorCustomerProfile::where('vendor_id', $vendorId)->where('customer_id', $user->id)->delete();
        CustomerDate::where('user_id', $user->id)->where('vendor_id', $vendorId)->delete();

        return response()->json(['success' => true, 'message' => 'Removed what this shop saved about you.', 'data' => $this->payload($user)]);
    }

    /** GET /customer/offers — offers shops sent them; opening the list marks them seen */
    public function offers(Request $request): JsonResponse
    {
        $user = $this->customer($request);
        $rows = CustomerOfferRecipient::where('user_id', $user->id)->latest()->limit(30)->with('offer')->get()->filter(fn ($r) => $r->offer);
        $shops = collect(VendorDirectory::details($rows->pluck('vendor_id')));
        $promos = VendorPromotion::whereIn('id', $rows->pluck('offer.promotion_id')->filter())->get()->keyBy('id');

        $list = $rows->map(fn ($r) => [
            'id' => $r->id,
            'vendor_id' => $r->vendor_id,
            'shop' => $shops[$r->vendor_id]['store_name'] ?? 'A shop',
            'title' => $r->offer->title,
            'body' => $r->offer->body,
            'promotion' => ($p = $promos->get($r->offer->promotion_id)) ? [
                'name' => $p->name,
                'label' => $p->discount_type === VendorPromotion::PERCENT ? rtrim(rtrim((string) $p->discount_value, '0'), '.') . '% off' : '₱' . number_format((float) $p->discount_value, 2) . ' off',
                'ends_at' => $p->ends_at?->toIso8601String(),
                'running' => $p->state() === 'running',
            ] : null,
            'product_id' => $r->offer->product_id,
            'new' => $r->seen_at === null,
            'at' => $r->created_at?->toIso8601String(),
        ])->values();
        CustomerOfferRecipient::where('user_id', $user->id)->whereNull('seen_at')->update(['seen_at' => now()]);

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * POST /customer/offers/consent {accept, source: checkout|prompt}: the
     * checkbox at checkout or the one-time question after an order. Either
     * way we don't ask again.
     */
    public function offerConsentAnswer(Request $request): JsonResponse
    {
        $user = $this->customer($request);
        $data = $request->validate(['accept' => 'required|boolean', 'source' => 'required|in:checkout,prompt']);
        $pref = CustomerPreference::firstOrCreate(['user_id' => $user->id]);
        // An unticked box at checkout says nothing: only a yes changes anything there
        if ($data['source'] === 'checkout' && ! $data['accept']) {
            return response()->json(['success' => true, 'data' => $this->payload($user)]);
        }
        $pref->update($this->offerConsent($user->id, (bool) $data['accept'], $data['source']) + ['offers_asked_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => $data['accept'] ? "You'll get offers from shops you order from. You can stop anytime in your profile." : 'No problem. You can turn offers on in your profile anytime.',
            'data' => $this->payload($user),
        ]);
    }

    /** POST|DELETE /customer/offers/shops/{vendorId} — stop or resume one shop's offers */
    public function muteShop(Request $request, int $vendorId): JsonResponse
    {
        $user = $this->customer($request);
        $pref = CustomerPreference::firstOrCreate(['user_id' => $user->id]);
        $muted = collect($pref->muted_offer_vendor_ids ?? [])->map(fn ($id) => (int) $id);
        $muted = $request->isMethod('delete') ? $muted->reject(fn ($id) => $id === $vendorId) : $muted->push($vendorId)->unique();
        $pref->update(['muted_offer_vendor_ids' => $muted->values()->all()]);

        return response()->json(['success' => true, 'message' => $request->isMethod('delete') ? "You'll get their offers again." : "You won't get offers from this shop.", 'data' => $this->payload($user)]);
    }

    /** GET /customer/favorites */
    public function favorites(Request $request): JsonResponse
    {
        $user = $this->customer($request);

        return response()->json(['success' => true, 'data' => $this->favoriteRows($user->id)]);
    }

    /** POST /customer/favorites/{productId} */
    public function favorite(Request $request, int $productId): JsonResponse
    {
        $user = $this->customer($request);
        Product::withoutGlobalScopes()->findOrFail($productId);
        if (FavoriteProduct::where('user_id', $user->id)->count() >= 200) {
            return response()->json(['success' => false, 'message' => 'You can keep up to 200 favorites.'], 422);
        }
        FavoriteProduct::firstOrCreate(['user_id' => $user->id, 'product_id' => $productId]);

        return response()->json(['success' => true, 'message' => 'Added to your favorites.', 'data' => ['favorite' => true]]);
    }

    /** DELETE /customer/favorites/{productId} */
    public function unfavorite(Request $request, int $productId): JsonResponse
    {
        $user = $this->customer($request);
        FavoriteProduct::where('user_id', $user->id)->where('product_id', $productId)->delete();

        return response()->json(['success' => true, 'message' => 'Removed from your favorites.', 'data' => ['favorite' => false]]);
    }

    /** The fields to save for a yes or no, keeping a record of when and where a yes was given */
    private function offerConsent(int $userId, bool $accept, string $source): array
    {
        $current = CustomerPreference::find($userId);
        if (! $accept) {
            return ['accept_offers' => false];
        }
        if ($current?->accept_offers) {
            return ['accept_offers' => true]; // already yes: keep the original record
        }

        return ['accept_offers' => true, 'offers_consent_at' => now(), 'offers_consent_source' => $source];
    }

    private function payload(User $user): array
    {
        $pref = CustomerPreference::find($user->id);
        $dates = CustomerDate::where('user_id', $user->id)->get();
        $shops = $this->records->shopsOf($user->id);

        // What each shop has saved about them (internal notes are not included)
        $saved = VendorCustomerProfile::where('customer_id', $user->id)->get()->keyBy('vendor_id');
        $shopIds = $saved->keys()->merge($dates->whereNotNull('vendor_id')->pluck('vendor_id'))->unique();
        $shopNames = $shopIds->diff($shops->keys())->isEmpty() ? $shops : $shops->union(
            User::whereIn('id', $shopIds->diff($shops->keys()))->with('vendor:id,email,store_name,registered_business_name')->get(['id', 'email', 'name'])
                ->mapWithKeys(fn ($u) => [$u->id => $u->vendor?->store_name ?: $u->vendor?->registered_business_name ?: $u->name])
        );

        return [
            'flowers' => $pref?->flowers ?? [],
            'colors' => $pref?->colors ?? [],
            'avoid' => $pref?->avoid ?? [],
            'occasions' => $pref?->occasions ?? [],
            'note' => $pref?->note,
            'share_with_shops' => (bool) $pref?->share_with_shops,
            'accept_offers' => (bool) $pref?->accept_offers,
            // Ask once, after their first order, if they haven't said yes or answered already
            'offers_prompt' => ! $pref?->accept_offers && ! $pref?->offers_asked_at
                && Order::withoutGlobalScopes()->where('user_id', $user->id)->exists(),
            'muted_shops' => collect(VendorDirectory::details($pref?->muted_offer_vendor_ids ?? []))
                ->map(fn ($v) => ['vendor_id' => $v['id'], 'shop' => $v['store_name']])->values()->all(),
            'dates' => $dates->whereNull('vendor_id')->map(fn ($d) => $this->records->dateRow($d))->sortBy('days_away')->values()->all(),
            'shop_count' => $shops->count(),
            'shops' => $shopIds->map(function ($vendorId) use ($saved, $dates, $shopNames) {
                $p = $saved->get($vendorId);

                return [
                    'vendor_id' => (int) $vendorId,
                    'shop' => $shopNames[$vendorId] ?? 'A shop',
                    'flowers' => $p?->flowers ?? [],
                    'colors' => $p?->colors ?? [],
                    'avoid' => $p?->avoid ?? [],
                    'occasions' => $p?->occasions ?? [],
                    'dates' => $dates->where('vendor_id', $vendorId)->map(fn ($d) => $this->records->dateRow($d))->values()->all(),
                    'updated_at' => $p?->updated_at?->toIso8601String(),
                ];
            })->values()->all(),
            'favorites' => $this->favoriteRows($user->id),
        ];
    }

    private function favoriteRows(int $userId): array
    {
        return FavoriteProduct::where('user_id', $userId)->latest()
            ->with(['product' => fn ($q) => $q->withTrashed()->with([
                'primaryImage' => fn ($i) => $i->withoutGlobalScopes(),
                'images' => fn ($i) => $i->withoutGlobalScopes(),
            ])])->get()
            ->filter(fn ($f) => $f->product)
            ->map(fn ($f) => [
                'product_id' => $f->product_id,
                'name' => $f->product->product_name,
                'price' => (float) ($f->product->selling_price ?? 0),
                'image' => $f->product->primaryImage?->image_url ?? $f->product->images->first()?->image_url,
                'available' => ! $f->product->deleted_at,
            ])->values()->all();
    }

    private function checkDay(array $data): void
    {
        if (! $this->records->validDay((int) $data['month'], (int) $data['day'])) {
            throw ValidationException::withMessages(['day' => 'That day doesn\'t exist in that month.']);
        }
    }

    private function customer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === User::ROLE_CUSTOMER, 403, 'Only customer accounts have these settings.');

        return $user;
    }
}
