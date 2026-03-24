<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Picqer\Barcode\BarcodeGeneratorSVG;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $service) {}

    // ─── POST /api/v1/barcode/scan ────────────────────────────────────────────
    // Universal scan endpoint — used by all scanner pages.
    // Body: { barcode: "DLV-XXXXXXXXXX", scanner_page: "to_process" }
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode'      => ['required', 'string', 'max:100'],
            'scanner_page' => ['required', 'string', 'in:to_process,to_ship,to_receive'],
        ]);

        try {
            $delivery = $this->service->processScan($data['barcode'], $data['scanner_page']);

            return response()->json([
                'success'     => true,
                'delivery'    => $this->formatDelivery($delivery),
                'order_status' => $delivery->order?->status,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── GET /api/v1/vendor/orders ────────────────────────────────────────────
    // Vendor sees only their own orders + delivery status.
    public function vendorOrders(Request $request): JsonResponse
    {
        $vendorId = Auth::id(); // vendor IS the authenticated user

        $filters = $request->validate([
            'status'   => ['nullable', 'string'],
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginated = $this->service->forVendor($vendorId, $filters);

        return response()->json([
            'data' => $paginated->items() ? array_map([$this, 'formatDelivery'], $paginated->items()) : [],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
            ],
            'status_counts' => $this->service->statusCounts(),
        ]);
    }

    // ─── GET /api/v1/sc/orders ────────────────────────────────────────────────
    // SC Coordinator sees ALL orders.
    public function scOrders(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status'   => ['nullable', 'string'],
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        // Resolve owner_id so statusCounts() is scoped to the same vendor
        $authUser = Auth::user();
        $ownerId  = null;
        if ($authUser instanceof \App\Models\Employee) {
            $ownerId = $authUser->owner_id;
        } else {
            $employee = \App\Models\Employee::where('id', Auth::id())->first();
            $ownerId  = $employee?->owner_id;
        }

        $paginated = $this->service->forSCCoordinator($filters);

        return response()->json([
            'data' => $paginated->items() ? array_map([$this, 'formatDelivery'], $paginated->items()) : [],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
            ],
            'status_counts' => $this->service->statusCounts($ownerId),
        ]);
    }

    public function barcode(int $id): JsonResponse
    {
        $delivery = Delivery::whereHas('order', fn ($q) => $q->where('id', $id))
            ->orWhere('id', $id)
            ->first();

        if (! $delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        if (empty($delivery->barcode)) {
            $delivery->barcode = 'DLV-' . strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 10));
            $delivery->save();
        }

        $barcodeValue = $delivery->barcode;

        $generator = new BarcodeGeneratorSVG();
        $svg = $generator->getBarcode(
            $barcodeValue,
            $generator::TYPE_CODE_128,
            2,
            60,
        );

        // Expand SVG height to fit label below bars
        $svg = str_replace(
            'height="60" viewBox="0 0 378 60"',
            'height="80" viewBox="0 0 378 80"',
            $svg
        );

        // But width varies per barcode — use regex for width only, hardcode height change
        $svg = preg_replace(
            '/height="60" viewBox="0 0 (\d+) 60"/',
            'height="80" viewBox="0 0 $1 80"',
            $svg
        );

        // Add white background rect + label below the 60px bars
        $svg = str_replace(
            '</svg>',
            '<rect x="0" y="60" width="100%" height="20" fill="#ffffff"/><text x="50%" y="74" text-anchor="middle" font-family="monospace" font-size="11" fill="#1e293b">' . htmlspecialchars($barcodeValue) . '</text></svg>',
            $svg
        );

        return response($svg)->header('Content-Type', 'image/svg+xml');

        return response()->json([
            'barcode'       => $barcodeValue,
            'delivery_id'   => $delivery->delivery_id,
            'svg'           => $svg,
            'barcode_value' => $barcodeValue,
            'barcode_svg'   => $svg,
        ]);
    }

    // Customer polls this to get delivery progress for their order.
    public function customerOrder(int $orderId): JsonResponse
    {
        // Ensure this customer owns the order
        $order = \App\Models\Order::where('id', $orderId)
            ->where('customer_id', Auth::id())
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $delivery = $this->service->forCustomerOrder($orderId);

        return response()->json([
            'order'    => [
                'id'     => $order->id,
                'status' => $order->status,
            ],
            'delivery' => $delivery ? $this->formatDelivery($delivery) : null,
            'timeline' => $delivery?->logs->map(fn ($log) => [
                'status'     => $log->status,
                'label'      => Delivery::STATUS_LABELS[$log->status] ?? $log->status,
                'scanned_at' => $log->scanned_at?->toIso8601String(),
                'scanned_by' => $log->scanner?->name,
            ]) ?? [],
        ]);
    }

    // ─── PATCH /api/v1/deliveries/{id}/status ─────────────────────────────────
    // Manual status override (SC Coordinator only).
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,to_processed,to_ship,to_received,completed,returned,refunded'],
        ]);

        try {
            $updated = $this->service->advanceStatus($delivery, $data['status']);
            return response()->json(['success' => true, 'delivery' => $this->formatDelivery($updated)]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── GET /api/v1/deliveries/{id}/logs ─────────────────────────────────────
    // Full audit log for a delivery.
    public function logs(int $id): JsonResponse
    {
        $delivery = Delivery::with(['logs.scanner'])->findOrFail($id);

        return response()->json([
            'delivery_id' => $delivery->delivery_id,
            'logs' => $delivery->logs->map(fn ($log) => [
                'id'         => $log->id,
                'status'     => $log->status,
                'label'      => Delivery::STATUS_LABELS[$log->status] ?? $log->status,
                'scanned_by' => $log->scanner?->name ?? 'System',
                'scanned_at' => $log->scanned_at?->toIso8601String(),
                'notes'      => $log->notes,
            ]),
        ]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function formatDelivery(Delivery $d): array
    {
        // Load order + items if not already eager-loaded
        if (! $d->relationLoaded('order')) {
            $d->load(['order.items', 'scanner']);
        } elseif ($d->order && ! $d->order->relationLoaded('items')) {
            $d->order->load('items');
        }

        $order = $d->order;

        // Use items() — the correct relationship name on Order
        $items = $order?->items ?? collect();

        return [
            'id'              => $d->id,
            'delivery_id'     => $d->delivery_id,
            'barcode'         => $d->barcode,
            'status'          => $d->status,
            'status_label'    => Delivery::STATUS_LABELS[$d->status] ?? $d->status,
            'last_scanned_at' => $d->last_scanned_at?->toIso8601String(),
            'last_scanned_by' => $d->scanner?->name,
            'updated_at'      => $d->updated_at?->toIso8601String(),
            'order'           => $order ? [
                'id'                     => $order->id,
                'order_number'           => $order->order_number,
                'status'                 => $order->status,
                'payment_status'         => $order->payment_status,
                'payment_method'         => $order->payment_method,
                'total_amount'           => (float) ($order->total_amount ?? 0),
                'subtotal'               => (float) ($order->subtotal ?? 0),
                'delivery_fee'           => (float) ($order->delivery_fee ?? 0),
                'vendor_id'              => $order->vendor_id,
                'store_name'             => $order->store_name,
                'delivery_address'       => $order->delivery_address,
                'delivery_contact_name'  => $order->delivery_contact_name,
                'delivery_contact_number'=> $order->delivery_contact_number,
                'reservation_date'       => $order->reservation_date?->toDateString(),
                'created_at'             => $order->created_at?->toIso8601String(),
                'delivered_at'           => $order->delivered_at?->toIso8601String(),
                'customer_notes'         => $order->customer_notes,
                'delivery_notes'         => $order->delivery_notes ?? null,
                'items_count'            => $items->count(),
                'items'                  => $items->map(fn ($i) => [
                    'id'             => $i->id,
                    'product_name'   => $i->product_name,
                    'product_description' => $i->product_description,
                    'quantity'       => $i->quantity,
                    'unit_price'     => (float) $i->unit_price,
                    'subtotal'       => (float) $i->subtotal,
                    'color'          => $i->color,
                    'size'           => $i->size,
                    'notes'          => $i->notes,
                    'customizations' => $i->customizations ?? [],
                    'product_image'  => $i->product_image,
                    'model_3d_url'   => $i->model_3d_url,
                    'model_3d_path'  => $i->model_3d_path,
                ])->values(),
            ] : null,
        ];
    }
}