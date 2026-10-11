<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionCheckout;
use App\Models\VendorSubscriptionTrial;
use App\Subscriptions\SubscriptionPlans;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin view of what vendors pay for their BloomCraft subscription.
 *
 * Paid checkouts are the revenue ledger; vendor_subscriptions holds each
 * vendor's current plan and status (one row per vendor).
 */
class SubscriptionRevenueController extends Controller
{
    private const RANGES = ['7d', '30d', '90d', '12m', 'ytd', 'all'];
    private const CHECKOUT_STATUSES = ['paid', 'pending', 'failed', 'cancelled'];

    /**
     * GET /api/admin/subscriptions/revenue?range=30d
     */
    public function overview(Request $request): JsonResponse
    {
        $range = in_array($request->input('range'), self::RANGES, true) ? $request->input('range') : '30d';
        $now = CarbonImmutable::now();
        [$start, $end, $previousStart] = $this->window($range, $now);

        $paid = VendorSubscriptionCheckout::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->get(['id', 'vendor_id', 'plan_key', 'amount', 'paid_at']);

        $inRange = $paid->filter(fn ($c) => $c->paid_at->betweenIncluded($start, $end));
        $inPrevious = $previousStart
            ? $paid->filter(fn ($c) => $c->paid_at->gte($previousStart) && $c->paid_at->lt($start))
            : collect();

        // A vendor's first paid checkout is when they became a paying customer
        $firstPaidAt = $paid->groupBy('vendor_id')->map(fn ($rows) => $rows->min('paid_at'));
        $newPaying = $firstPaidAt->filter(fn ($at) => $at->betweenIncluded($start, $end))->count();
        $newPayingPrevious = $previousStart
            ? $firstPaidAt->filter(fn ($at) => $at->gte($previousStart) && $at->lt($start))->count()
            : null;

        $subscriptions = VendorSubscription::query()->get();
        $paying = $subscriptions->filter(fn ($s) => $this->isPaying($s, $now));
        $mrr = round($paying->sum(fn ($s) => $this->monthlyValue($s)), 2);

        $churned = $subscriptions->filter(fn ($s) => $this->churnedAt($s, $now)?->betweenIncluded($start, $end));
        $churnBase = $paying->count() + $churned->count();

        $revenue = round((float) $inRange->sum('amount'), 2);
        $previousRevenue = $previousStart ? round((float) $inPrevious->sum('amount'), 2) : null;

        $checkoutsInRange = VendorSubscriptionCheckout::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $settled = (int) ($checkoutsInRange['paid'] ?? 0) + (int) ($checkoutsInRange['failed'] ?? 0) + (int) ($checkoutsInRange['cancelled'] ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $range,
                'period' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
                'generated_at' => $now->toIso8601String(),
                'currency' => 'PHP',
                'kpis' => [
                    'mrr' => $mrr,
                    'arr' => round($mrr * 12, 2),
                    'revenue' => $revenue,
                    'revenue_previous' => $previousRevenue,
                    'lifetime_revenue' => round((float) $paid->sum('amount'), 2),
                    'transactions' => $inRange->count(),
                    'transactions_previous' => $previousStart ? $inPrevious->count() : null,
                    'paying_vendors' => $paying->count(),
                    'trialing_vendors' => $subscriptions->filter(fn ($s) => $s->isTrialActive($now))->count(),
                    'new_paying' => $newPaying,
                    'new_paying_previous' => $newPayingPrevious,
                    'arpu' => $paying->count() ? round($mrr / $paying->count(), 2) : 0,
                    'churned' => $churned->count(),
                    'churn_rate' => $churnBase ? round($churned->count() / $churnBase * 100, 1) : 0,
                    'trial_conversion' => $this->trialConversion($paid),
                    'checkout_success_rate' => $settled ? round(($checkoutsInRange['paid'] ?? 0) / $settled * 100, 1) : null,
                    'pending_checkouts' => (int) ($checkoutsInRange['pending'] ?? 0),
                    'failed_checkouts' => (int) ($checkoutsInRange['failed'] ?? 0),
                ],
                'series' => $this->series($range, $start, $end, $inRange, $firstPaidAt),
                'plans' => $this->plans($subscriptions, $paying, $inRange, $now),
                'statuses' => $this->statuses($subscriptions, $now),
                'renewals' => $this->renewals($subscriptions, $now),
                'top_vendors' => $this->topVendors($paid),
            ],
        ]);
    }

    /**
     * GET /api/admin/subscriptions/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
        $page = $this->transactionQuery($request)->paginate($perPage);
        $vendors = $this->vendorDetails($page->getCollection()->pluck('vendor_id'));

        return response()->json([
            'success' => true,
            'data' => $page->getCollection()->map(fn ($c) => $this->formatTransaction($c, $vendors))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'counts' => VendorSubscriptionCheckout::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    /**
     * GET /api/admin/subscriptions/transactions/export (CSV, same filters)
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->transactionQuery($request)->get();
        $vendors = $this->vendorDetails($rows->pluck('vendor_id'));

        return response()->streamDownload(function () use ($rows, $vendors) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Store', 'Owner', 'Email', 'Plan', 'Amount', 'Currency', 'Status', 'Created', 'Paid', 'PayMongo payment']);
            foreach ($rows as $checkout) {
                $t = $this->formatTransaction($checkout, $vendors);
                fputcsv($out, [
                    $t['reference_number'], $t['vendor']['store_name'], $t['vendor']['name'], $t['vendor']['email'],
                    $t['plan_name'], number_format($t['amount'], 2, '.', ''), $t['currency'], $t['status'],
                    $checkout->created_at?->format('Y-m-d H:i'), $checkout->paid_at?->format('Y-m-d H:i'),
                    $checkout->paymongo_payment_id,
                ]);
            }
            fclose($out);
        }, 'subscription-transactions-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * GET /api/admin/subscriptions/subscribers
     */
    public function subscribers(Request $request): JsonResponse
    {
        $now = CarbonImmutable::now();
        $subscriptions = VendorSubscription::query()->orderByDesc('updated_at')->get();
        $vendors = $this->vendorDetails($subscriptions->pluck('vendor_id'));
        $lifetime = VendorSubscriptionCheckout::query()
            ->where('status', 'paid')
            ->selectRaw('vendor_id, sum(amount) as total, count(*) as payments')
            ->groupBy('vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $rows = $subscriptions->map(function ($s) use ($vendors, $lifetime, $now) {
            $plan = SubscriptionPlans::exists($s->plan_key) ? SubscriptionPlans::get($s->plan_key) : null;

            return [
                'id' => $s->id,
                'vendor' => $vendors[$s->vendor_id] ?? $this->unknownVendor($s->vendor_id),
                'plan_key' => $s->plan_key,
                'plan_name' => $plan['name'] ?? ucfirst($s->plan_key),
                'status' => $this->effectiveStatus($s, $now),
                'monthly_value' => $this->isPaying($s, $now) ? $this->monthlyValue($s) : 0,
                'started_at' => ($s->subscription_started_at ?? $s->trial_started_at ?? $s->created_at)?->toIso8601String(),
                'ends_at' => ($s->trial_ends_at && $s->status === SubscriptionStatus::Trialing ? $s->trial_ends_at : $s->subscription_ends_at)?->toIso8601String(),
                'lifetime_revenue' => round((float) ($lifetime[$s->vendor_id]->total ?? 0), 2),
                'payments' => (int) ($lifetime[$s->vendor_id]->payments ?? 0),
            ];
        });

        if ($status = $request->input('status')) {
            $rows = $rows->where('status', $status);
        }
        if ($plan = $request->input('plan')) {
            $rows = $rows->where('plan_key', $plan);
        }
        if ($search = mb_strtolower(trim((string) $request->input('search')))) {
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', [
                $r['vendor']['store_name'], $r['vendor']['name'], $r['vendor']['email'],
            ])), $search));
        }

        return response()->json(['success' => true, 'data' => $rows->values()]);
    }

    // ── Overview pieces ──────────────────────────────────────────────────

    /** [start, end, start of the previous equal-length period or null] */
    private function window(string $range, CarbonImmutable $now): array
    {
        $end = $now;
        $start = match ($range) {
            '7d' => $now->subDays(6)->startOfDay(),
            '30d' => $now->subDays(29)->startOfDay(),
            '90d' => $now->subDays(89)->startOfDay(),
            '12m' => $now->subMonthsNoOverflow(11)->startOfMonth(),
            'ytd' => $now->startOfYear(),
            'all' => $this->firstActivity() ?? $now->startOfMonth(),
        };

        $previousStart = $range === 'all' ? null : $start->sub($start->diff($end));

        return [$start, $end, $previousStart];
    }

    private function firstActivity(): ?CarbonImmutable
    {
        $first = collect([
            VendorSubscriptionCheckout::query()->min('created_at'),
            VendorSubscription::query()->min('created_at'),
        ])->filter()->min();

        return $first ? CarbonImmutable::parse($first)->startOfMonth() : null;
    }

    private function series(string $range, CarbonImmutable $start, CarbonImmutable $end, Collection $paid, Collection $firstPaidAt): array
    {
        $unit = match ($range) {
            '7d', '30d' => 'day',
            '90d' => 'week',
            default => 'month',
        };

        $buckets = [];
        $cursor = match ($unit) {
            'day' => $start->startOfDay(),
            'week' => $start->startOfWeek(),
            'month' => $start->startOfMonth(),
        };
        while ($cursor->lte($end)) {
            $next = match ($unit) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
            };
            $buckets[] = [
                'start' => $cursor,
                'end' => $next,
                'label' => match ($unit) {
                    'day' => $cursor->format('M j'),
                    'week' => $cursor->format('M j'),
                    'month' => $cursor->format('M Y'),
                },
            ];
            $cursor = $next;
        }

        $cumulative = 0.0;
        $points = array_map(function ($bucket) use ($paid, $firstPaidAt, &$cumulative) {
            $inBucket = $paid->filter(fn ($c) => $c->paid_at->gte($bucket['start']) && $c->paid_at->lt($bucket['end']));
            $revenue = round((float) $inBucket->sum('amount'), 2);
            $cumulative += $revenue;

            return [
                'label' => $bucket['label'],
                'start' => $bucket['start']->toDateString(),
                'revenue' => $revenue,
                'cumulative' => round($cumulative, 2),
                'transactions' => $inBucket->count(),
                'new_paying' => $firstPaidAt->filter(fn ($at) => $at->gte($bucket['start']) && $at->lt($bucket['end']))->count(),
            ];
        }, $buckets);

        return ['unit' => $unit, 'points' => $points];
    }

    private function plans(Collection $subscriptions, Collection $paying, Collection $paidInRange, CarbonImmutable $now): array
    {
        $mrr = $paying->sum(fn ($s) => $this->monthlyValue($s)) ?: 0;

        return collect(SubscriptionPlans::all())->map(function ($plan, $key) use ($subscriptions, $paying, $paidInRange, $mrr, $now) {
            $planMrr = $paying->where('plan_key', $key)->sum(fn ($s) => $this->monthlyValue($s));

            return [
                'key' => $key,
                'name' => $plan['name'],
                'price' => $plan['monthly_price'] ?? $plan['minimum_monthly_price'] ?? null,
                'custom_pricing' => $plan['custom_pricing'],
                'paying' => $paying->where('plan_key', $key)->count(),
                'trialing' => $subscriptions->where('plan_key', $key)->filter(fn ($s) => $s->isTrialActive($now))->count(),
                'mrr' => round($planMrr, 2),
                'mrr_share' => $mrr ? round($planMrr / $mrr * 100, 1) : 0,
                'revenue' => round((float) $paidInRange->where('plan_key', $key)->sum('amount'), 2),
            ];
        })->values()->all();
    }

    private function statuses(Collection $subscriptions, CarbonImmutable $now): array
    {
        $counts = $subscriptions->countBy(fn ($s) => $this->effectiveStatus($s, $now));

        return collect(['active', 'trialing', 'past_due', 'pending', 'cancelled', 'expired'])
            ->map(fn ($status) => ['status' => $status, 'count' => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    /** Paid subscriptions renewing and trials ending in the next 30 days */
    private function renewals(Collection $subscriptions, CarbonImmutable $now): array
    {
        $horizon = $now->addDays(30);
        $upcoming = $subscriptions
            ->map(function ($s) use ($now, $horizon) {
                if ($s->isTrialActive($now) && $s->trial_ends_at->lte($horizon)) {
                    return ['subscription' => $s, 'kind' => 'trial_ends', 'date' => $s->trial_ends_at];
                }
                $date = $s->next_billing_at ?? $s->subscription_ends_at;
                if ($this->isPaying($s, $now) && $date && $date->lte($horizon)) {
                    return ['subscription' => $s, 'kind' => 'renewal', 'date' => $date];
                }
                return null;
            })
            ->filter()
            ->sortBy(fn ($row) => $row['date'])
            ->take(8);

        $vendors = $this->vendorDetails($upcoming->pluck('subscription.vendor_id'));

        return $upcoming->map(function ($row) use ($vendors, $now) {
            $s = $row['subscription'];
            $plan = SubscriptionPlans::exists($s->plan_key) ? SubscriptionPlans::get($s->plan_key) : null;

            return [
                'vendor' => $vendors[$s->vendor_id] ?? $this->unknownVendor($s->vendor_id),
                'plan_name' => $plan['name'] ?? ucfirst($s->plan_key),
                'kind' => $row['kind'],
                'amount' => $row['kind'] === 'renewal' ? $this->monthlyValue($s) : (float) ($plan['monthly_price'] ?? 0),
                'date' => $row['date']->toIso8601String(),
                'days_left' => max(0, (int) ceil($now->diffInHours($row['date']) / 24)),
            ];
        })->values()->all();
    }

    private function topVendors(Collection $paid): array
    {
        $totals = $paid->groupBy('vendor_id')
            ->map(fn ($rows, $vendorId) => [
                'vendor_id' => $vendorId,
                'revenue' => round((float) $rows->sum('amount'), 2),
                'payments' => $rows->count(),
                'last_paid_at' => $rows->max('paid_at')->toIso8601String(),
            ])
            ->sortByDesc('revenue')
            ->take(5);

        $vendors = $this->vendorDetails($totals->keys());

        return $totals->map(fn ($row) => $row + [
            'vendor' => $vendors[$row['vendor_id']] ?? $this->unknownVendor($row['vendor_id']),
        ])->values()->all();
    }

    /**
     * Trials that ended (or were cut short by a payment) and how many of
     * those vendors went on to pay.
     */
    private function trialConversion(Collection $paid): array
    {
        $now = now();
        $trials = VendorSubscriptionTrial::query()->get(['vendor_id', 'started_at', 'ends_at']);
        $converted = $trials->filter(fn ($t) => $paid->contains(
            fn ($c) => $c->vendor_id === $t->vendor_id && $c->paid_at->gte($t->started_at)
        ));
        $decided = $trials->filter(fn ($t) => $t->ends_at->lte($now))->merge($converted)->unique(fn ($t) => $t->vendor_id . $t->started_at);

        return [
            'trials' => $trials->count(),
            'converted' => $converted->count(),
            'decided' => $decided->count(),
            'rate' => $decided->count() ? round($converted->count() / $decided->count() * 100, 1) : null,
        ];
    }

    // ── Subscription helpers ─────────────────────────────────────────────

    private function isPaying(VendorSubscription $s, CarbonInterface $now): bool
    {
        return $s->status === SubscriptionStatus::Active
            && $s->paid_at !== null
            && ($s->subscription_ends_at === null || $now->lt($s->subscription_ends_at));
    }

    /** What the subscription is worth per month (custom Enterprise deals use the amount paid) */
    private function monthlyValue(VendorSubscription $s): float
    {
        if ($s->paid_amount !== null) {
            $amount = (float) $s->paid_amount;
            return round($s->billing_period === 'yearly' ? $amount / 12 : $amount, 2);
        }

        $plan = SubscriptionPlans::exists($s->plan_key) ? SubscriptionPlans::get($s->plan_key) : null;
        return (float) ($plan['monthly_price'] ?? 0);
    }

    /** When a once-paying subscription stopped (cancelled, expired or lapsed without renewal) */
    private function churnedAt(VendorSubscription $s, CarbonInterface $now): ?CarbonInterface
    {
        if ($s->paid_at === null) {
            return null;
        }
        if ($s->status === SubscriptionStatus::Cancelled) {
            return $s->cancelled_at ?? $s->updated_at;
        }
        if ($s->status === SubscriptionStatus::Expired) {
            return $s->expired_at ?? $s->subscription_ends_at ?? $s->updated_at;
        }
        if ($s->status === SubscriptionStatus::Active && $s->subscription_ends_at && $now->gte($s->subscription_ends_at)) {
            return $s->subscription_ends_at;
        }

        return null;
    }

    /** The stored status, corrected for trials and periods that ran out */
    private function effectiveStatus(VendorSubscription $s, CarbonInterface $now): string
    {
        if ($s->status === SubscriptionStatus::Trialing) {
            return $s->isTrialActive($now) ? 'trialing' : 'expired';
        }
        if ($s->status === SubscriptionStatus::Active && $s->subscription_ends_at && $now->gte($s->subscription_ends_at)) {
            return 'expired';
        }

        return $s->status->value;
    }

    // ── Transactions ─────────────────────────────────────────────────────

    private function transactionQuery(Request $request)
    {
        $query = VendorSubscriptionCheckout::query()->orderByDesc('created_at')->orderByDesc('id');

        if (in_array($request->input('status'), self::CHECKOUT_STATUSES, true)) {
            $query->where('status', $request->input('status'));
        }
        if (SubscriptionPlans::exists((string) $request->input('plan'))) {
            $query->where('plan_key', $request->input('plan'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', CarbonImmutable::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', CarbonImmutable::parse($request->input('to'))->endOfDay());
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $emails = VendorApplication::query()->whereRaw('lower(store_name) like ?', [$like])->pluck('email');
            $vendorIds = User::query()
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(email) like ?', [$like])
                ->orWhereIn('email', $emails)
                ->pluck('id');

            $query->where(fn ($q) => $q
                ->whereRaw('lower(reference_number) like ?', [$like])
                ->orWhereIn('vendor_id', $vendorIds));
        }

        return $query;
    }

    private function formatTransaction(VendorSubscriptionCheckout $c, array $vendors): array
    {
        $plan = SubscriptionPlans::exists($c->plan_key) ? SubscriptionPlans::get($c->plan_key) : null;

        return [
            'id' => $c->id,
            'reference_number' => $c->reference_number,
            'vendor' => $vendors[$c->vendor_id] ?? $this->unknownVendor($c->vendor_id),
            'plan_key' => $c->plan_key,
            'plan_name' => $plan['name'] ?? ucfirst($c->plan_key),
            'amount' => (float) $c->amount,
            'currency' => $c->currency,
            'billing_period' => $c->billing_period,
            'status' => $c->status,
            'paymongo_payment_id' => $c->paymongo_payment_id,
            'created_at' => $c->created_at?->toIso8601String(),
            'paid_at' => $c->paid_at?->toIso8601String(),
        ];
    }

    // ── Vendors ──────────────────────────────────────────────────────────

    /** Store name and logo for each vendor user id */
    private function vendorDetails(Collection $vendorIds): array
    {
        $users = User::query()->whereIn('id', $vendorIds->filter()->unique()->values())->get(['id', 'name', 'email']);
        $stores = VendorApplication::query()
            ->whereIn('email', $users->pluck('email'))
            ->orderByDesc('id')
            ->get()
            ->unique('email')
            ->keyBy('email');

        return $users->mapWithKeys(fn ($user) => [$user->id => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'store_name' => $stores[$user->email]->store_name ?? $user->name,
            'logo_url' => $stores[$user->email]->store_logo_url ?? null,
        ]])->all();
    }

    private function unknownVendor($id): array
    {
        return ['id' => $id, 'name' => 'Deleted vendor', 'email' => null, 'store_name' => 'Deleted vendor', 'logo_url' => null];
    }
}
