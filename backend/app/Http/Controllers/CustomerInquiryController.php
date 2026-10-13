<?php

namespace App\Http\Controllers;

use App\Models\CustomerInquiry;
use App\Models\Order;
use App\Models\Product;
use App\Services\VendorNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customers asking a shop about a product or one of their orders, and the
 * answered product questions every shopper can read.
 */
class CustomerInquiryController extends Controller
{
    /** Unanswered questions one customer can have waiting at one shop */
    private const MAX_OPEN_PER_SHOP = 5;

    /** GET /products/{id}/questions — public, answered and shown */
    public function productQuestions(int $productId): JsonResponse
    {
        if (! VendorNotificationService::ready()) {
            return response()->json(['success' => true, 'data' => [], 'total' => 0]);
        }
        $items = CustomerInquiry::query()->where('product_id', $productId)
            ->where('status', '!=', 'open')->whereNotNull('answer')->where('is_public', true)
            ->with('customer:id,name')
            ->orderByDesc('answered_at')->limit(30)->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'answer' => $q->answer,
                'asked_by' => $this->firstName($q->customer?->name),
                'asked_at' => $q->created_at?->toIso8601String(),
                'answered_at' => $q->answered_at?->toIso8601String(),
            ]),
            'total' => $items->count(),
        ]);
    }

    /** GET /customer/inquiries?product_id|order_id — the signed-in customer's own */
    public function mine(Request $request): JsonResponse
    {
        if (! VendorNotificationService::ready()) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $data = $request->validate(['product_id' => 'nullable|integer', 'order_id' => 'nullable|integer']);
        $items = CustomerInquiry::query()->where('customer_id', $request->user()->id)
            ->when($data['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($data['order_id'] ?? null, fn ($q, $id) => $q->where('order_id', $id))
            ->latest()->limit(30)->get();

        // Reading them here counts as seeing the answer
        CustomerInquiry::query()->whereIn('id', $items->whereNotNull('answered_at')->whereNull('customer_seen_at')->pluck('id'))
            ->update(['customer_seen_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $items->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'answer' => $q->answer,
                'status' => $q->status,
                'asked_at' => $q->created_at?->toIso8601String(),
                'answered_at' => $q->answered_at?->toIso8601String(),
            ]),
        ]);
    }

    /** POST /customer/inquiries {product_id|order_id, question} */
    public function store(Request $request): JsonResponse
    {
        if (! VendorNotificationService::ready()) {
            return response()->json(['success' => false, 'message' => 'Questions open soon. Please message the shop in the meantime.'], 503);
        }
        $user = $request->user();
        $data = $request->validate([
            'product_id' => 'nullable|integer|required_without:order_id',
            'order_id' => 'nullable|integer|required_without:product_id',
            'question' => 'required|string|min:5|max:1000',
        ], [
            'question.required' => 'Type your question.',
            'question.min' => 'Add a little more detail so the shop can help.',
        ]);

        $productId = null;
        $orderId = null;
        if (! empty($data['order_id'])) {
            $order = Order::withoutGlobalScopes()->whereKey($data['order_id'])->where('user_id', $user->id)->first();
            if (! $order) {
                throw ValidationException::withMessages(['order_id' => "That order isn't one of yours."]);
            }
            $vendorId = $order->vendor_id;
            $orderId = $order->id;
        } else {
            $product = Product::withoutGlobalScopes()->whereKey($data['product_id'])->whereNull('deleted_at')->first();
            if (! $product) {
                throw ValidationException::withMessages(['product_id' => 'That product is no longer available.']);
            }
            $vendorId = $product->owner_id;
            $productId = $product->id;
        }

        if ((int) $vendorId === (int) $user->id) {
            throw ValidationException::withMessages(['question' => "You can't ask your own shop a question."]);
        }
        $waiting = CustomerInquiry::query()->where('customer_id', $user->id)->where('vendor_id', $vendorId)->where('status', 'open')->count();
        if ($waiting >= self::MAX_OPEN_PER_SHOP) {
            throw ValidationException::withMessages(['question' => 'You already have a few questions waiting for this shop. Please wait for an answer, or message them.']);
        }

        CustomerInquiry::create([
            'vendor_id' => $vendorId,
            'customer_id' => $user->id,
            'product_id' => $productId,
            'order_id' => $orderId,
            'question' => trim($data['question']),
            'status' => 'open',
            'is_public' => $productId !== null,
        ]);

        return response()->json(['success' => true, 'message' => "Question sent. We'll let you know when the shop answers."], 201);
    }

    private function firstName(?string $name): string
    {
        $first = trim(explode(' ', trim((string) $name))[0] ?? '');

        return $first !== '' ? $first : 'A shopper';
    }
}
