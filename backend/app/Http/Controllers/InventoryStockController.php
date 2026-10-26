<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStockAdjustment;
use App\Services\InventoryStockService;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Inventory & stock replenishment for the ERP, on the same stock count the
 * shop and the warehouse use (products.quantity_in_stock).
 */
class InventoryStockController extends Controller
{
    use ScopesOwner;

    public function __construct(private readonly InventoryStockService $stock) {}

    /** GET /procurement/inventory/stock */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->stock->overview($this->getOwnerId()) + [
            'reasons' => collect(ProductStockAdjustment::REASONS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]]);
    }

    /** GET /procurement/inventory/stock/history */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter'     => ['nullable', Rule::in(['damaged'])],
            'product_id' => ['nullable', 'integer'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(['data' => $this->stock->history($this->getOwnerId(), $data['filter'] ?? null, $data['product_id'] ?? null, $data['limit'] ?? 30)]);
    }

    /** PATCH /procurement/inventory/stock/{id}/levels */
    public function levels(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'min_stock_level'    => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'max_stock_level'    => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'stock_kind'         => ['sometimes', 'nullable', Rule::in(InventoryStockService::KINDS)],
            'stock_unit'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'supplier_lead_time' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:180'],
        ]);
        $product = $this->product($id);
        $min = $data['min_stock_level'] ?? (int) $product->min_stock_level;
        if (isset($data['max_stock_level']) && $data['max_stock_level'] < $min) {
            return response()->json(['message' => 'The target has to be at least the minimum.', 'errors' => ['max_stock_level' => ['The target has to be at least the minimum.']]], 422);
        }

        return response()->json(['data' => $this->stock->setLevels($product, $data), 'message' => 'Stock levels saved.']);
    }

    /** POST /procurement/inventory/stock/{id}/adjust */
    public function adjust(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'mode'     => ['required', Rule::in(['add', 'remove', 'set'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason'   => ['required', Rule::in(array_keys(ProductStockAdjustment::REASONS))],
            'note'     => ['nullable', 'string', 'max:500'],
        ], [
            'reason.required' => 'Pick why the stock changed.',
        ]);
        if ($data['reason'] === 'damaged' && $data['mode'] === 'add') {
            return response()->json(['message' => 'Damaged stock is taken away, not added. Use "Take away" for damaged items.'], 422);
        }

        try {
            $result = $this->stock->adjust($this->product($id), $data['mode'], $data['quantity'], $data['reason'], $data['note'] ?? null, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result, 'message' => 'Stock updated.']);
    }

    /** POST /procurement/inventory/stock/materials */
    public function storeMaterial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'stock_kind'         => ['required', Rule::in(array_diff(InventoryStockService::KINDS, ['flowers']))],
            'sku'                => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'stock_unit'         => ['nullable', 'string', 'max:20'],
            'unit_cost'          => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'on_hand'            => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'min_stock_level'    => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_stock_level'    => ['nullable', 'integer', 'min:0', 'max:1000000', 'gte:min_stock_level'],
            'supplier_lead_time' => ['nullable', 'integer', 'min:0', 'max:180'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ], [
            'stock_kind.in'          => 'Flowers are added as products. Materials are wrapping, ribbons, vases, greenery or other supplies.',
            'sku.unique'             => 'Another product already uses that SKU.',
            'max_stock_level.gte'    => 'The target has to be at least the minimum.',
        ]);

        return response()->json(['data' => $this->stock->createMaterial($this->getOwnerId(), $data, $request->user()), 'message' => 'Material added.'], 201);
    }

    private function product(int $id): Product
    {
        return Product::where('owner_id', $this->getOwnerId())->findOrFail($id);
    }
}
