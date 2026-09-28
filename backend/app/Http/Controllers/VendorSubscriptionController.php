<?php

namespace App\Http\Controllers;

use App\Services\VendorSubscriptionService;
use App\Services\VendorSubscriptionCheckoutService;
use App\Models\VendorSubscriptionCheckout;
use App\Services\SubscriptionAccessService;
use Illuminate\Http\Request;
use LogicException;

/**
 * Vendor-only subscription actions. Customer order checkout remains in
 * CheckoutController and uses a separate payment record/path.
 */
class VendorSubscriptionController extends Controller
{
    /** Used by the shared Vue route guard for a vendor owner or employee. */
    public function access(Request $request, SubscriptionAccessService $access)
    {
        return response()->json([
            'success' => true,
            'data' => $access->accessSummary($request->user()),
        ]);
    }

    public function startBusinessTrial(Request $request, VendorSubscriptionService $subscriptions)
    {
        try {
            $subscription = $subscriptions->startBusinessTrial($request->user());

            return response()->json([
                'success' => true,
                'subscription' => $subscription,
            ], 201);
        } catch (LogicException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function createCheckout(Request $request, VendorSubscriptionCheckoutService $checkouts)
    {
        $data = $request->validate(['plan_key' => ['required', 'string']]);
        try {
            $checkout = $checkouts->create($request->user(), $data['plan_key']);
            return response()->json(['success' => true, 'data' => [
                'checkout_id' => $checkout->id, 'checkout_url' => $checkout->checkout_url,
                'status' => $checkout->status, 'plan_key' => $checkout->plan_key,
            ]], 201);
        } catch (LogicException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function cancelCheckout(Request $request, VendorSubscriptionCheckout $checkout, VendorSubscriptionCheckoutService $checkouts)
    {
        try {
            $checkouts->cancel($request->user(), $checkout);
            return response()->json(['success' => true]);
        } catch (LogicException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function checkoutStatus(Request $request, VendorSubscriptionCheckout $checkout)
    {
        if ($checkout->vendor_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Subscription checkout not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => [
            'status' => $checkout->status,
            'plan_key' => $checkout->plan_key,
            'paid_at' => $checkout->paid_at?->toIso8601String(),
        ]]);
    }

    /** PayMongo return triggers an authenticated gateway lookup, never browser-trusted activation. */
    public function paymentCallback(Request $request, VendorSubscriptionCheckoutService $checkouts)
    {
        $frontend = rtrim((string) config('app.frontend_url', 'https://bloomcraft-app.vercel.app'), '/');
        $checkout = VendorSubscriptionCheckout::find($request->integer('checkout_id'));
        $paid = $request->boolean('success') && $checkout && $checkouts->confirmPaidCheckout($checkout);
        $state = $paid ? 'paid' : ($request->boolean('success') ? 'pending' : 'cancelled');

        if ($paid) {
            $profilePath = '/' . ltrim((string) config('app.frontend_vendor_profile_path'), '/');
            return redirect($frontend . $profilePath . '?subscription_success=' . urlencode($checkout->fresh()->plan_key));
        }

        return redirect($frontend . '/pricing?subscription_payment=' . $state . '&checkout_id=' . urlencode((string) $request->query('checkout_id')));
    }
}
