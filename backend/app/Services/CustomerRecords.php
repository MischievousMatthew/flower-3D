<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CustomerDate;
use App\Models\CustomerInquiry;
use App\Models\CustomerPreference;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorCustomerProfile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared rules for customer management:
 * - a shop's customers are people who ordered, chatted or asked it a question
 * - what a customer shares reaches only those shops, and only when they say so
 * - a shop's own records about a customer stay with that shop, and the
 *   customer can see and remove them (internal notes excepted)
 */
class CustomerRecords
{
    /** Lists of words (flowers, colors…) are kept short and tidy */
    public const MAX_TAGS = 12;
    public const MAX_TAG_LENGTH = 40;

    /** Ids of everyone who has dealt with the shop */
    public function customerIds(int $vendorId): Collection
    {
        $orders = Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->whereNotNull('user_id')->distinct()->pluck('user_id');
        $chats = Conversation::withoutGlobalScopes()->where('vendor_id', $vendorId)->distinct()->pluck('customer_id');
        $questions = CustomerInquiry::query()->where('vendor_id', $vendorId)->distinct()->pluck('customer_id');

        return $orders->merge($chats)->merge($questions)->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function isCustomerOf(int $vendorId, int $customerId): bool
    {
        return Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $customerId)->exists()
            || Conversation::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('customer_id', $customerId)->exists()
            || CustomerInquiry::query()->where('vendor_id', $vendorId)->where('customer_id', $customerId)->exists();
    }

    /** The shops a customer has dealt with, by id: [id => shop name] */
    public function shopsOf(int $customerId): Collection
    {
        $ids = Order::withoutGlobalScopes()->where('user_id', $customerId)->distinct()->pluck('vendor_id')
            ->merge(Conversation::withoutGlobalScopes()->where('customer_id', $customerId)->distinct()->pluck('vendor_id'))
            ->merge(CustomerInquiry::query()->where('customer_id', $customerId)->distinct()->pluck('vendor_id'))
            ->map(fn ($id) => (int) $id)->unique();

        return User::whereIn('id', $ids)->with('vendor:id,email,store_name,registered_business_name')->get(['id', 'name', 'surname', 'email'])
            ->mapWithKeys(fn (User $u) => [$u->id => $u->vendor?->store_name ?: $u->vendor?->registered_business_name ?: trim("{$u->name} {$u->surname}")]);
    }

    /** Trimmed, de-duplicated, capped list of short words */
    public function tags($list): array
    {
        return collect(is_array($list) ? $list : [])
            ->map(fn ($t) => mb_substr(trim(preg_replace('/\s+/', ' ', (string) $t)), 0, self::MAX_TAG_LENGTH))
            ->filter()
            ->unique(fn ($t) => mb_strtolower($t))
            ->take(self::MAX_TAGS)
            ->values()
            ->all();
    }

    public function tagRules(): array
    {
        return ['nullable', 'array', 'max:' . self::MAX_TAGS];
    }

    public function dateRules(): array
    {
        return [
            'kind' => 'required|in:' . implode(',', CustomerDate::KINDS),
            'label' => 'required|string|max:80',
            'month' => 'required|integer|between:1,12',
            'day' => 'required|integer|between:1,31',
            'year' => 'nullable|integer|between:1900,' . now()->year,
            'note' => 'nullable|string|max:255',
        ];
    }

    /** A day that exists in that month (Feb 29 allowed) */
    public function validDay(int $month, int $day): bool
    {
        return $day <= Carbon::create(2024, $month, 1)->daysInMonth;
    }

    public function dateRow(CustomerDate $d, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $next = $d->nextOccurrence($today);

        return [
            'id' => $d->id,
            'kind' => $d->kind,
            'label' => $d->label,
            'month' => $d->month,
            'day' => $d->day,
            'year' => $d->year,
            'note' => $d->note,
            'added_by' => $d->added_by,
            'next' => $next->toDateString(),
            'days_away' => (int) $today->diffInDays($next),
            'turning' => $d->year && $d->kind !== 'other' ? $next->year - $d->year : null,
        ];
    }

    /** What the customer shares with shops, or null when they keep it to themselves */
    public function shared(int $customerId): ?array
    {
        $pref = CustomerPreference::find($customerId);
        if (! $pref?->share_with_shops) {
            return null;
        }

        return [
            'flowers' => $pref->flowers ?? [],
            'colors' => $pref->colors ?? [],
            'avoid' => $pref->avoid ?? [],
            'occasions' => $pref->occasions ?? [],
            'note' => $pref->note,
            'dates' => CustomerDate::where('user_id', $customerId)->whereNull('vendor_id')->get()
                ->map(fn ($d) => $this->dateRow($d))->sortBy('days_away')->values()->all(),
        ];
    }

    /** One shop's own record of a customer */
    public function shopRecord(int $vendorId, int $customerId): array
    {
        $p = VendorCustomerProfile::where('vendor_id', $vendorId)->where('customer_id', $customerId)->first();
        $favoriteIds = $p?->favorite_product_ids ?? [];

        return [
            'flowers' => $p?->flowers ?? [],
            'colors' => $p?->colors ?? [],
            'avoid' => $p?->avoid ?? [],
            'occasions' => $p?->occasions ?? [],
            'favorite_products' => $favoriteIds ? $this->products($vendorId)->whereIn('id', $favoriteIds)->get(['id', 'product_name'])
                ->map(fn ($x) => ['id' => $x->id, 'name' => $x->product_name])->values()->all() : [],
            'updated_by' => $p?->updated_by,
            'updated_at' => $p?->updated_at?->toIso8601String(),
            'dates' => CustomerDate::where('user_id', $customerId)->where('vendor_id', $vendorId)->get()
                ->map(fn ($d) => $this->dateRow($d))->sortBy('days_away')->values()->all(),
        ];
    }

    /** The shop's products, removed ones included */
    public function products(int $vendorId): Builder
    {
        return Product::withoutGlobalScopes()->where('owner_id', $vendorId);
    }
}
