<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\WarehouseItem;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SupplyChainAnalyticsController extends Controller
{
    private function resolveOwnerId(): ?int
    {
        $authUser = Auth::user();

        if ($authUser instanceof Employee) {
            return $authUser->owner_id;
        }

        if ($authUser && property_exists($authUser, 'owner_id') && $authUser->owner_id) {
            return $authUser->owner_id;
        }

        return $authUser?->id;
    }

    /**
     * Get high-level summary of orders and shipments
     */
    public function summary(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId();

        // 1. Orders breakdown
        $orders = PurchaseOrder::query()->where('owner_id', $ownerId);
        if ($request->has('from')) {
            $orders->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $orders->whereDate('created_at', '<=', $request->to);
        }
        
        $totalOrders = $orders->count();
        $ordersByStatus = $orders->select('status', DB::raw('count(*) as count'))
                                 ->groupBy('status')
                                 ->pluck('count', 'status')
                                 ->toArray();

        // 2. Shipments breakdown
        $shipments = Shipment::query()->where('owner_id', $ownerId);
        if ($request->has('from')) {
            $shipments->whereDate('shipped_date', '>=', $request->from);
        }
        if ($request->has('to')) {
            $shipments->whereDate('shipped_date', '<=', $request->to);
        }

        $totalShipments = $shipments->count();
        $shipmentsByStatus = $shipments->select('status', DB::raw('count(*) as count'))
                                       ->groupBy('status')
                                       ->pluck('count', 'status')
                                       ->toArray();

        // 3. Recent purchase orders for the dashboard timeline
        $recentOrders = PurchaseOrder::query()
            ->where('owner_id', $ownerId)
            ->with('supplier')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'created_at' => $order->created_at?->toIso8601String(),
                'supplier' => $order->supplier ? [
                    'id' => $order->supplier->id,
                    'company_name' => $order->supplier->company_name,
                    'address' => $order->supplier->address,
                    'logo_url' => $order->supplier->logo_url,
                ] : null,
            ]);

        $supplierCount = Supplier::query()
            ->where('owner_id', $ownerId)
            ->count();

        $warehouseCount = Warehouse::query()
            ->where('owner_id', $ownerId)
            ->count();

        return response()->json([
            'orders' => [
                'total' => $totalOrders,
                'by_status' => [
                    'pending' => $ordersByStatus['pending'] ?? 0,
                    'processing' => $ordersByStatus['processing'] ?? 0,
                    'shipped' => $ordersByStatus['shipped'] ?? 0,
                    'received' => $ordersByStatus['received'] ?? 0,
                    'completed' => $ordersByStatus['completed'] ?? 0,
                ],
            ],
            'shipments' => [
                'total' => $totalShipments,
                'by_status' => [
                    'pending' => $shipmentsByStatus['pending'] ?? 0,
                    'in_transit' => $shipmentsByStatus['in_transit'] ?? 0,
                    'out_for_delivery' => $shipmentsByStatus['out_for_delivery'] ?? 0,
                    'delivered' => $shipmentsByStatus['delivered'] ?? 0,
                ],
            ],
            'suppliers' => [
                'total' => $supplierCount,
            ],
            'warehouses' => [
                'total' => $warehouseCount,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * Get real-time warehouse inventory metrics
     */
    public function inventory(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId();

        // For actual inventory, we look at WarehouseItem quantities
        $warehouseItems = WarehouseItem::query()->where('owner_id', $ownerId);
        $skuCount = (clone $warehouseItems)->distinct('sku')->count('sku');
        $totalUnits = (clone $warehouseItems)->sum('quantity');

        // Low stock: Assume low stock is strictly below 15 for demo
        $lowStockItems = WarehouseItem::with('warehouse')
            ->where('owner_id', $ownerId)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 15)
            ->take(10)
            ->get();

        $outOfStockItems = WarehouseItem::with('warehouse')
            ->where('owner_id', $ownerId)
            ->where('quantity', '<=', 0)
            ->take(10)
            ->get();

        $warehouseSummary = Warehouse::query()
            ->where('owner_id', $ownerId)
            ->withCount(['items as total_skus'])
            ->withSum('items as total_units', 'quantity')
            ->withCount([
                'items as out_of_stock_count' => fn ($query) => $query->where('quantity', '<=', 0),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($warehouse) => [
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'warehouse_location' => $warehouse->location,
                'total_skus' => (int) ($warehouse->total_skus ?? 0),
                'total_units' => (int) ($warehouse->total_units ?? 0),
                'out_of_stock_count' => (int) ($warehouse->out_of_stock_count ?? 0),
            ]);

        return response()->json([
            'total_skus' => $skuCount,
            'total_units' => $totalUnits,
            'low_stock_items' => $lowStockItems->map(fn($item) => [
                'item_id' => $item->id,
                'sku' => $item->sku ?? 'UNKNOWN',
                'product_name' => $item->product_name ?? 'Unknown Product',
                'quantity' => $item->quantity,
                'warehouse' => $item->warehouse->name ?? 'Main DC'
            ]),
            'out_of_stock_items' => $outOfStockItems->map(fn($item) => [
                'item_id' => $item->id,
                'sku' => $item->sku ?? 'UNKNOWN',
                'product_name' => $item->product_name ?? 'Unknown Product',
                'warehouse' => $item->warehouse->name ?? 'Main DC'
            ]),
            'by_warehouse' => $warehouseSummary,
        ]);
    }

    /**
     * Calculate supplier performance metrics based on POs and GMV
     */
    public function supplierPerformance(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId();

        // Supplier efficiency: GMV = sum of PO totals, Completion = (completed POs / total POs)
        $suppliers = Supplier::query()
            ->where('owner_id', $ownerId)
            ->with('purchaseOrders')
            ->get();

        $performance = $suppliers->map(function ($supplier) {
            $totalOrders = $supplier->purchaseOrders->count();
            $completedOrders = $supplier->purchaseOrders->where('status', 'completed')->count();
            
            // GMV definition: total amount of orders that are not cancelled
            $gmv = $supplier->purchaseOrders->whereIn('status', ['completed', 'shipped', 'processing'])->sum('total_amount');
            
            $completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;

            return [
                'supplier_id' => $supplier->id,
                'company_name' => $supplier->company_name ?? 'Unknown',
                'location' => $supplier->address ?? 'N/A',
                'logo' => $supplier->logo,
                'logo_url' => $supplier->logo_url,
                'total_orders' => $totalOrders,
                'total_gmv' => (float) $gmv,
                'completion_rate' => $completionRate,
            ];
        })->sortByDesc('total_gmv')->values()->take(10);

        return response()->json([
            'data' => $performance
        ]);
    }
}
