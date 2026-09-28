<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionCheckout;
use App\Subscriptions\SubscriptionPlans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LogicException;

class VendorSubscriptionCheckoutService
{
    public function create(User $vendor, string $planKey): VendorSubscriptionCheckout
    {
        if (! $vendor->isVendor()) throw new LogicException('Only vendor owners can purchase subscriptions.');
        if (! SubscriptionPlans::exists($planKey)) throw new LogicException('Unknown subscription plan.');
        $plan = SubscriptionPlans::get($planKey);
        if ($plan['custom_pricing']) throw new LogicException('Enterprise subscriptions require a custom agreement.');
        if ($plan['monthly_price'] === null) throw new LogicException('This plan does not have online monthly pricing.');
        $key = config('services.paymongo.secret_key');
        if (! $key) throw new LogicException('Subscription payments are not configured.');

        $attempt = bin2hex(random_bytes(12));
        $checkout = VendorSubscriptionCheckout::create([
            'vendor_id' => $vendor->id, 'plan_key' => $planKey, 'status' => 'pending',
            'amount' => $plan['monthly_price'], 'currency' => $plan['currency'], 'billing_period' => 'monthly',
            'payment_attempt' => $attempt, 'reference_number' => 'BC-SUB-' . $vendor->id . '-' . strtoupper(substr($attempt, 0, 12)),
        ]);
        $callback = rtrim(config('app.url'), '/') . '/api/subscription/payment/callback?checkout_id=' . $checkout->id;
        $attributes = [
            'send_email_receipt' => true, 'show_description' => true, 'show_line_items' => true,
            'description' => "BloomCraft {$plan['name']} monthly subscription",
            'line_items' => [[
                'currency' => $plan['currency'], 'amount' => (int) round($plan['monthly_price'] * 100),
                'description' => "{$plan['name']} monthly subscription", 'quantity' => 1, 'name' => "BloomCraft {$plan['name']}",
            ]],
            'payment_method_types' => ['gcash', 'paymaya'],
            'success_url' => $callback . '&success=true', 'cancel_url' => $callback . '&success=false',
            'reference_number' => $checkout->reference_number,
            'metadata' => [
                'payment_context' => 'vendor_subscription', 'vendor_subscription_checkout_id' => (string) $checkout->id,
                'vendor_id' => (string) $vendor->id, 'plan_key' => $planKey,
                'reference_number' => $checkout->reference_number, 'payment_attempt' => $attempt,
            ],
        ];
        try {
            $response = Http::withBasicAuth($key, '')->acceptJson()->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', ['data' => ['attributes' => $attributes]]);
            $data = $response->json();
            $url = data_get($data, 'data.attributes.checkout_url');
            if (! $response->successful() || ! $url) {
                $checkout->update(['status' => 'failed', 'metadata' => ['gateway_error' => data_get($data, 'errors.0.detail')]]);
                throw new LogicException(data_get($data, 'errors.0.detail', 'Payment gateway error. Please try again.'));
            }
            $checkout->update(['paymongo_checkout_session_id' => data_get($data, 'data.id'), 'checkout_url' => $url]);
            return $checkout->fresh();
        } catch (\Throwable $e) {
            if ($checkout->status === 'pending') $checkout->update(['status' => 'failed']);
            throw $e instanceof LogicException ? $e : new LogicException('Payment gateway error. Please try again.');
        }
    }

