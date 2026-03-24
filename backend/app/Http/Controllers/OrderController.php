<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

use App\Traits\ScopesOwner;
 
class OrderController extends Controller
{
    use ScopesOwner;
 
    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status'      => ['nullable', 'string', Rule::in(['pending', 'processing', 'shipped', 'received', 'completed'])],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->service->listOrders($this->getOwnerId(), $filters));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->findOrder($id, $this->getOwnerId()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'          => ['required', 'integer', 'exists:suppliers,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.price'        => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->service->createOrder($data, $this->getOwnerId()), 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'processing', 'shipped', 'received', 'completed'])],
        ]);

        return response()->json($this->service->updateOrderStatus($id, $data['status'], $this->getOwnerId()));
    }

    public function attachItems(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.price'        => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->service->attachItems($id, $data['items'], $this->getOwnerId()));
    }

    public function removeItem(int $id, int $itemId): JsonResponse
    {
        return response()->json($this->service->removeItem($id, $itemId, $this->getOwnerId()));
    }

    public function recalculateTotals(int $id): JsonResponse
    {
        return response()->json($this->service->calculateTotals($id, $this->getOwnerId()));
    }

    public function approvedFundingRequests(Request $request): JsonResponse
    {
        $ownerId = $request->user()->owner_id;
        return response()->json($this->service->getApprovedFundingRequests($ownerId));
    }

    public function createFromFunding(Request $request, int $fundingRequestId): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        return response()->json(
            $this->service->createOrderFromFunding($fundingRequestId, $data['supplier_id'], $this->getOwnerId()),
            201
        );
    }

    public function statusCounts(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();

        $counts = PurchaseOrder::where('owner_id', $ownerId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

            \Log::info('statusCounts user: ' . json_encode([
                'user' => $request->user(),
                'owner_id' => $request->user()?->owner_id,
            ]));

        return response()->json([
            'data' => [
                'pending'    => $counts['pending']    ?? 0,
                'processing' => $counts['processing'] ?? 0,
                'shipped'    => $counts['shipped']    ?? 0,
                'received'   => $counts['received']   ?? 0,
                'completed'  => $counts['completed']  ?? 0,
            ]
        ]);
    }
}