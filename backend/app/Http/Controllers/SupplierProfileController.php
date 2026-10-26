<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierProfileService;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A supplier's profile page: its catalog and prices, and the shop's ratings.
 * The supplier's own details still go through SupplierController.
 */
class SupplierProfileController extends Controller
{
    use ScopesOwner;

    public function __construct(private readonly SupplierProfileService $profiles) {}

    /** GET /suppliers/{id}/profile */
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->profiles->profile($this->supplier($id))]);
    }

    // ─── Catalog ──────────────────────────────────────────────────────────────

    /** POST /suppliers/{id}/products */
    public function storeProduct(Request $request, int $id): JsonResponse
    {
        $supplier = $this->supplier($id);
        $data = $request->validate($this->productRules());

        $item = $supplier->products()->create($data + ['owner_id' => $supplier->owner_id, 'price_updated_at' => now()]);

        return response()->json(['data' => $item], 201);
    }

    /** PUT /suppliers/{id}/products/{productId} */
    public function updateProduct(Request $request, int $id, int $productId): JsonResponse
    {
        $item = $this->supplier($id)->products()->findOrFail($productId);
        $data = $request->validate($this->productRules());

        if (isset($data['price']) && (float) $data['price'] !== (float) $item->price) {
            $data['price_updated_at'] = now();
        }
        $item->update($data);

        return response()->json(['data' => $item->fresh()]);
    }

    /** DELETE /suppliers/{id}/products/{productId} */
    public function destroyProduct(int $id, int $productId): JsonResponse
    {
        $this->supplier($id)->products()->findOrFail($productId)->delete();

        return response()->json(['message' => 'Removed from the catalog.']);
    }

    // ─── Ratings ──────────────────────────────────────────────────────────────

    /** POST /suppliers/{id}/ratings */
    public function storeRating(Request $request, int $id): JsonResponse
    {
        $supplier = $this->supplier($id);
        $data = $request->validate([
            'quality'           => ['required', 'integer', 'between:1,5'],
            'timeliness'        => ['required', 'integer', 'between:1,5'],
            'communication'     => ['required', 'integer', 'between:1,5'],
            'comment'           => ['nullable', 'string', 'max:1000'],
            'purchase_order_id' => ['nullable', 'integer'],
        ]);

        // Only one of this supplier's own orders can be linked
        if (! empty($data['purchase_order_id'])
            && ! PurchaseOrder::where('owner_id', $supplier->owner_id)->where('supplier_id', $supplier->id)->whereKey($data['purchase_order_id'])->exists()) {
            return response()->json(['message' => 'Pick one of this supplier\'s orders.', 'errors' => ['purchase_order_id' => ['Pick one of this supplier\'s orders.']]], 422);
        }

        return response()->json(['data' => $this->profiles->rate($supplier, $data, $request->user())], 201);
    }

    /** DELETE /suppliers/{id}/ratings/{ratingId} */
    public function destroyRating(int $id, int $ratingId): JsonResponse
    {
        $this->supplier($id)->ratings()->findOrFail($ratingId)->delete();

        return response()->json(['message' => 'Rating removed.']);
    }

    private function supplier(int $id): Supplier
    {
        return Supplier::where('owner_id', $this->getOwnerId())->findOrFail($id);
    }

    private function productRules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:160'],
            'category'     => ['nullable', Rule::in(SupplierProduct::CATEGORIES)],
            'unit'         => ['required', Rule::in(SupplierProduct::UNITS)],
            'price'        => ['required', 'numeric', 'min:0', 'max:9999999'],
            'min_qty'      => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_available' => ['sometimes', 'boolean'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