    public function cancel(User $vendor, VendorSubscriptionCheckout $checkout): void
    {
        if ($checkout->vendor_id !== $vendor->id) throw new LogicException('Subscription checkout not found.');
        if ($checkout->status === 'pending') $checkout->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function handlesWebhook(array $payload): bool
    {
        $attributes = data_get($payload, 'data.attributes.data.attributes', []);
        return data_get($attributes, 'metadata.payment_context') === 'vendor_subscription';
    }

    /**
     * The return URL is never proof of payment. It may only trigger this
     * authenticated PayMongo lookup, which independently verifies paid status.
     */
    public function confirmPaidCheckout(VendorSubscriptionCheckout $checkout): bool
    {
        if ($checkout->status !== 'pending' || ! $checkout->paymongo_checkout_session_id) return $checkout->status === 'paid';
        $key = config('services.paymongo.secret_key');
        if (! $key) return false;

        try {
            $response = Http::withBasicAuth($key, '')->acceptJson()->timeout(15)
                ->get('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($checkout->paymongo_checkout_session_id));
            if (! $response->successful()) return false;
            $attributes = data_get($response->json(), 'data.attributes', []);
            $metadata = data_get($attributes, 'metadata', []);
            if ((string) data_get($metadata, 'vendor_subscription_checkout_id') !== (string) $checkout->id) return false;
            $statuses = collect([
                data_get($attributes, 'status'), data_get($attributes, 'payment.status'),
                data_get($attributes, 'payment.attributes.status'), data_get($attributes, 'payment_intent.status'),
                data_get($attributes, 'payment_intent.attributes.status'),
            ])->merge(collect(data_get($attributes, 'payments', []))->map(
                fn ($payment) => data_get($payment, 'attributes.status', data_get($payment, 'status'))
            ))->filter()->map(fn ($status) => strtolower((string) $status));
            if (! $statuses->contains(fn ($status) => in_array($status, ['paid', 'succeeded'], true))) return false;

            $this->activatePaidCheckout($checkout, data_get($attributes, 'payment.id') ?? data_get($attributes, 'payment.attributes.id'));
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function handleWebhook(array $payload): void
    {
        $event = data_get($payload, 'data.attributes.type');
        $resource = data_get($payload, 'data.attributes.data', []);
        $attributes = data_get($resource, 'attributes', []);
        $metadata = data_get($attributes, 'metadata', []);
        $checkoutId = $metadata['vendor_subscription_checkout_id'] ?? null;
        $sessionId = $resource['id'] ?? data_get($attributes, 'checkout_session_id') ?? data_get($attributes, 'checkout_session.id');
        if (! $checkoutId && ! $sessionId && empty($metadata['reference_number'])) return;
        $checkout = VendorSubscriptionCheckout::query()
            ->where(function ($query) use ($checkoutId, $sessionId, $metadata) {
                if ($checkoutId) $query->where('id', $checkoutId);
                if ($sessionId) $query->orWhere('paymongo_checkout_session_id', $sessionId);
                if ($metadata['reference_number'] ?? null) $query->orWhere('reference_number', $metadata['reference_number']);
            })->first();
        if (! $checkout || $checkout->status !== 'pending') return;
        if (in_array($event, ['checkout_session.payment.failed', 'payment.failed'], true)) {
            $checkout->update(['status' => 'failed']); return;
        }
        if (! in_array($event, ['checkout_session.payment.paid', 'payment.paid'], true)) return;

        $this->activatePaidCheckout($checkout, data_get($attributes, 'payment.id') ?? data_get($attributes, 'payment.attributes.id'));
    }

    private function activatePaidCheckout(VendorSubscriptionCheckout $checkout, ?string $paymentId = null): void
    {
        DB::transaction(function () use ($checkout, $paymentId) {
            $checkout = VendorSubscriptionCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            if ($checkout->status !== 'pending') return;
            $periodStart = now();
            $periodEnd = $periodStart->copy()->addMonthNoOverflow();
            $subscription = VendorSubscription::query()->lockForUpdate()->firstOrNew(['vendor_id' => $checkout->vendor_id]);
            $subscription->fill([
                'plan_key' => $checkout->plan_key, 'status' => SubscriptionStatus::Active,
                'subscription_started_at' => $periodStart, 'subscription_ends_at' => $periodEnd,
                'trial_started_at' => null, 'trial_ends_at' => null,
                'current_period_started_at' => $periodStart, 'current_period_ends_at' => $periodEnd, 'next_billing_at' => $periodEnd,
                'cancelled_at' => null, 'expired_at' => null,
                'paymongo_checkout_session_id' => $checkout->paymongo_checkout_session_id,
                'paymongo_payment_id' => $paymentId, 'payment_status' => 'paid',
                'paid_amount' => $checkout->amount, 'paid_currency' => $checkout->currency, 'billing_period' => $checkout->billing_period, 'paid_at' => now(),
            ])->save();
            $checkout->update(['status' => 'paid', 'paymongo_payment_id' => $paymentId, 'paid_at' => now()]);
        });
    }
}
