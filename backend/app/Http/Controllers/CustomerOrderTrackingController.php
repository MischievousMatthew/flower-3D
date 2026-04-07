<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\VendorApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CustomerOrderTrackingController extends Controller
{
    /**
     * Delivery status groups per tab.
     */
    private const TAB_DELIVERY_STATUSES = [
        'processing' => ['pending', 'to_processed'],
        'out_for_delivery' => ['to_ship', 'to_received'],
        'completed' => ['completed'],
        'returned' => ['returned', 'refunded'],
    ];

    /**
     * GET /api/customer/orders
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'delivery_status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Order::where('user_id', Auth::id())
            ->with(['delivery.logs', 'vendor:id,name,email', 'items', 'orderRequests']);

        if ($request->filled('delivery_status')) {
            $statuses = self::TAB_DELIVERY_STATUSES[$request->delivery_status]
                ?? [$request->delivery_status];

            $query->whereHas('delivery', fn ($q) => $q->whereIn('status', $statuses));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('order_number', 'LIKE', "%{$s}%")
                ->orWhere('store_name', 'LIKE', "%{$s}%"));
        }

        $paginated = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => array_map([$this, 'formatOrder'], $paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    /**
     * GET /api/customer/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['delivery.logs', 'vendor:id,name,email', 'items', 'orderRequests'])
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatOrder($order)]);
    }

    /**
     * POST /api/customer/orders/{id}/complete
     */
    public function complete(int $id): JsonResponse
    {
        $order = $this->ownedOrder($id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $delivery = $order->delivery;
        if (! $delivery) {
            return response()->json(['success' => false, 'message' => 'No delivery record found'], 422);
        }

        if ($delivery->status !== 'to_received') {
            return response()->json([
                'success' => false,
                'message' => 'Order must be "Out for Delivery" before confirming receipt. Current: ' . $delivery->status,
            ], 422);
        }

        $delivery->update(['status' => 'completed', 'last_scanned_at' => now()]);
        $delivery->logs()->create([
            'owner_id' => $delivery->owner_id ?? $order->vendor_id,
            'status' => 'completed',
            'scanned_at' => now(),
            'notes' => 'Confirmed received by customer.',
        ]);

        $order->markAsCompleted();

        return response()->json(['success' => true, 'message' => 'Order marked as received. Thank you!']);
    }

    public function confirmReceived(int $id): JsonResponse
    {
        return $this->complete($id);
    }

    /**
     * POST /api/customer/orders/{id}/request-return
     */
    public function requestReturn(Request $request, int $id): JsonResponse
    {
        $order = $this->ownedOrder($id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (! $order->delivery || $order->delivery->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Return can only be requested for delivered orders.',
            ], 422);
        }

        if ($this->hasPendingRequest($order->id, 'return')) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending return request for this order.',
            ], 422);
        }

        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'],
        ]);

        [$mediaPath, $mediaType] = $this->storeMedia($request);

        OrderRequest::create([
            'owner_id' => $order->vendor_id,
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'type' => 'return',
            'reason' => $request->reason,
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'status' => 'pending',
        ]);

        $order->delivery->update(['status' => 'returned', 'last_scanned_at' => now()]);
        $order->delivery->logs()->create([
            'owner_id' => $order->delivery->owner_id ?? $order->vendor_id,
            'status' => 'returned',
            'scanned_at' => now(),
            'notes' => 'Return requested by customer.',
        ]);
        $order->update(['status' => 'returned']);

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted. Our team will contact you with next steps.',
        ]);
    }

    /**
     * POST /api/customer/orders/{id}/request-refund
     */
    public function requestRefund(Request $request, int $id): JsonResponse
    {
        $order = $this->ownedOrder($id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Refund can only be requested for paid orders.',
            ], 422);
        }

        if (! $order->delivery || ! in_array($order->delivery->status, ['completed', 'returned'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Refund can only be requested for delivered or returned orders.',
            ], 422);
        }

        if ($this->hasPendingRequest($order->id, 'refund')) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending refund request for this order.',
            ], 422);
        }

        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'],
        ]);

        [$mediaPath, $mediaType] = $this->storeMedia($request);

        OrderRequest::create([
            'owner_id' => $order->vendor_id,
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'type' => 'refund',
            'reason' => $request->reason,
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund request submitted and is waiting for vendor approval.',
        ]);
    }

    private function ownedOrder(int $id): ?Order
    {
        return Order::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['delivery', 'orderRequests'])
            ->first();
    }

    private function hasPendingRequest(int $orderId, string $type): bool
    {
        return OrderRequest::where('order_id', $orderId)
            ->where('type', $type)
            ->where('status', 'pending')
            ->exists();
    }

    private function storeMedia(Request $request): array
    {
        if (! $request->hasFile('media') || ! $request->file('media')->isValid()) {
            return [null, null];
        }

        $file = $request->file('media');
        $mime = $file->getMimeType();
        $mediaType = str_starts_with($mime, 'video/') ? 'video' : 'image';

        $result = CloudinaryHelper::upload($file->getRealPath(), [
            'folder' => 'returns',
            'resource_type' => $mediaType,
        ]);

        return [$result['public_id'], $mediaType];
    }

    private function formatOrder(Order $order): array
    {
        $delivery = $order->delivery;
        $items = $order->items ?? collect();

        $storeName = $order->store_name
            ?? VendorApplication::where('email', $order->vendor?->email)->value('store_name')
            ?? $order->vendor?->name;

        $requests = ($order->orderRequests ?? collect())
            ->sortByDesc('created_at')
            ->keyBy('type');

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'subtotal' => (float) ($order->subtotal ?? 0),
            'delivery_fee' => (float) ($order->delivery_fee ?? 0),
            'total_amount' => (float) ($order->total_amount ?? 0),
            'delivery_address' => $order->delivery_address,
            'delivery_contact_name' => $order->delivery_contact_name,
            'delivery_contact_number' => $order->delivery_contact_number,
            'store_name' => $storeName,
            'customer_notes' => $order->customer_notes,
            'reservation_date' => $order->reservation_date?->toDateString(),
            'created_at' => $order->created_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'items_count' => $items->count(),
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_name' => $i->product_name,
                'product_image' => $i->product_image,
                'quantity' => (int) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'subtotal' => (float) $i->subtotal,
                'color' => $i->color,
                'size' => $i->size,
                'notes' => $i->notes,
            ])->values(),
            'return_request' => isset($requests['return']) ? [
                'id' => $requests['return']->id,
                'status' => $requests['return']->status,
                'reason' => $requests['return']->reason,
                'media_url' => $requests['return']->media_url,
                'media_type' => $requests['return']->media_type,
                'created_at' => $requests['return']->created_at?->toIso8601String(),
            ] : null,
            'refund_request' => isset($requests['refund']) ? [
                'id' => $requests['refund']->id,
                'status' => $requests['refund']->status,
                'reason' => $requests['refund']->reason,
                'media_url' => $requests['refund']->media_url,
                'media_type' => $requests['refund']->media_type,
                'created_at' => $requests['refund']->created_at?->toIso8601String(),
            ] : null,
            'delivery' => $delivery ? [
                'id' => $delivery->id,
                'delivery_id' => $delivery->delivery_id,
                'barcode' => $delivery->barcode,
                'status' => $delivery->status,
                'status_label' => Delivery::STATUS_LABELS[$delivery->status] ?? $delivery->status,
                'last_scanned_at' => $delivery->last_scanned_at?->toIso8601String(),
                'timeline' => $delivery->logs
                    ->sortBy('scanned_at')
                    ->map(fn ($log) => [
                        'status' => $log->status,
                        'label' => Delivery::STATUS_LABELS[$log->status] ?? $log->status,
                        'scanned_at' => $log->scanned_at?->toIso8601String(),
                        'notes' => $log->notes,
                    ])
                    ->values(),
            ] : null,
        ];
    }
}
