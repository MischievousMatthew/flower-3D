<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\WarehouseBatch;
use App\Models\WarehouseItem;
use App\Models\Warehouse;
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
            ->with('supplier:id,company_name,logo')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                if ($order->supplier) {
                    $supplier = $order->supplier;
                    $supplier->setAttribute(
                        'address',
                        $supplier->getAttribute('address')
                            ?? $supplier->getAttribute('location')
                            ?? null
                    );
                }

                return $order;
            });
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
            ->when($from, fn ($q) => $q->whereDate('shipped_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('shipped_date', '<=', $to));

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

        $deliveredShipments = (clone $query)
            ->where('status', 'delivered')
            ->whereNotNull('shipped_date')
            ->whereNotNull('received_date')
            ->get(['shipped_date', 'received_date']);

        $delivered = $deliveredShipments->count();
        $onTime = $deliveredShipments->filter(function ($shipment) {
            return $shipment->received_date->lte($shipment->shipped_date->copy()->addDays(30));
        })->count();

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
        $activeBatches = WarehouseBatch::query()
            ->with(['location.warehouse:id,name,location', 'product:id'])
            ->where('owner_id', $ownerId)
            ->where('status', 'active')
            ->where('qty_remaining', '>', 0)
            ->get();

        $totalSkus = (int) $activeBatches->unique('product_id')->count();
        $totalUnits = (int) $activeBatches->sum('qty_remaining');

        $floorSummary = [
            'fresh'          => 0,
            'aging'          => 0,
            'wilting'        => 0,
            'spoiled'        => 0,
            'expiring_today' => 0,
        ];

        foreach ($activeBatches as $batch) {
            $batchQuantity = (int) $batch->qty_remaining;
            $condition = $this->resolveBatchCondition($batch);
            if (isset($floorSummary[$condition])) {
                $floorSummary[$condition] += $batchQuantity;
            }

            $daysRemaining = $batch->expiration_date
                ? now()->startOfDay()->diffInDays($batch->expiration_date, false)
                : null;

            if ($daysRemaining !== null && $daysRemaining <= 1) {
                $floorSummary['expiring_today'] += $batchQuantity;
            }
        }

        $warehouseProductTotals = WarehouseBatch::query()
            ->join('warehouse_locations', 'warehouse_locations.id', '=', 'warehouse_batches.warehouse_location_id')
            ->join('products', 'products.id', '=', 'warehouse_batches.product_id')
            ->where('warehouse_batches.owner_id', $ownerId)
            ->where('warehouse_locations.owner_id', $ownerId)
            ->where('warehouse_batches.status', 'active')
            ->where('warehouse_batches.qty_remaining', '>', 0)
            ->whereNull('products.deleted_at')
            ->groupBy('warehouse_locations.warehouse_id', 'warehouse_batches.product_id')
            ->selectRaw('warehouse_locations.warehouse_id, warehouse_batches.product_id, SUM(warehouse_batches.qty_remaining) as total_qty');

        $skuCounts = DB::query()
            ->fromSub($warehouseProductTotals, 'warehouse_product_totals')
            ->selectRaw('warehouse_id, COUNT(*) as total_skus')
            ->groupBy('warehouse_id');

        $flowerTotals = DB::query()
            ->fromSub($warehouseProductTotals, 'warehouse_product_totals')
            ->selectRaw('warehouse_id, SUM(total_qty) as total_flowers')
            ->groupBy('warehouse_id');

        $byWarehouse = Warehouse::query()
            ->where('owner_id', $ownerId)
            ->leftJoinSub($skuCounts, 'sku_counts', fn ($join) => $join->on('sku_counts.warehouse_id', '=', 'warehouses.id'))
            ->leftJoinSub($flowerTotals, 'flower_totals', fn ($join) => $join->on('flower_totals.warehouse_id', '=', 'warehouses.id'))
            ->select('warehouses.id', 'warehouses.name', 'warehouses.location')
            ->selectRaw('COALESCE(sku_counts.total_skus, 0) as total_skus')
            ->selectRaw('COALESCE(flower_totals.total_flowers, 0) as total_flowers')
            ->orderBy('warehouses.name')
            ->get()
            ->map(fn ($wh) => [
                'warehouse_id'       => $wh->id,
                'warehouse_name'     => $wh->name,
                'warehouse_location' => $wh->location,
                'total_skus'         => (int) $wh->total_skus,
                'total_units'        => (int) $wh->total_flowers,
                'total_flowers'      => (int) $wh->total_flowers,
                'out_of_stock_count' => 0,
            ]);

        return [
            'total_skus'         => $totalSkus,
            'total_units'        => $totalUnits,
            'total_flowers'      => $totalUnits,
            'low_stock_items'    => collect(),
            'out_of_stock_items' => collect(),
            'floor_summary'      => $floorSummary,
            'by_warehouse'       => $byWarehouse,
        ];
    }

    private function resolveBatchCondition(WarehouseBatch $batch): string
    {
        if (! $batch->expiration_date) {
            return $batch->condition_status ?: 'fresh';
        }

        $daysRemaining = now()->startOfDay()->diffInDays($batch->expiration_date, false);

        if ($daysRemaining < 0) {
            return 'spoiled';
        }

        if ($batch->freshness_days) {
            $ratio = $daysRemaining / $batch->freshness_days;

            return match (true) {
                $ratio > 0.60 => 'fresh',
                $ratio > 0.40 => 'aging',
                $ratio > 0.20 => 'wilting',
                default => 'spoiled',
            };
        }

        return match (true) {
            $daysRemaining > 5 => 'fresh',
            $daysRemaining > 2 => 'aging',
            $daysRemaining > 0 => 'wilting',
            default => 'spoiled',
        };
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
