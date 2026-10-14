<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use App\Models\LoginAuditLog;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorSubscriptionCheckout;
use App\Models\VendorSubscriptionTrial;
use App\Services\ResourceLimitService;
use App\Subscriptions\SubscriptionPlans;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Subscription & account: plan, billing history, usage and limits, shop
 * settings and security. Owner-only (the vendor middleware keeps employees out)
 * and open on every plan, including an expired one, so a vendor can always
 * see where they stand and renew.
 */
class VendorAccountController extends Controller
{
    /** What each subscription module unlocks, in the vendor's words. */
    private const MODULES = [
        'products'     => ['Products & catalog', 'List products, inventory, categories, 3D bouquets and promotions'],
        'orders'       => ['Orders', 'Take, prepare and track customer orders'],
        'deliveries'   => ['Deliveries', 'Plan delivery runs and update customers on the way'],
        'reservations' => ['Reservations', 'Let customers book flowers for a future date'],
        'calendar'     => ['Reservations calendar', 'See bookings by day and close dates you are away'],
        'scanning'     => ['QR scanning', 'Scan orders to process, ship and confirm delivery'],
        'crm'          => ['Customer chat & inquiries', 'Chat with customers and answer product questions'],
        'finance'      => ['Finance & books', 'Sales, expenses, profit and loss, and reports'],
        'staff'        => ['Staff & roles', 'Employee accounts, roles, permissions and schedules'],
        'procurement'  => ['Procurement', 'Funding requests and purchasing'],
        'suppliers'    => ['Suppliers', 'Keep a list of your flower and material suppliers'],
        'warehouse'    => ['Warehouse', 'Stock locations, receiving and warehouse inventory'],
        'supply_chain' => ['Supply chain', 'Supply chain dashboard and purchase orders'],
        'logistics'    => ['Logistics', 'Shipments and shipment tracking'],
        'hr'           => ['HR dashboard', 'An overview of your people'],
        'employees'    => ['Employee directory', 'Employee profiles and records'],
        'attendance'   => ['Attendance', 'Clock-ins, QR attendance and timesheets'],
        'payroll'      => ['Payroll', 'Pay runs and payslips'],
        'leave'        => ['Leave management', 'Leave requests and approvals'],
    ];

    private const PENDING_CHECKOUT_HOURS = 24;

    // ── Subscription plan ────────────────────────────────────────────────

    public function subscription(Request $request)
    {
        $vendor = $request->user();
        $subscription = $vendor->subscription()->first();
        $active = $subscription?->isActive() ?? false;
        $plan = $subscription && SubscriptionPlans::exists($subscription->plan_key) ? SubscriptionPlans::get($subscription->plan_key) : null;
        $keys = array_keys(SubscriptionPlans::all());

        $pending = VendorSubscriptionCheckout::query()
            ->where('vendor_id', $vendor->id)->where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(self::PENDING_CHECKOUT_HOURS))
            ->whereNotNull('checkout_url')->latest()->first();

        $endsAt = $subscription?->status?->value === 'trialing' ? $subscription->trial_ends_at : $subscription?->subscription_ends_at;

