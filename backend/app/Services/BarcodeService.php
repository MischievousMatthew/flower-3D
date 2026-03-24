<?php

namespace App\Services;

use App\Models\WarehouseItem;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BarcodeService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Barcode Format
    //
    // Items:     ITEM-{sku}-{timestamp_base36}     e.g.  ITEM-BOLT-M8-100-LK3A2
    // Orders:    ORD-{order_number}-{id_base36}    e.g.  ORD-PO-20250601-00001-1Z
    // Shipments: SHIP-{tracking}-{id_base36}       e.g.  SHIP-FDX-ABC123-2B
    // ─────────────────────────────────────────────────────────────────────────

    /** Generate a scannable barcode string for a warehouse item */
    public function generateItemBarcode(WarehouseItem $item): string
    {
        $ts      = base_convert(now()->timestamp, 10, 36);
        $sku     = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $item->sku));
        return "ITEM-{$sku}-{$ts}";
    }

    /** Generate a scannable barcode string for a purchase order */
    public function generateOrderBarcode(PurchaseOrder $order): string
    {
        $idB36   = base_convert($order->id, 10, 36);
        $num     = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $order->order_number));
        return "ORD-{$num}-{$idB36}";
    }

    /** Generate a scannable barcode string for a shipment */
    public function generateShipmentBarcode(Shipment $shipment): string
    {
        $idB36   = base_convert($shipment->id, 10, 36);
        $track   = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $shipment->tracking_number ?? $shipment->id));
        return "SHIP-{$track}-{$idB36}";
    }

    public function scan(string $barcode): array
    {
        $barcode = strtoupper(trim($barcode));

        if (str_starts_with($barcode, 'ITEM-')) {
            return $this->resolveItemBarcode($barcode);
        }

        if (str_starts_with($barcode, 'ORD-')) {
            return $this->resolveOrderBarcode($barcode);
        }

        if (str_starts_with($barcode, 'SHIP-')) {
            return $this->resolveShipmentBarcode($barcode);
        }

        // ✅ NEW: handle fixed DLV delivery barcodes
        if (str_starts_with($barcode, 'DLV-')) {
            return $this->resolveDeliveryBarcode($barcode);
        }

        throw new RuntimeException("Unrecognized barcode format: {$barcode}");
    }

    private function resolveDeliveryBarcode(string $barcode): array
    {
        $delivery = \App\Models\Delivery::with(['order', 'order.items'])
            ->where('barcode', $barcode)
            ->first();

        if (!$delivery) {
            throw new RuntimeException("No delivery found for barcode: {$barcode}");
        }

        return ['type' => 'delivery', 'data' => $delivery];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private resolvers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveItemBarcode(string $barcode): array
    {
        // Format: ITEM-{SKU}-{timestamp_b36}
        // Strip the ITEM- prefix and the last segment (timestamp) to recover SKU
        $inner  = substr($barcode, 5);                  // "BOLT-M8-100-LK3A2"
        $parts  = explode('-', $inner);
        array_pop($parts);                              // remove timestamp
        $sku    = implode('-', $parts);                 // "BOLT-M8-100"

        $item = WarehouseItem::where('sku', $sku)
            ->orWhere('sku', strtolower($sku))
            ->with(['warehouse'])
            ->first();

        if (!$item) {
            throw new RuntimeException("Item not found for SKU: {$sku}");
        }

        return ['type' => 'item', 'data' => $item];
    }

    private function resolveOrderBarcode(string $barcode): array
    {
        // Format: ORD-{ORDER_NUMBER}-{id_b36}
        $inner  = substr($barcode, 4);                  // "PO-20250601-00001-1Z"
        $parts  = explode('-', $inner);
        $idB36  = array_pop($parts);                    // "1Z"
        $id     = base_convert($idB36, 36, 10);

        $order = PurchaseOrder::with(['supplier', 'items'])
            ->find($id);

        if (!$order) {
            throw new RuntimeException("Order not found for barcode: {$barcode}");
        }

        return ['type' => 'order', 'data' => $order];
    }

    private function resolveShipmentBarcode(string $barcode): array
    {
        // Format: SHIP-{TRACKING}-{id_b36}
        $inner  = substr($barcode, 5);                  // "FDX-ABC123-2B"
        $parts  = explode('-', $inner);
        $idB36  = array_pop($parts);                    // "2B"
        $id     = base_convert($idB36, 36, 10);

        $shipment = Shipment::with(['purchaseOrder.supplier', 'items'])
            ->find($id);

        if (!$shipment) {
            throw new RuntimeException("Shipment not found for barcode: {$barcode}");
        }

        return ['type' => 'shipment', 'data' => $shipment];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generate + persist barcodes on model creation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called from WarehouseItemObserver after creation.
     * Stores the barcode back on the model so it can be printed / queried.
     */
    public function assignItemBarcode(WarehouseItem $item): string
    {
        $barcode = $this->generateItemBarcode($item);
        $item->update(['barcode' => $barcode]);
        return $barcode;
    }

    public function assignOrderBarcode(PurchaseOrder $order): string
    {
        $barcode = $this->generateOrderBarcode($order);
        $order->update(['barcode' => $barcode]);
        return $barcode;
    }

    public function assignShipmentBarcode(Shipment $shipment): string
    {
        $barcode = $this->generateShipmentBarcode($shipment);
        $shipment->update(['barcode' => $barcode]);
        return $barcode;
    }
}