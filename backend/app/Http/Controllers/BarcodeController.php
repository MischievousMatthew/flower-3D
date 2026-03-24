<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\BarcodeService;
use App\Services\OrderService;
use App\Services\ShipmentService;
use App\Services\WarehouseService;
use App\Models\WarehouseItem;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BarcodeController extends Controller
{
    public function __construct(
        protected BarcodeService  $barcodeService,
        protected OrderService    $orderService,
        protected ShipmentService $shipmentService,
        protected WarehouseService $warehouseService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/barcode/scan
    //
    // Universal scan endpoint. Resolves barcode to its entity type and
    // returns typed data. The frontend decides what action to take next,
    // OR the caller passes `action` for server-side workflow execution.
    //
    // Payload:
    //   { "barcode": "ITEM-BOLT-M8-LK3A2" }
    //   { "barcode": "ITEM-BOLT-M8-LK3A2", "action": "add_to_order", "order_id": 42 }
    //   { "barcode": "ORD-PO-20250601-1Z",  "action": "mark_shipped" }
    //   { "barcode": "SHIP-FDX-ABC123-2B",  "action": "mark_received" }
    // ─────────────────────────────────────────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'barcode'  => 'required|string|max:200',
            'action'   => 'nullable|in:add_to_order,mark_shipped,mark_received',
            'order_id' => 'nullable|integer|exists:purchase_orders,id',
        ]);

        try {
            $result = $this->barcodeService->scan($request->barcode);

            // If a workflow action is requested, execute it immediately
            if ($action = $request->action) {
                $result = match ($action) {
                    'add_to_order'  => $this->executeAddToOrder($result, $request),
                    'mark_shipped'  => $this->executeMarkShipped($result),
                    'mark_received' => $this->executeMarkReceived($result),
                    default         => $result,
                };
            }

            return response()->json([
                'success' => true,
                'type'    => $result['type'],
                'data'    => $result['data'],
                'action'  => $action ?? null,
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/barcode/generate
    //
    // Generates (or regenerates) a barcode for any entity.
    // Body: { "item_id": 1 } | { "order_id": 1 } | { "shipment_id": 1 }
    // ─────────────────────────────────────────────────────────────────────────

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'item_id'     => 'nullable|integer|exists:warehouse_items,id',
            'order_id'    => 'nullable|integer|exists:purchase_orders,id',
            'shipment_id' => 'nullable|integer|exists:shipments,id',
        ]);

        if ($id = $request->item_id) {
            $item    = WarehouseItem::findOrFail($id);
            $barcode = $this->barcodeService->assignItemBarcode($item);
            return response()->json(['barcode' => $barcode, 'type' => 'item', 'entity_id' => $id]);
        }

        if ($id = $request->order_id) {
            $order   = PurchaseOrder::findOrFail($id);
            $barcode = $this->barcodeService->assignOrderBarcode($order);
            return response()->json(['barcode' => $barcode, 'type' => 'order', 'entity_id' => $id]);
        }

        if ($id = $request->shipment_id) {
            $shipment = Shipment::findOrFail($id);
            $barcode  = $this->barcodeService->assignShipmentBarcode($shipment);
            return response()->json(['barcode' => $barcode, 'type' => 'shipment', 'entity_id' => $id]);
        }

        return response()->json(['message' => 'Provide item_id, order_id, or shipment_id.'], 422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/barcode/lookup?barcode=ITEM-BOLT-M8-LK3A2
    // ─────────────────────────────────────────────────────────────────────────

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['barcode' => 'required|string|max:200']);

        try {
            $result = $this->barcodeService->scan($request->barcode);
            return response()->json(['success' => true, ...$result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private workflow executors
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scan item barcode → attach item to a purchase order.
     */
    private function executeAddToOrder(array $scanResult, Request $request): array
    {
        if ($scanResult['type'] !== 'item') {
            throw new \RuntimeException('Expected an item barcode for add_to_order action.');
        }

        $orderId = $request->order_id;
        if (!$orderId) {
            throw new \RuntimeException('order_id is required for add_to_order action.');
        }

        $item  = $scanResult['data'];
        $order = PurchaseOrder::with('items')->findOrFail($orderId);

        DB::transaction(function () use ($order, $item) {
            $this->orderService->attachItems($order->id, [[
                'product_name' => $item->product_name,
                'sku'          => $item->sku,
                'quantity'     => 1,
                'price'        => $item->unit_price ?? 0,
            ]]);
        });

        return [
            'type' => 'item',
            'data' => $item->fresh(['warehouse']),
            'order' => $order->fresh(['items', 'supplier']),
        ];
    }

    /**
     * Scan order barcode → advance status to 'shipped'.
     */
    private function executeMarkShipped(array $scanResult): array
    {
        if ($scanResult['type'] !== 'order') {
            throw new \RuntimeException('Expected an order barcode for mark_shipped action.');
        }

        $order = $scanResult['data'];

        DB::transaction(function () use ($order) {
            $this->orderService->updateStatus($order->id, 'shipped');
        });

        return ['type' => 'order', 'data' => $order->fresh(['items', 'supplier'])];
    }

    /**
     * Scan shipment barcode → mark as received + update warehouse stock.
     */
    private function executeMarkReceived(array $scanResult): array
    {
        if ($scanResult['type'] !== 'shipment') {
            throw new \RuntimeException('Expected a shipment barcode for mark_received action.');
        }

        $shipment = $scanResult['data'];

        DB::transaction(function () use ($shipment) {
            $this->shipmentService->markReceived($shipment->id, [
                'received_date' => now()->toDateString(),
            ]);
        });

        return ['type' => 'shipment', 'data' => $shipment->fresh(['purchaseOrder.supplier', 'items'])];
    }
}