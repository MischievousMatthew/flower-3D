<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorSubscription;
use App\Subscriptions\SubscriptionPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin "Plans & pricing": change each plan's name, monthly price,
 * description, highlights and limits. Changes are laid over the defaults in
 * SubscriptionPlans, so pricing pages, checkout and limits all follow. Shops
 * keep paying what they paid until their next payment.
 */
class SubscriptionPlanController extends Controller
{
    /** GET /admin/subscription-plans */
    public function index(): JsonResponse
    {
        $defaults = SubscriptionPlans::defaults();
        $counts = VendorSubscription::query()->select('plan_key', DB::raw('count(*) as total'))->groupBy('plan_key')->pluck('total', 'plan_key');
        $edited = DB::table('subscription_plan_settings')->get()->keyBy('plan_key');

        $plans = collect(SubscriptionPlans::all())->map(fn ($p, $key) => [
            'key' => $key,
            'name' => $p['name'],
            'monthly_price' => $p['monthly_price'],
            'currency' => $p['currency'],
            'description' => $p['description'],
            'highlights' => $p['highlights'],
            'featured' => $p['featured'],
            'limits' => array_intersect_key($p['resource_limits'], array_flip(SubscriptionPlans::EDITABLE_LIMITS)),
            'modules' => $p['included_modules'],
            'free_trial_available' => $p['free_trial_available'],
            'trial_duration_months' => $p['trial_duration_months'],
            'subscribers' => (int) ($counts[$key] ?? 0),
            'default' => [
                'name' => $defaults[$key]['name'],
                'monthly_price' => $defaults[$key]['monthly_price'],
            ],
            'updated_at' => $edited[$key]->updated_at ?? null,
            'updated_by_name' => $edited[$key]->updated_by_name ?? null,
        ])->values();

        return response()->json(['success' => true, 'data' => ['plans' => $plans]]);
    }

    /** PUT /admin/subscription-plans/{key} */
    public function update(Request $request, string $key): JsonResponse
    {
        if (! SubscriptionPlans::exists($key)) {
            return response()->json(['message' => 'That plan doesn\'t exist.'], 404);
        }

        $limitRule = ['nullable', 'integer', 'min:0', 'max:1000000'];
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            // PayMongo can't take less than ₱20 in one payment
            'monthly_price' => ['required', 'numeric', 'min:20', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:300'],
            'highlights' => ['nullable', 'array', 'max:6'],
            'highlights.*' => ['string', 'min:2', 'max:80'],
            'featured' => ['boolean'],
            'limits' => ['nullable', 'array'],
            'limits.products' => $limitRule,
            'limits.staff_employees' => $limitRule,
            'limits.warehouses' => $limitRule,
        ], [
            'monthly_price.min' => 'The price must be at least ₱20, the smallest amount PayMongo accepts.',
            'highlights.max' => 'Keep it to 6 highlights.',
        ]);

        // Each name is used once, so shops can tell the plans apart
        $taken = collect(SubscriptionPlans::all())->except($key)
            ->first(fn ($p) => mb_strtolower(trim($p['name'])) === mb_strtolower(trim($data['name'])));
        if ($taken) {
            return response()->json(['message' => "Another plan is already called {$taken['name']}.", 'errors' => ['name' => ['Use a different name.']]], 422);
        }

        $limits = [];
        foreach (SubscriptionPlans::EDITABLE_LIMITS as $resource) {
            if (array_key_exists($resource, $data['limits'] ?? [])) {
                $limits[$resource] = $data['limits'][$resource] === null ? null : (int) $data['limits'][$resource];
            }
        }

        DB::transaction(function () use ($key, $data, $limits, $request) {
            // One plan is marked "most popular" at a time
            if (! empty($data['featured'])) {
                foreach (array_keys(SubscriptionPlans::defaults()) as $other) {
                    if ($other !== $key) {
                        DB::table('subscription_plan_settings')->updateOrInsert(['plan_key' => $other], ['featured' => false, 'updated_at' => now(), 'created_at' => now()]);
                    }
                }
            }
            DB::table('subscription_plan_settings')->updateOrInsert(['plan_key' => $key], [
                'name' => trim($data['name']),
                'monthly_price' => round((float) $data['monthly_price'], 2),
                'description' => $data['description'] ?? null,
                'highlights' => json_encode(array_values(array_filter(array_map('trim', $data['highlights'] ?? [])))),
                'featured' => (bool) ($data['featured'] ?? false),
                'resource_limits' => json_encode($limits),
                'updated_by_name' => $request->user()?->name,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });
        SubscriptionPlans::flush();

        return response()->json([
            'success' => true,
            'message' => 'Saved. New checkouts use this price; shops already subscribed keep theirs until they renew.',
            'data' => SubscriptionPlans::get($key),
        ]);
    }

    /** POST /admin/subscription-plans/{key}/reset: back to the defaults */
    public function reset(string $key): JsonResponse
    {
        if (! SubscriptionPlans::exists($key)) {
            return response()->json(['message' => 'That plan doesn\'t exist.'], 404);
        }
        DB::table('subscription_plan_settings')->where('plan_key', $key)->delete();
        SubscriptionPlans::flush();

        return response()->json(['success' => true, 'message' => 'Back to the original name, price and limits.', 'data' => SubscriptionPlans::get($key)]);
    }
}
