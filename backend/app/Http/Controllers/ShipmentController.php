<?php

namespace App\Http\Controllers;

use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

use App\Traits\ScopesOwner;
 
class ShipmentController extends Controller
{
    use ScopesOwner;
 
    public function __construct(private readonly ShipmentService $service) {}




    /** GET /shipments */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status'   => ['nullable', 'string', Rule::in(['pending', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'])],
            'carrier'  => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->service->listShipments($this->getOwnerId(), $filters));
    }

    /** GET /shipments/{id} */
    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->findShipment($id, $this->getOwnerId()));
    }

    /** POST /shipments */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purchase_order_id'           => ['required', 'integer', 'exists:purchase_orders,id'],
            'carrier'                     => ['required', 'string', 'max:255'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.warehouse_item_id'   => ['required', 'integer', 'exists:warehouse_items,id'],
            'items.*.quantity'            => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($this->service->createShipment($data, $this->getOwnerId()), 201);
    }

    /** PATCH /shipments/{id}/tracking */
    public function updateTracking(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'tracking_number' => ['sometimes', 'string', 'max:255'],
            'carrier'         => ['sometimes', 'string', 'max:255'],
        ]);

        return response()->json($this->service->updateTracking($id, $data, $this->getOwnerId()));
    }

    /** PATCH /shipments/{id}/ship */
    public function markShipped(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'shipped_date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->service->markShipped($id, $this->getOwnerId(), $data['shipped_date'] ?? null));
    }

    /** PATCH /shipments/{id}/receive */
    public function markReceived(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'received_date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->service->markReceived($id, $this->getOwnerId(), $data['received_date'] ?? null));
    }

    /** PATCH /shipments/{id}/status */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'])],
        ]);

        return response()->json($this->service->updateStatus($id, $data['status'], $this->resolveOwnerId()));
    }
}