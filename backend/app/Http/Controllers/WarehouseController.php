<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseBatch;
use App\Models\WarehouseLocation;
use App\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Traits\ScopesOwner;
 
class WarehouseController extends Controller
{
    use ScopesOwner;
 
    public function __construct(protected WarehouseService $service) {}




    // ── Warehouse CRUD ────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->listWarehouses($this->getOwnerId())]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->findWarehouse($id, $this->getOwnerId())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:255'],
            'manager'  => ['nullable', 'string', 'max:150'],
        ]);

        $warehouse = $this->service->addWarehouse($data, $this->getOwnerId());

        return response()->json(['success' => true, 'data' => $warehouse], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:150'],
            'location' => ['sometimes', 'string', 'max:255'],
            'manager'  => ['nullable', 'string', 'max:150'],
        ]);

        return response()->json(['success' => true, 'data' => $this->service->updateWarehouse($id, $data, $this->getOwnerId())]);
    }

    public function destroy(int $id): JsonResponse
    {
        Warehouse::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Warehouse deleted.']);
    }

    // ── Items = Vendor Catalog Products (read-only here) ──────────────────
    //
    // The Inventory System owns products. The Warehouse reads them
    // to let the SC pick what to physically place in storage.

    public function items(Request $request, int $warehouseId): JsonResponse
    {
        // Confirm the warehouse exists
        Warehouse::findOrFail($warehouseId);

        $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Products are owned by the authenticated vendor/owner
        $query = Product::with(['images' => fn ($q) => $q->where('is_primary', true)->limit(1)])
            ->where('owner_id', $this->getOwnerId())
            ->where('status', 'active')
            ->when(
                $request->search,
                fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('product_name', 'like', "%{$s}%")
                      ->orWhere('sku', 'like', "%{$s}%");
                })
            )
            ->orderBy('product_name');

        $products = $query->paginate($request->per_page ?? 20);

        // Attach each product's current batch count in THIS warehouse
        $products->getCollection()->transform(function ($product) use ($warehouseId) {
            $batches = WarehouseBatch::where('product_id', $product->id)
                ->where('status', 'active')
                ->whereHas('location', fn ($q) => $q->whereHas(
                    'warehouse',
                    fn ($q) => $q->where('id', $warehouseId)
                ))
                ->selectRaw('SUM(qty_remaining) as total_units, COUNT(*) as batch_count')
                ->first();

            return [
                'id'            => $product->id,
                'product_name'  => $product->product_name,
                'sku'           => $product->sku,
                'flower_type'   => $product->flower_type,
                'color'         => $product->color,
                'unit_price'    => $product->unit_price,
                'stock_count'   => $product->quantity_in_stock,   // on-paper inventory count
                'image_url'     => $product->images->first()?->image_url,
                // Warehouse read-only stats
                'warehouse_units_stored' => (int) ($batches->total_units ?? 0),
                'warehouse_batch_count'  => (int) ($batches->batch_count ?? 0),
            ];
        });

        return response()->json(['success' => true, 'data' => $products]);
    }

    // ── Add Item = Pick product from catalog, place it in a location ──────
    //
    // This is the bridge: SC picks a catalog product and assigns it
    // to a physical storage location, creating a WarehouseBatch.

    public function addItem(Request $request, int $warehouseId): JsonResponse
    {
        Warehouse::findOrFail($warehouseId);

        $data = $request->validate([
            'product_id'            => ['required', 'integer', 'exists:products,id'],
            'warehouse_location_id' => ['required', 'integer', 'exists:warehouse_locations,id'],
            'qty_received'          => ['required', 'integer', 'min:1'],
            'received_date'         => ['required', 'date'],
            'lot_number'            => ['nullable', 'string', 'max:100'],
            'harvest_date'          => ['nullable', 'date'],
            'expiration_date'       => ['nullable', 'date'],
            'freshness_days'        => ['nullable', 'integer', 'min:1'],
            'notes'                 => ['nullable', 'string'],
        ]);

        // Confirm the location actually belongs to this warehouse
        $location = WarehouseLocation::where('id', $data['warehouse_location_id'])
            ->where('is_active', true)
            ->firstOrFail();

        // Check capacity if set
        if ($location->capacity_units && $location->is_full) {
            return response()->json([
                'success' => false,
                'message' => "Location \"{$location->name}\" is at full capacity.",
            ], 422);
        }

        $product     = Product::findOrFail($data['product_id']);
        $batchNumber = WarehouseBatch::generateBatchNumber($product->sku, $data['received_date']);

        $batch = WarehouseBatch::create([
            'owner_id'              => $this->getOwnerId(),
            'product_id'            => $product->id,
            'warehouse_location_id' => $location->id,
            'storage_location'      => $location->name,
            'batch_number'          => $batchNumber,
            'barcode'               => 'WB-' . strtoupper(base_convert((string) time(), 10, 36)) . '-' . strtoupper(\Str::random(4)),
            'lot_number'            => $data['lot_number'] ?? null,
            'received_date'         => $data['received_date'],
            'harvest_date'          => $data['harvest_date'] ?? null,
            'expiration_date'       => $data['expiration_date'] ?? null,
            'freshness_days'        => $data['freshness_days'] ?? null,
            'qty_received'          => $data['qty_received'],
            'qty_remaining'         => $data['qty_received'],
            'condition_status'      => 'fresh',
            'status'                => 'active',
            'notes'                 => $data['notes'] ?? null,
        ]);

        // Log the placement
        $batch->logs()->create([
            'owner_id'     => $this->getOwnerId(),
            'performed_by' => Auth::id(),
            'event_type'   => 'QUANTITY_ADJUSTED',
            'qty_change'   => $data['qty_received'],
            'qty_after'    => $data['qty_received'],
            'to_location'  => $location->name,
            'notes'        => "Placed into warehouse from catalog — {$product->product_name}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Batch created and placed in {$location->name}.",
            'data'    => $batch->load('product', 'location'),
        ], 201);
    }

    // ── Remaining WarehouseService-backed methods ─────────────────────────

    public function updateItem(Request $request, int $warehouseId, int $id): JsonResponse
    {
        $data = $request->validate([
            'product_name' => ['sometimes', 'string'],
            'sku'          => ['sometimes', 'string'],
            'barcode'      => ['nullable', 'string'],
        ]);

        return response()->json(['success' => true, 'data' => $this->service->updateItem($id, $data)]);
    }

    public function adjustStock(Request $request, int $warehouseId, int $id): JsonResponse
    {
        $data = $request->validate([
            'quantity'  => ['required', 'integer'],
            'reference' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->updateStock($id, $data['quantity'], $data['reference'] ?? ''),
        ]);
    }

    public function movements(Request $request, int $warehouseId): JsonResponse
    {
        $filters = $request->only(['type', 'per_page']);

        return response()->json([
            'success' => true,
            'data'    => $this->service->trackWarehouseMovements($warehouseId, $filters),
        ]);
    }

    public function barcodes(int $warehouseId): JsonResponse
    {
        $items = \App\Models\WarehouseItem::where('warehouse_id', $warehouseId)
            ->whereNotNull('barcode')
            ->get(['id', 'product_name', 'sku', 'barcode']);

        return response()->json(['success' => true, 'data' => $items]);
    }
}
