<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\WarehouseBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

use App\Traits\ScopesOwner;
 
class WarehouseBatchController extends Controller
{
    use ScopesOwner;
 
    public function __construct(private readonly WarehouseBatchService $service) {}




    // ── Floor view & listing ───────────────────────────────────────────────

    public function floorView(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'condition'   => ['nullable', 'string', Rule::in(['fresh', 'aging', 'wilting', 'spoiled'])],
            'search'      => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($this->service->floorView($this->getOwnerId(), $filters));
    }

    public function byProduct(int $productId): JsonResponse
    {
        return response()->json($this->service->batchesForProduct($productId, $this->getOwnerId()));
    }

    public function logs(int $batchId): JsonResponse
    {
        return response()->json($this->service->batchLogs($batchId, $this->getOwnerId()));
    }

    // ── Scanner ────────────────────────────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $request->validate(['barcode' => ['required', 'string']]);

        $result = $this->service->scanBatch($request->barcode, $this->getOwnerId());

        return response()->json($result, $result['found'] ? 200 : 404);
    }

    // ── Write operations ───────────────────────────────────────────────────

    /**
     * POST /warehouse/batches
     * Receive a new batch.
     *
     * ADDED: accepts optional source_order_id. When provided and the batch
     * is saved successfully, the linked purchase order is automatically
     * transitioned to "completed" (received → completed).
     */
    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'            => ['required', 'integer', 'exists:products,id'],
            'qty_received'          => ['required', 'integer', 'min:1'],
            'received_date'         => ['required', 'date'],
            'harvest_date'          => ['nullable', 'date'],
            'expiration_date'       => ['nullable', 'date', 'after:today'],
            'freshness_days'        => ['nullable', 'integer', 'min:1', 'max:365'],
            'warehouse_location_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouse_locations', 'id')->where(
                    fn ($query) => $query->where('owner_id', $this->getOwnerId())
                ),
            ],
            'lot_number'            => ['nullable', 'string', 'max:100'],
            'notes'                 => ['nullable', 'string', 'max:500'],
            // ADDED: optional link back to the purchase order
            'source_order_id'       => ['nullable', 'integer', 'exists:purchase_orders,id'],
        ]);

        $sourceOrderId = $data['source_order_id'] ?? null;
        unset($data['source_order_id']); // don't pass to service — it doesn't expect it

        try {
            $batch = $this->service->receiveBatch($data, $this->getOwnerId());
        } catch (Throwable $e) {
            Log::error('Batch creation failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'user_id' => auth()->id(),
                'owner_id' => $this->getOwnerId(),
            ]);

            throw $e;
        }

        // Auto-complete the purchase order when a batch is received from it
        if ($sourceOrderId) {
            $order = PurchaseOrder::find($sourceOrderId);

            if ($order && in_array($order->status, ['received', 'shipped', 'processing', 'pending'], true)) {
                $order->update(['status' => 'completed']);
            }
        }

        return response()->json($batch, 201);
    }

    public function updateCondition(Request $request, int $batchId): JsonResponse
    {
        $data = $request->validate([
            'condition' => ['required', 'string', Rule::in(['fresh', 'aging', 'wilting', 'spoiled', 'discarded'])],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->service->updateCondition($batchId, $data['condition'], $this->getOwnerId(), $data['notes'] ?? null));
    }

    public function cull(Request $request, int $batchId): JsonResponse
    {
        $data = $request->validate([
            'qty'   => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->service->cullBatch($batchId, $data['qty'], $this->getOwnerId(), $data['notes'] ?? null));
    }

    public function transfer(Request $request, int $batchId): JsonResponse
    {
        $data = $request->validate([
            'warehouse_location_id' => [
                'required',
                'integer',
                Rule::exists('warehouse_locations', 'id')->where(
                    fn ($query) => $query->where('owner_id', $this->getOwnerId())
                ),
            ],
            'notes'                 => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->service->transferBatch($batchId, $data['warehouse_location_id'], $this->getOwnerId(), $data['notes'] ?? null));
    }
}
