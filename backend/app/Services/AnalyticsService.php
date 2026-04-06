<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\WarehouseBatch;
use App\Models\WarehouseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    // ─── Orders ───────────────────────────────────────────────────────────────

    /**
     * High-level order totals broken down by status.
     *
     * @return array{total: int, total_value: float, by_status: array, avg_order_value: float, period: array}
     */
    public function totalOrders(int $ownerId, ?string $from = null, ?string $to = null): array
    {
        $query = PurchaseOrder::query()
            ->where('owner_id', $ownerId)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('created_at', '<=', $to));

        $total      = (clone $query)->count();
        $totalValue = (clone $query)->sum('total_amount');

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as value'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn ($row) => ['count' => (int) $row->count, 'value' => (float) $row->value]);

        return [
            'total'           => $total,
            'total_value'     => (float) $totalValue,
            'by_status'       => $byStatus->toArray(),
            'avg_order_value' => $total > 0 ? round($totalValue / $total, 2) : 0.0,
            'period'          => ['from' => $from, 'to' => $to],
        ];
    }

    /**
     * Fetch the most recent purchase orders for the dashboard list.
     *
     * @return Collection
     */
    public function recentOrders(int $ownerId, int $limit = 8): Collection
    {
        return PurchaseOrder::where('owner_id', $ownerId)
            ->with('supplier:id,company_name,address,logo_url')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // ─── Shipments ────────────────────────────────────────────────────────────

    /**
     * Shipment statistics grouped by status and carrier.
     *
     * @return array{total: int, by_status: array, by_carrier: array, on_time_rate: float, period: array}
     */
    public function totalShipments(int $ownerId, ?string $from = null, ?string $to = null): array
    {
        $query = Shipment::query()
            ->where('owner_id', $ownerId)
            ->when($from, fn ($q) => $q->whereDate('id', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('id', '<=', $to));

        $total = (clone $query)->count();

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($c) => (int) $c)
            ->toArray();

        $byCarrier = (clone $query)
            ->select('carrier', DB::raw('COUNT(*) as count'))
            ->groupBy('carrier')
            ->pluck('count', 'carrier')
            ->map(fn ($c) => (int) $c)
            ->toArray();

        $delivered  = (clone $query)->where('status', 'delivered')->count();
        $onTime     = (clone $query)
            ->where('status', 'delivered')
            ->whereRaw('received_date <= DATE_ADD(shipped_date, INTERVAL 30 DAY)')
            ->count();

        return [
            'total'        => $total,
            'by_status'    => $byStatus,
            'by_carrier'   => $byCarrier,
            'on_time_rate' => $delivered > 0 ? round(($onTime / $delivered) * 100, 2) : 0.0,
            'period'       => ['from' => $from, 'to' => $to],
        ];
    }

    // ─── Supplier Performance ─────────────────────────────────────────────────

    /**
     * Rank suppliers by order volume, GMV, and completion rate.
     *
     * @return Collection<int, array{supplier_id: int, company_name: string, status: string, total_orders: int, total_value: float, completed_orders: int, completion_rate: float}>
     */
    public function supplierPerformance(int $ownerId, ?string $from = null, ?string $to = null): Collection
    {
        return Supplier::where('owner_id', $ownerId)
        ->withCount([
            'purchaseOrders as total_orders',
            'purchaseOrders as completed_orders' => fn ($q) => $q->where('status', 'completed'),
        ])
        ->withSum('purchaseOrders as total_value', 'total_amount')
        ->get()
        ->map(fn ($supplier) => [
            'supplier_id'      => $supplier->id,
            'company_name'     => $supplier->company_name,
            'location'         => $supplier->address,
            'logo'             => $supplier->logo,
            'logo_url'         => $supplier->logo_url,
            'status'           => $supplier->status,
            'total_orders'     => (int)   $supplier->total_orders,
            'total_value'      => (float) $supplier->total_value,
            'total_gmv'        => (float) $supplier->total_value,
            'completed_orders' => (int)   $supplier->completed_orders,
            'completion_rate'  => $supplier->total_orders > 0
                ? round(($supplier->completed_orders / $supplier->total_orders) * 100, 2)
                : 0.0,
        ])
        ->sortByDesc('total_value')
        ->values();
    }

    // ─── Inventory ────────────────────────────────────────────────────────────

    /**
     * Stock summary per warehouse, with low-stock and out-of-stock alerts.
     *
     * @return array{total_skus: int, total_units: int, low_stock_items: Collection, out_of_stock_items: Collection, by_warehouse: Collection}
     */
    public function inventoryStockSummary(int $ownerId, int $lowStockThreshold = 10): array
    {
        $totalSkus  = WarehouseItem::where('owner_id', $ownerId)->count();
        
        // Sync with Floor View: Use WarehouseBatch qty_remaining for total units
        $totalUnits = WarehouseBatch::where('owner_id', $ownerId)
            ->where('status', '!=', 'depleted')
            ->sum('qty_remaining');

        $lowStock = WarehouseItem::with('warehouse')
            ->where('owner_id', $ownerId)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', $lowStockThreshold)
            ->orderBy('quantity')
            ->get()
            ->map(fn ($item) => [
                'item_id'      => $item->id,
                'sku'          => $item->sku,
                'product_name' => $item->product_name,
                'quantity'     => (int) $item->quantity,
                'warehouse'    => $item->warehouse?->name,
            ]);

        $outOfStock = WarehouseItem::with('warehouse')
            ->where('owner_id', $ownerId)
            ->where('quantity', '<=', 0)
            ->get()
            ->map(fn ($item) => [
                'item_id'      => $item->id,
                'sku'          => $item->sku,
                'product_name' => $item->product_name,
                'warehouse'    => $item->warehouse?->name,
            ]);

        $byWarehouse = WarehouseItem::select(
            'warehouse_id',
            DB::raw('COUNT(*) as total_skus'),
            DB::raw('SUM(quantity) as total_units'),
            DB::raw('SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count')
        )
        ->where('owner_id', $ownerId)
        ->with('warehouse:id,name,location')
        ->groupBy('warehouse_id')
        ->get()
        ->map(fn ($row) => [
            'warehouse_id'       => $row->warehouse_id,
            'warehouse_name'     => $row->warehouse?->name,
            'warehouse_location' => $row->warehouse?->location,
            'total_skus'         => (int) $row->total_skus,
            'total_units'        => (int) $row->total_units,
            'out_of_stock_count' => (int) $row->out_of_stock_count,
        ]);

        return [
            'total_skus'         => $totalSkus,
            'total_units'        => (int) $totalUnits,
            'low_stock_items'    => $lowStock,
            'out_of_stock_items' => $outOfStock,
            'by_warehouse'       => $byWarehouse,
        ];
    }

    /**
     * IN vs OUT movement summary over an optional date range.
     *
     * @return array{total_in: int, total_out: int, net: int, movements_by_day: Collection}
     */
    public function movementSummary(int $ownerId, ?string $from = null, ?string $to = null): array
    {
        $query = InventoryMovement::query()
            ->where('owner_id', $ownerId)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('created_at', '<=', $to));

        $totalIn  = (clone $query)->where('type', 'IN')->sum('quantity');
        $totalOut = (clone $query)->where('type', 'OUT')->sum('quantity');

        $byDay = (clone $query)
            ->select(DB::raw('DATE(created_at) as date'), 'type', DB::raw('SUM(quantity) as total'))
            ->groupBy(DB::raw('DATE(created_at)'), 'type')
            ->orderBy('date')
            ->get();

        return [
            'total_in'         => (int) $totalIn,
            'total_out'        => (int) $totalOut,
            'net'              => (int) ($totalIn - $totalOut),
            'movements_by_day' => $byDay,
        ];
    }
}
