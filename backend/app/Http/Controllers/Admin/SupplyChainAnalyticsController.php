<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use App\Traits\ScopesOwner;

class SupplyChainAnalyticsController extends Controller
{
    use ScopesOwner;

    public function __construct(private readonly AnalyticsService $service) {}

    /**
     * Get high-level summary of orders and shipments
     */
    public function summary(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $from = $filters['from'] ?? null;
        $to   = $filters['to']   ?? null;
        $ownerId = $this->getOwnerId();

        // 1. Orders breakdown
        $orders = $this->service->totalOrders($ownerId, $from, $to);
        
        // 2. Shipments breakdown
        $shipments = $this->service->totalShipments($ownerId, $from, $to);

        // 3. Recent activity for Live Overview (Synced with real SC Orders)
        $recentOrders = $this->service->recentOrders($ownerId, 10);

        return response()->json([
            'orders'          => $orders,
            'shipments'       => $shipments,
            'recent_orders'   => $recentOrders,
            'recent_shipments'=> $recentOrders, // Fallback for components looking for shipments
        ]);
    }

    /**
     * Get real-time warehouse inventory metrics
     */
    public function inventory(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $inventory = $this->service->inventoryStockSummary($ownerId);

        return response()->json($inventory);
    }

    /**
     * Calculate supplier performance metrics based on POs and GMV
     */
    public function supplierPerformance(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $performance = $this->service->supplierPerformance($ownerId);

        return response()->json([
            'data' => $performance
        ]);
    }
}
