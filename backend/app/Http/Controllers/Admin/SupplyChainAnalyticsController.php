<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\WarehouseItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplyChainAnalyticsController extends Controller
{
    /**
     * Get high-level summary of orders and shipments
     */
    public function summary(Request $request)
    {
        // 1. Orders breakdown
        $orders = PurchaseOrder::query();
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
        $shipments = Shipment::query();
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

        // 3. Recent shipments for Live Overview
        $recentShipments = Shipment::with('purchaseOrder.supplier')
                                   ->orderBy('id', 'desc')
                                   ->take(10)
                                   ->get();

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
            'recent_shipments' => $recentShipments,
        ]);
    }

    /**
     * Get real-time warehouse inventory metrics
     */
    public function inventory(Request $request)
    {
        // For actual inventory, we look at WarehouseItem quantities
        $skuCount = WarehouseItem::distinct('sku')->count('sku');
        $totalUnits = WarehouseItem::sum('quantity');

        // Low stock: Assume low stock is strictly below 15 for demo
        $lowStockItems = WarehouseItem::with('warehouse')
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 15)
            ->take(10)
            ->get();

        $outOfStockItems = WarehouseItem::with('warehouse')
            ->where('quantity', '<=', 0)
            ->take(10)
            ->get();

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
            ])
        ]);
    }

    /**
     * Calculate supplier performance metrics based on POs and GMV
     */
    public function supplierPerformance(Request $request)
    {
        // Supplier efficiency: GMV = sum of PO totals, Completion = (completed POs / total POs)
        $suppliers = Supplier::with('purchaseOrders')->get();

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
