<?php

namespace App\Http\Controllers;

use App\Services\VendorSubscriptionService;
use Illuminate\Http\Request;
use LogicException;

/**
 * Stage 2 exposes only trial initialization. Billing management, checkout,
 * webhooks, history, and UI are intentionally deferred.
 */
class VendorSubscriptionController extends Controller
{
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
