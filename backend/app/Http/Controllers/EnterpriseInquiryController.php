<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseInquiry;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Talk to us" on the Enterprise plan. Visitors and signed-in vendors both
 * send the same form; admins pick it up in Subscription revenue → Enterprise.
 */
class EnterpriseInquiryController extends Controller
{
    /** POST /api/enterprise-inquiries (visitors) and /api/vendor/enterprise-inquiries (signed in) */
    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'shop_name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:20',
            'branches' => 'nullable|integer|min:1|max:500',
            'message' => 'required|string|min:10|max:2000',
        ], [
            'message.min' => 'Tell us a little more about what you need.',
        ]);

        $vendor = $request->user();
        $inquiry = EnterpriseInquiry::create($data + [
            'vendor_id' => $vendor instanceof User && $vendor->isVendor() ? $vendor->id : null,
            'status' => 'new',
        ]);

        // Every admin who can act on it sees it in their notifications
        $admins = User::query()->where('role', User::ROLE_ADMIN)->with('adminRole')->get()
            ->filter(fn (User $u) => $u->adminCan('subscriptions.manage') || $u->adminCan('revenue.view'))
            ->pluck('id');
        $notifications->notify(
            $admins,
            'enterprise_inquiry',
            "Enterprise inquiry from {$inquiry->shop_name}",
            "{$inquiry->name} wants to talk about the Enterprise plan.",
            '/admin/subscription-revenue?tab=enterprise',
            true,
        );

        return response()->json([
            'success' => true,
            'message' => "Thanks, {$inquiry->name}. Our team will reach you within 1–2 business days.",
        ], 201);
    }
}
