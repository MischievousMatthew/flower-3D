<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;

class SupplyChainAnalyticsController extends Controller
{
    use ScopesOwner;

    public function __construct(private readonly AnalyticsService $service) {}

    /**
     * Get high-level summary of orders and shipments for the Dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $from = $filters['from'] ?? null;
        $to   = $filters['to']   ?? null;

        return response()->json([
            'orders'          => $this->service->totalOrders($ownerId, $from, $to),
            'shipments'       => $this->service->totalShipments($ownerId, $from, $to),
            'recent_orders'   => $this->service->recentOrders($ownerId, 10),
            'recent_shipments'=> $this->service->recentOrders($ownerId, 10), // Shared data for dashboard components
            'suppliers'       => [
                'total' => \App\Models\Supplier::where('owner_id', $ownerId)->count()
            ],
            'warehouses'      => [
                'total' => \App\Models\Warehouse::where('owner_id', $ownerId)->count()
            ]
        ]);
    }

    /**
     * Get real-time warehouse inventory metrics (Aggregated from storage/batches)
     */
    public function inventory(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $inventory = $this->service->inventoryStockSummary($ownerId);

        return response()->json($inventory);
    }

    /**
     * Calculate supplier performance metrics based on POs and GMV
     */
    public function supplierPerformance(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $performance = $this->service->supplierPerformance($ownerId);

        return response()->json([
            'data' => $performance
        ]);
    }
}
