<?php

namespace App\Http\Controllers;

use App\Services\VendorSubscriptionService;
use App\Services\SubscriptionAccessService;
use Illuminate\Http\Request;
use LogicException;

/**
 * Stage 2 exposes only trial initialization. Billing management, checkout,
 * webhooks, history, and UI are intentionally deferred.
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
}