        return response()->json(['success' => true, 'data' => [
            'current' => $subscription ? [
                'plan_key'        => $subscription->plan_key,
                'plan_name'       => $plan['name'] ?? Str::headline($subscription->plan_key),
                'status'          => $subscription->isExpired() ? 'expired' : $subscription->status?->value,
                'active'          => $active,
                'monthly_price'   => $plan['monthly_price'] ?? null,
                'currency'        => $plan['currency'] ?? 'PHP',
                'started_at'      => $subscription->subscription_started_at?->toIso8601String() ?? $subscription->trial_started_at?->toIso8601String(),
                'period_start'    => ($subscription->current_period_started_at ?? $subscription->trial_started_at)?->toIso8601String(),
                'ends_at'         => $endsAt?->toIso8601String(),
                'days_left'       => $endsAt && $endsAt->isFuture() ? (int) ceil(now()->floatDiffInDays($endsAt)) : 0,
                'trial'           => $subscription->status?->value === 'trialing',
                'rank'            => array_search($subscription->plan_key, $keys, true),
            ] : null,
            'plans' => array_values(array_map(fn (array $p) => [
                'key'                   => $p['key'],
                'name'                  => $p['name'],
                'monthly_price'         => $p['monthly_price'],
                'minimum_monthly_price' => $p['minimum_monthly_price'] ?? null,
                'currency'              => $p['currency'],
                'custom_pricing'        => $p['custom_pricing'],
                'free_trial_available'  => $p['free_trial_available'],
                'modules'               => $p['included_modules'],
                'limits'                => $p['resource_limits'],
                'rank'                  => array_search($p['key'], $keys, true),
            ], SubscriptionPlans::all())),
            'modules' => $this->moduleList($active ? ($plan['included_modules'] ?? []) : []),
            'business_trial_eligible' => ! VendorSubscriptionTrial::query()->where('vendor_id', $vendor->id)->where('plan_key', SubscriptionPlans::BUSINESS)->exists(),
            'pending_checkout' => $pending ? [
                'id' => $pending->id, 'plan_key' => $pending->plan_key,
                'plan_name' => SubscriptionPlans::exists($pending->plan_key) ? SubscriptionPlans::get($pending->plan_key)['name'] : $pending->plan_key,
                'checkout_url' => $pending->checkout_url, 'created_at' => $pending->created_at?->toIso8601String(),
            ] : null,
        ]]);
    }

    // ── Billing history ──────────────────────────────────────────────────

    public function billing(Request $request)
    {
        $vendor = $request->user();
        $subscription = $vendor->subscription()->first();

        $payments = VendorSubscriptionCheckout::query()->where('vendor_id', $vendor->id)->latest()->get()
            ->map(fn (VendorSubscriptionCheckout $c) => [
                'id'         => $c->id,
                'type'       => 'payment',
                'reference'  => $c->reference_number,
                'plan_name'  => $this->planName($c->plan_key),
                'amount'     => (float) $c->amount,
                'currency'   => $c->currency,
                'status'     => $this->checkoutStatus($c),
                'period'     => $c->paid_at ? [$c->paid_at->toIso8601String(), $c->paid_at->copy()->addMonthNoOverflow()->toIso8601String()] : null,
                'date'       => ($c->paid_at ?? $c->created_at)?->toIso8601String(),
                'invoice'    => $c->status === 'paid',
                'checkout_url' => $this->checkoutStatus($c) === 'pending' ? $c->checkout_url : null,
            ]);

        $trials = VendorSubscriptionTrial::query()->where('vendor_id', $vendor->id)->get()
            ->map(fn (VendorSubscriptionTrial $t) => [
                'id'        => 't' . $t->id,
                'type'      => 'trial',
                'reference' => null,
                'plan_name' => $this->planName($t->plan_key),
                'amount'    => 0.0,
                'currency'  => 'PHP',
                'status'    => 'trial',
                'period'    => [$t->started_at?->toIso8601String(), $t->ends_at?->toIso8601String()],
                'date'      => $t->started_at?->toIso8601String(),
                'invoice'   => false,
                'checkout_url' => null,
            ]);

        $items = $payments->concat($trials)->sortByDesc('date')->values();
        $paid = $payments->where('status', 'paid');
        $plan = $subscription && SubscriptionPlans::exists($subscription->plan_key) ? SubscriptionPlans::get($subscription->plan_key) : null;
        $renewsAt = $subscription?->isActive() && $subscription->status?->value === 'active' ? $subscription->subscription_ends_at : null;

        return response()->json(['success' => true, 'data' => [
            'summary' => [
                'total_paid'    => round((float) $paid->sum('amount'), 2),
                'payments'      => $paid->count(),
                'last_paid_at'  => $paid->max('date'),
                'renews_at'     => $renewsAt?->toIso8601String(),
                'renew_amount'  => $renewsAt ? ($plan['monthly_price'] ?? null) : null,
                'plan_name'     => $plan['name'] ?? null,
                'currency'      => 'PHP',
            ],
            'items' => $items,
        ]]);
    }

    public function invoice(Request $request, int $id)
    {
        $vendor = $request->user();
        $checkout = VendorSubscriptionCheckout::query()->where('vendor_id', $vendor->id)->where('status', 'paid')->find($id);
        if (! $checkout) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $shop = VendorApplication::findApprovedForVendorUser($vendor);
        $periodEnd = $checkout->paid_at->copy()->addMonthNoOverflow();
        $address = $shop?->billing_address ?: collect([$shop?->store_street, $shop?->store_address])->filter()->implode(', ');

        return response()->json(['success' => true, 'data' => [
            'number'    => $checkout->reference_number,
            'issued_at' => $checkout->paid_at->toIso8601String(),
            'status'    => 'paid',
            'bill_to'   => [
                'name'    => $shop?->store_name ?: trim("{$vendor->name} {$vendor->surname}"),
                'owner'   => $shop?->owner_name ?: trim("{$vendor->name} {$vendor->surname}"),
                'email'   => $vendor->email,
                'address' => $address ?: null,
            ],
            'lines' => [[
                'description' => "BloomCraft {$this->planName($checkout->plan_key)} plan, monthly subscription",
                'period'      => [$checkout->paid_at->toIso8601String(), $periodEnd->toIso8601String()],
                'quantity'    => 1,
                'amount'      => (float) $checkout->amount,
            ]],
            'total'    => (float) $checkout->amount,
            'currency' => $checkout->currency,
            'payment'  => [
                'method'    => 'GCash / Maya via PayMongo',
                'reference' => $checkout->paymongo_payment_id ? '••••' . substr($checkout->paymongo_payment_id, -6) : null,
            ],
        ]]);
    }

    // ── Usage & limits ───────────────────────────────────────────────────

    public function usage(Request $request, ResourceLimitService $limits)
    {
        $vendor = $request->user();
        $subscription = $vendor->subscription()->first();
        $active = $subscription?->isActive() ?? false;
        $planKey = $active ? $subscription->plan_key : SubscriptionPlans::STARTER;
        $plan = SubscriptionPlans::get($planKey);

        $resources = collect($limits->summary($vendor))->map(function (array $r, string $key) use ($planKey) {
            // There is one shop location until branch tools exist
            $usage = $key === SubscriptionPlans::RESOURCE_BRANCHES ? 1 : $r['usage'];
            $next = $r['unlimited'] ? null : SubscriptionPlans::nextPlanWithMoreOf($planKey, $key);

            return [
                'key'       => $key,
                'label'     => self::RESOURCE_LABELS[$key] ?? $r['label'],
                'hint'      => self::RESOURCE_HINTS[$key] ?? null,
                'usage'     => $usage,
                'limit'     => $r['limit'],
                'unlimited' => $r['unlimited'],
                'can_add'   => $r['unlimited'] || $usage < $r['limit'],
                'next_plan' => $next ? ['name' => $next['name'], 'limit' => $next['resource_limits'][$key] ?? null] : null,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => [
            'plan_key'  => $active ? $planKey : null,
            'plan_name' => $active ? $plan['name'] : null,
            'active'    => $active,
            'resources' => $resources,
            'features'  => $this->moduleList($active ? $plan['included_modules'] : []),
        ]]);
    }

    private const RESOURCE_LABELS = [
        'products' => 'Products', 'staff_employees' => 'Employee accounts', 'warehouses' => 'Warehouses', 'branches' => 'Shop locations',
    ];
    private const RESOURCE_HINTS = [
        'products'        => 'Active, draft and hidden products all count. Deleted ones don\'t.',
        'staff_employees' => 'Every employee account, active or not.',
        'warehouses'      => 'Stock locations used by the warehouse tools.',
        'branches'        => 'Your shop counts as one. Tools for more locations are on the way.',
    ];

    // ── Shop settings ────────────────────────────────────────────────────

    public function shopSettings(Request $request)
    {
        $shop = VendorApplication::findApprovedForVendorUser($request->user());
        if (! $shop) {
            return response()->json(['success' => false, 'message' => 'Shop not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->serializeSettings($shop)]);
    }

    public function updateShopSettings(Request $request)
    {
        $shop = VendorApplication::findApprovedForVendorUser($request->user());
        if (! $shop) {
            return response()->json(['success' => false, 'message' => 'Shop not found.'], 404);
        }

        $data = $request->validate([
            'same_day_delivery'   => 'required|boolean',
            'lead_time_days'      => 'required|integer|min:0|max:30',
            'max_orders_per_day'  => 'required|integer|min:1|max:500',
            'service_areas'       => ['nullable', 'string', 'max:500', VendorApplication::serviceAreaRule()],
            'cancellation_policy' => 'required|string|max:1000',
            ...VendorProfileController::scheduleRules(),
            'preferences'                  => 'required|array',
            'preferences.accepting_orders' => 'required|boolean',
            'preferences.away_message'     => 'nullable|string|max:200',
            'preferences.back_on'          => 'nullable|date_format:Y-m-d|after:today',
            'preferences.accept_cod'       => 'required|boolean',
        ], [
            ...VendorProfileController::SCHEDULE_MESSAGES,
            'cancellation_policy.required' => 'Tell customers your cancellation and refund policy.',
            'preferences.back_on.after'    => 'Pick a date after today.',
        ]);

        $prefs = $data['preferences'];
        if (! $prefs['accept_cod'] && ! $shop->payment_details_completed) {
            throw ValidationException::withMessages([
                'preferences.accept_cod' => ['Add your payout details in your profile first, otherwise customers would have no way to pay.'],
            ]);
        }

        $schedules = VendorProfileController::normalizeSchedules($data['operating_schedules']);
        $days = (int) $data['lead_time_days'];

        $shop->fill([
            'delivery_handled_by' => 'self',
            'same_day_delivery'   => $data['same_day_delivery'],
            'lead_time'           => $days === 0 ? 'Same day' : ($days === 1 ? '1 day' : "{$days} days"),
            'max_orders_per_day'  => $data['max_orders_per_day'],
            'service_areas'       => isset($data['service_areas']) ? trim($data['service_areas']) : null,
            'cancellation_policy' => trim($data['cancellation_policy']),
            'operating_schedules' => $schedules,
            'operating_hours'     => VendorProfileController::describeSchedules($schedules),
            'shop_preferences'    => [
                'accepting_orders' => (bool) $prefs['accepting_orders'],
                'away_message'     => $prefs['accepting_orders'] ? null : (trim((string) ($prefs['away_message'] ?? '')) ?: null),
                'back_on'          => $prefs['accepting_orders'] ? null : ($prefs['back_on'] ?? null),
                'accept_cod'       => (bool) $prefs['accept_cod'],
            ],
        ]);
        $shop->updateProfileCompletion();
        $shop->save();

        return response()->json([
            'success' => true,
            'message' => 'Shop settings saved.',
            'data'    => $this->serializeSettings($shop->fresh()),
        ]);
    }

    private function serializeSettings(VendorApplication $shop): array
    {
        $now = Carbon::now(VendorApplication::RESERVATION_TIMEZONE);

        return [
            'same_day_delivery'   => (bool) $shop->same_day_delivery,
            'lead_time_days'      => $shop->reservationLeadTimeDays(),
            'max_orders_per_day'  => (int) ($shop->max_orders_per_day ?: 10),
            'service_areas'       => $shop->service_areas ?? '',
            'cancellation_policy' => $shop->cancellation_policy ?? '',
            'operating_schedules' => $shop->operating_schedules ?? [],
            'operating_hours'     => $shop->operating_hours ?? '',
            'preferences'         => $shop->shopPreferences(),
            'payment_details_completed' => (bool) $shop->payment_details_completed,
            'taking_orders'       => $shop->isTakingOrders(),
            'today'               => $now->toDateString(),
        ];
    }

    // ── Security settings ────────────────────────────────────────────────

    public function security(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $signIns = LoginAuditLog::query()->where('user_id', $user->id)->latest('logged_in_at')->limit(15)->get()
            ->map(fn (LoginAuditLog $l) => [
                'id'       => $l->id,
                'at'       => $l->logged_in_at?->toIso8601String(),
                'browser'  => $l->browser ?: null,
                'platform' => $l->platform ?: null,
                'device'   => $l->device_name ?: null,
                'location' => $l->location_label ?: null,
                'ip'       => $l->ip_address,
            ]);

        $failed = LoginAttempt::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDays(30))->latest('created_at')->get();

        return response()->json(['success' => true, 'data' => [
            'account' => [
                'name'     => trim("{$user->name} {$user->surname}"),
                'username' => $user->username,
                'email'    => $user->email,
            ],
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'two_factor_enabled'  => (bool) $user->two_factor_enabled,
            'sign_ins'            => $signIns,
            'failed_attempts'     => [
                'count'  => $failed->count(),
                'recent' => $failed->take(8)->map(fn (LoginAttempt $a) => [
                    'at'     => $a->created_at?->toIso8601String(),
                    'reason' => $a->reason,
                    'ip'     => $a->ip_address,
                ])->values(),
            ],
        ]]);
    }

    /**
     * A vendor has one sign-in token, so a fresh one signs every other device
     * out. The new token comes back so this browser stays signed in.
     */
    public function signOutOtherDevices(Request $request)
    {
        $token = Str::random(80);
        $request->user()->forceFill(['api_token' => hash('sha256', $token)])->save();

        return response()->json([
            'success' => true,
            'message' => 'Every other device has been signed out.',
            'token'   => $token,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function moduleList(array $included): array
    {
        return collect(self::MODULES)->map(function (array $info, string $key) use ($included) {
            $required = SubscriptionPlans::requiredPlanForModule($key);

            return [
                'key'           => $key,
                'label'         => $info[0],
                'description'   => $info[1],
                'included'      => in_array($key, $included, true),
                'required_plan' => $required['name'] ?? null,
            ];
        })->values()->all();
    }

    private function planName(string $key): string
    {
        return SubscriptionPlans::exists($key) ? SubscriptionPlans::get($key)['name'] : Str::headline($key);
    }

    /** A checkout left unpaid for a day is treated as expired. */
    private function checkoutStatus(VendorSubscriptionCheckout $c): string
    {
        if ($c->status === 'pending' && $c->created_at?->lt(now()->subHours(self::PENDING_CHECKOUT_HOURS))) {
            return 'expired';
        }

        return $c->status;
    }
}
