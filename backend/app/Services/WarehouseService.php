<?php

namespace App\Services;

use App\Models\Warehouse;
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
        return Warehouse::where('owner_id', $ownerId)->withCount('items')->get();
    }

    public function findWarehouse(int $id, int $ownerId): Warehouse
    {
        return Warehouse::where('owner_id', $ownerId)->with('items')->findOrFail($id);
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