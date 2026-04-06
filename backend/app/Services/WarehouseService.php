<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\WarehouseBatch;
use App\Models\WarehouseItem;
use App\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    // ─── Warehouses ───────────────────────────────────────────────────────────

    public function listWarehouses(int $ownerId): Collection
    {
        $warehouseProductTotals = WarehouseBatch::query()
            ->join('warehouse_locations', 'warehouse_locations.id', '=', 'warehouse_batches.warehouse_location_id')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_locations.warehouse_id')
            ->join('products', 'products.id', '=', 'warehouse_batches.product_id')
            ->where('warehouse_batches.owner_id', $ownerId)
            ->where('warehouses.owner_id', $ownerId)
            ->where('warehouse_batches.status', 'active')
            ->where('warehouse_batches.qty_remaining', '>', 0)
            ->whereNull('products.deleted_at')
            ->groupBy('warehouse_locations.warehouse_id', 'warehouse_batches.product_id')
            ->selectRaw('warehouse_locations.warehouse_id, warehouse_batches.product_id, SUM(warehouse_batches.qty_remaining) as total_qty');

        $skuCounts = DB::query()
            ->fromSub($warehouseProductTotals, 'warehouse_product_totals')
            ->selectRaw('warehouse_id, COUNT(*) as items_count')
            ->groupBy('warehouse_id');

        $lowStockCounts = DB::query()
            ->fromSub($warehouseProductTotals, 'warehouse_product_totals')
            ->selectRaw('warehouse_id, SUM(CASE WHEN total_qty <= 10 THEN 1 ELSE 0 END) as low_stock')
            ->groupBy('warehouse_id');

        return Warehouse::query()
            ->where('owner_id', $ownerId)
            ->withCount('locations as storages_count')
            ->leftJoinSub($skuCounts, 'sku_counts', fn ($join) => $join->on('sku_counts.warehouse_id', '=', 'warehouses.id'))
            ->leftJoinSub(
                DB::query()
                    ->fromSub($warehouseProductTotals, 'warehouse_product_totals')
                    ->selectRaw('warehouse_id, SUM(total_qty) as total_flowers')
                    ->groupBy('warehouse_id'),
                'flower_totals',
                fn ($join) => $join->on('flower_totals.warehouse_id', '=', 'warehouses.id')
            )
            ->leftJoinSub($lowStockCounts, 'low_stock_counts', fn ($join) => $join->on('low_stock_counts.warehouse_id', '=', 'warehouses.id'))
            ->select('warehouses.*')
            ->selectRaw('COALESCE(sku_counts.items_count, 0) as items_count')
            ->selectRaw('COALESCE(flower_totals.total_flowers, 0) as total_flowers')
            ->selectRaw('COALESCE(low_stock_counts.low_stock, 0) as low_stock')
            ->orderBy('warehouses.name')
            ->get()
            ->each(function ($warehouse) {
                $warehouse->storages_count = (int) ($warehouse->storages_count ?? 0);
                $warehouse->items_count = (int) ($warehouse->items_count ?? 0);
                $warehouse->low_stock = (int) ($warehouse->low_stock ?? 0);
                $warehouse->total_flowers = (int) ($warehouse->total_flowers ?? 0);
                $warehouse->total_units = $warehouse->total_flowers;
            });
    }

    public function findWarehouse(int $id, int $ownerId): Warehouse
    {
        $warehouse = Warehouse::query()
            ->where('owner_id', $ownerId)
            ->with('items', 'locations')
            ->withCount('locations as storages_count')
            ->findOrFail($id);

        $warehouse->total_flowers = (int) WarehouseBatch::query()
            ->join('warehouse_locations', 'warehouse_locations.id', '=', 'warehouse_batches.warehouse_location_id')
            ->where('warehouse_locations.warehouse_id', $warehouse->id)
            ->where('warehouse_batches.owner_id', $ownerId)
            ->where('warehouse_batches.status', 'active')
            ->where('warehouse_batches.qty_remaining', '>', 0)
            ->sum('warehouse_batches.qty_remaining');

        $warehouse->storages_count = (int) ($warehouse->storages_count ?? 0);
        $warehouse->total_units = $warehouse->total_flowers;

        return $warehouse;
    }

    /** @param array{name: string, location: string, manager: string} $data */
    public function addWarehouse(array $data, int $ownerId): Warehouse
    {
        $data['owner_id'] = $ownerId;
        return Warehouse::create($data);
    }

    /** @param array<string, mixed> $data */
    public function updateWarehouse(int $id, array $data, int $ownerId): Warehouse
    {
        $warehouse = Warehouse::where('owner_id', $ownerId)->findOrFail($id);
        $warehouse->update($data);
        return $warehouse;
    }

    // ─── Warehouse Items ──────────────────────────────────────────────────────

    /** @param array{search?: string, per_page?: int} $filters */
    public function listItems(int $warehouseId, array $filters = []): LengthAwarePaginator
    {
        return WarehouseItem::where('warehouse_id', $warehouseId)
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($q) use ($filters) {
                    $q->where('product_name', 'like', "%{$filters['search']}%")
                      ->orWhere('sku', 'like', "%{$filters['search']}%")
                      ->orWhere('barcode', 'like', "%{$filters['search']}%");
                })
            )
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Add a new item to a warehouse and record initial stock movement.
     * @param array{product_name: string, sku: string, quantity: int, barcode?: string} $data
     */
    public function addItemToWarehouse(int $warehouseId, array $data): WarehouseItem
    {
        Warehouse::findOrFail($warehouseId);

        $data['warehouse_id'] = $warehouseId;

        return DB::transaction(function () use ($data) {
            $item = WarehouseItem::create($data);

            if ($item->quantity > 0) {
                $item->inventoryMovements()->create([
                    'type'      => 'IN',
                    'quantity'  => $item->quantity,
                    'reference' => 'INITIAL_STOCK',
                ]);
            }

            return $item;
        });
    }

    /**
     * Update warehouse item metadata (product_name, sku, barcode — not quantity).
     * @param array<string, mixed> $data
     */
    public function updateItem(int $itemId, array $data): WarehouseItem
    {
        $item = WarehouseItem::findOrFail($itemId);
        $item->update($data);
        return $item->fresh();
    }

    /**
     * Adjust stock: positive = IN movement, negative = OUT movement.
     * @throws \RuntimeException on insufficient stock for OUT.
     */
    public function updateStock(int $itemId, int $quantity, string $reference = ''): WarehouseItem
    {
        return DB::transaction(function () use ($itemId, $quantity, $reference) {
            $item = WarehouseItem::lockForUpdate()->findOrFail($itemId);

            if ($quantity > 0) {
                $item->receiveStock($quantity, $reference ?: null);
            } elseif ($quantity < 0) {
                $item->dispatchStock(abs($quantity), $reference ?: null);
            }

            return $item->fresh();
        });
    }

    /**
     * Global inventory summary, optionally scoped to one warehouse.
     *
     * @return array{total_skus: int, total_units: int, low_stock_items: Collection, out_of_stock_items: Collection}
     */
    public function inventorySummary(?int $warehouseId = null, int $lowStockThreshold = 10): array
    {
        $query = WarehouseItem::query()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->with('warehouse:id,name,location');

        $totalSkus  = (clone $query)->count();
        $totalUnits = (clone $query)->sum('quantity');

        $lowStock = (clone $query)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', $lowStockThreshold)
            ->orderBy('quantity')
            ->get()
            ->map(fn ($item) => [
                'item_id'      => $item->id,
                'sku'          => $item->sku,
                'product_name' => $item->product_name,
                'quantity'     => $item->quantity,
                'warehouse'    => $item->warehouse?->name,
            ]);

        $outOfStock = (clone $query)
            ->where('quantity', '<=', 0)
            ->get()
            ->map(fn ($item) => [
                'item_id'      => $item->id,
                'sku'          => $item->sku,
                'product_name' => $item->product_name,
                'warehouse'    => $item->warehouse?->name,
            ]);

        return [
            'total_skus'         => $totalSkus,
            'total_units'        => $totalUnits,
            'low_stock_items'    => $lowStock,
            'out_of_stock_items' => $outOfStock,
        ];
    }

    // ─── Movements ────────────────────────────────────────────────────────────

    /** @param array{type?: 'IN'|'OUT', per_page?: int} $filters */
    public function trackMovements(int $itemId, array $filters = []): LengthAwarePaginator
    {
        WarehouseItem::findOrFail($itemId);

        return InventoryMovement::where('warehouse_item_id', $itemId)
            ->when(
                isset($filters['type']),
                fn ($q) => $q->where('type', strtoupper($filters['type']))
            )
            ->latest('created_at')
            ->paginate($filters['per_page'] ?? 20);
    }

    /** @param array{type?: string, per_page?: int} $filters */
    public function trackWarehouseMovements(int $warehouseId, array $filters = []): LengthAwarePaginator
    {
        Warehouse::findOrFail($warehouseId);

        $itemIds = WarehouseItem::where('warehouse_id', $warehouseId)->pluck('id');

        return InventoryMovement::whereIn('warehouse_item_id', $itemIds)
            ->when(
                isset($filters['type']),
                fn ($q) => $q->where('type', strtoupper($filters['type']))
            )
            ->with('warehouseItem')
            ->latest('created_at')
            ->paginate($filters['per_page'] ?? 20);
    }
}
