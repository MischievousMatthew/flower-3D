<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use App\Traits\ScopesOwner;
 
class AnalyticsController extends Controller
{
    use ScopesOwner;
 
    public function __construct(private readonly AnalyticsService $service) {}




    // ─── GET /analytics/summary ───────────────────────────────────────────────
    // Combined KPI snapshot: orders + shipments + inventory in one call.
    // Query: ?from=2024-01-01&to=2024-12-31
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $filters['from'] ?? null;
        $to   = $filters['to']   ?? null;

        $ownerId = $this->getOwnerId();

        return response()->json([
            'orders'    => $this->service->totalOrders($ownerId, $from, $to),
            'shipments' => $this->service->totalShipments($ownerId, $from, $to),
            'inventory' => $this->service->inventoryStockSummary($ownerId),
            'movements' => $this->service->movementSummary($ownerId, $from, $to),
        ]);
    }

    // ─── GET /analytics/inventory ─────────────────────────────────────────────
    // Stock levels, low-stock and out-of-stock alerts per warehouse.
    // Query: ?low_stock_threshold=10
    public function inventory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'low_stock_threshold' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->service->inventoryStockSummary($this->getOwnerId(), $data['low_stock_threshold'] ?? 10)
        );
    }

    // ─── GET /analytics/orders ────────────────────────────────────────────────
    // Order totals, GMV, and status breakdown.
    // Query: ?from=2024-01-01&to=2024-12-31
    public function orders(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(
            $this->service->totalOrders($this->getOwnerId(), $filters['from'] ?? null, $filters['to'] ?? null)
        );
    }

    // ─── GET /analytics/shipments ─────────────────────────────────────────────
    // Shipment counts, on-time delivery rate, and carrier split.
    // Query: ?from=2024-01-01&to=2024-12-31
    public function shipments(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(
            $this->service->totalShipments($this->getOwnerId(), $filters['from'] ?? null, $filters['to'] ?? null)
        );
    }

    // ─── GET /analytics/suppliers ─────────────────────────────────────────────
    // Supplier performance: order volume, GMV, and completion rates.
    // Query: ?from=2024-01-01&to=2024-12-31
    public function supplierPerformance(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(
            $this->service->supplierPerformance($this->getOwnerId(), $filters['from'] ?? null, $filters['to'] ?? null)
        );
    }

    // ─── GET /analytics/movements ─────────────────────────────────────────────
    // Inventory IN vs OUT totals and daily movement breakdown.
    // Query: ?from=2024-01-01&to=2024-12-31
    public function movements(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(
            $this->service->movementSummary($this->resolveOwnerId(), $filters['from'] ?? null, $filters['to'] ?? null)
        );
    }
}