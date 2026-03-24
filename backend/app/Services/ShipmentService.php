<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\WarehouseItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentService
{
    /** Allowed forward-only status transitions */
    private const STATUS_TRANSITIONS = [
        'pending'          => ['in_transit'],
        'in_transit'       => ['out_for_delivery', 'failed'],
        'out_for_delivery' => ['delivered', 'failed'],
        'delivered'        => [],
        'failed'           => ['in_transit', 'returned'],
        'returned'         => [],
    ];

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * @param  array{status?: string, carrier?: string, per_page?: int}  $filters
     */
    public function listShipments(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return Shipment::query()
            ->where('owner_id', $ownerId)
            ->when(isset($filters['status']),  fn ($q) => $q->where('status',  $filters['status']))
            ->when(isset($filters['carrier']), fn ($q) => $q->where('carrier', 'like', "%{$filters['carrier']}%"))
            ->with('purchaseOrder', 'items.warehouseItem')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findShipment(int $id, int $ownerId): Shipment
    {
        return Shipment::where('owner_id', $ownerId)->with('purchaseOrder.supplier', 'items.warehouseItem')->findOrFail($id);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    /**
     * Create a shipment for a purchase order, reserving warehouse items.
     *
     * @param  array{
     *   purchase_order_id: int,
     *   carrier: string,
     *   items: array<int, array{warehouse_item_id: int, quantity: int}>
     * }  $data
     *
     * @throws \RuntimeException on invalid order status or duplicate shipment.
     */
    public function createShipment(array $data, int $ownerId): Shipment
    {
        return DB::transaction(function () use ($data, $ownerId) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->findOrFail($data['purchase_order_id']);

            if (!in_array($order->status, ['pending', 'processing'], true)) {
                throw new \RuntimeException(
                    "Cannot create shipment for order in [{$order->status}] status."
                );
            }

            if ($order->shipment()->exists()) {
                throw new \RuntimeException(
                    "Purchase order [{$order->order_number}] already has a shipment."
                );
            }

            // Validate all items before touching the DB
            foreach ($data['items'] as $item) {
                $this->assertSufficientStock($item['warehouse_item_id'], $item['quantity']);
            }

            $shipment = Shipment::create([
                'owner_id'          => $ownerId,
                'purchase_order_id' => $order->id,
                'tracking_number'   => $this->generateTrackingNumber($data['carrier']),
                'carrier'           => $data['carrier'],
                'status'            => 'pending',
            ]);

            foreach ($data['items'] as $item) {
                $shipment->items()->create([
                    'warehouse_item_id' => $item['warehouse_item_id'],
                    'quantity'          => $item['quantity'],
                ]);
            }

            $order->update(['status' => 'processing']);

            return $shipment->load('items.warehouseItem', 'purchaseOrder');
        });
    }

    // ─── Updates ──────────────────────────────────────────────────────────────

    /**
     * Update tracking number and/or carrier.
     *
     * @param  array{tracking_number?: string, carrier?: string}  $data
     */
    public function updateTracking(int $shipmentId, array $data, int $ownerId): Shipment
    {
        $shipment = Shipment::where('owner_id', $ownerId)->findOrFail($shipmentId);

        $shipment->update(array_filter([
            'tracking_number' => $data['tracking_number'] ?? null,
            'carrier'         => $data['carrier'] ?? null,
        ]));

        return $shipment->fresh();
    }

    /**
     * Mark shipment as in_transit, deduct warehouse stock, sync PO status.
     */
    public function markShipped(int $shipmentId, int $ownerId, ?string $shippedDate = null): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $ownerId, $shippedDate) {
            $shipment = Shipment::where('owner_id', $ownerId)->with('items')->findOrFail($shipmentId);

            $this->transitionStatus($shipment, 'in_transit');
            $shipment->update(['shipped_date' => $shippedDate ?? now()->toDateString()]);

            foreach ($shipment->items as $item) {
                WarehouseItem::lockForUpdate()
                    ->findOrFail($item->warehouse_item_id)
                    ->dispatchStock($item->quantity, "SHIPMENT-{$shipment->tracking_number}");
            }

            $shipment->purchaseOrder->update(['status' => 'shipped']);

            return $shipment->fresh();
        });
    }

    /**
     * Mark shipment as delivered, record received_date, sync PO status.
     */
    public function markReceived(int $shipmentId, int $ownerId, ?string $receivedDate = null): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $ownerId, $receivedDate) {
            $shipment = Shipment::where('owner_id', $ownerId)->findOrFail($shipmentId);

            $this->transitionStatus($shipment, 'delivered');
            $shipment->update(['received_date' => $receivedDate ?? now()->toDateString()]);

            $shipment->purchaseOrder->update(['status' => 'received']);

            return $shipment->fresh();
        });
    }

    /**
     * Generic status transition with guard.
     */
    public function updateStatus(int $shipmentId, string $newStatus, int $ownerId): Shipment
    {
        $shipment = Shipment::where('owner_id', $ownerId)->findOrFail($shipmentId);
        $this->transitionStatus($shipment, $newStatus);

        return $shipment->fresh();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function transitionStatus(Shipment $shipment, string $newStatus): void
    {
        $allowed = self::STATUS_TRANSITIONS[$shipment->status] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Invalid transition: [{$shipment->status}] → [{$newStatus}]. "
                . "Allowed: [" . implode(', ', $allowed) . "]"
            );
        }

        $shipment->update(['status' => $newStatus]);
    }

    private function assertSufficientStock(int $warehouseItemId, int $quantity): void
    {
        $item = WarehouseItem::findOrFail($warehouseItemId);

        if ($item->quantity < $quantity) {
            throw new \RuntimeException(
                "Insufficient stock for SKU [{$item->sku}]: "
                . "requested {$quantity}, available {$item->quantity}."
            );
        }
    }

    /** Format: {CARRIER}-{TIMESTAMP}-{RANDOM6} */
    private function generateTrackingNumber(string $carrier): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $carrier), 0, 4));

        do {
            $number = $prefix . '-' . now()->format('ymdHi') . '-' . strtoupper(Str::random(6));
        } while (Shipment::where('tracking_number', $number)->exists());

        return $number;
    }
}