<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductTaxonomy;
use App\Models\VendorPromotion;
use App\Traits\ResolvesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A vendor's seasonal offers (see VendorPromotion for how prices follow them). */
class VendorPromotionController extends Controller
{
    use ResolvesOwner;

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $products = $this->products($ownerId);

        $promotions = VendorPromotion::query()
            ->where('owner_id', $ownerId)
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (VendorPromotion $p) => $this->format($p, $products))
            ->sortBy(fn ($p) => array_search($p['state'], ['running', 'scheduled', 'paused', 'ended'], true))
            ->values();

        return response()->json(['success' => true, 'data' => $promotions]);
    }

    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $promotion = VendorPromotion::create(['owner_id' => $ownerId, 'is_active' => true] + $this->validated($request, $ownerId));
        VendorPromotion::forgetCache($ownerId);

        return response()->json([
            'success' => true,
            'message' => 'Promotion created.',
            'data' => $this->format($promotion, $this->products($ownerId)),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $promotion = $this->find($ownerId, $id);
        $promotion->update($this->validated($request, $ownerId));
        VendorPromotion::forgetCache($ownerId);

        return response()->json([
            'success' => true,
            'message' => 'Promotion saved.',
            'data' => $this->format($promotion->fresh(), $this->products($ownerId)),
        ]);
    }

    /** Pause or resume without losing the dates. */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $promotion = $this->find($ownerId, $id);
        $promotion->update(['is_active' => ! $promotion->is_active]);
        VendorPromotion::forgetCache($ownerId);

        return response()->json([
            'success' => true,
            'message' => $promotion->is_active ? 'Promotion resumed.' : 'Promotion paused.',
            'data' => $this->format($promotion, $this->products($ownerId)),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $this->find($ownerId, $id)->delete();
        VendorPromotion::forgetCache($ownerId);

        return response()->json(['success' => true, 'message' => 'Promotion deleted.']);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function validated(Request $request, int $ownerId): array
    {
        $data = $request->validate([
            'name' => 'required|string|min:3|max:120',
            'discount_type' => ['required', Rule::in([VendorPromotion::PERCENT, VendorPromotion::FIXED])],
            'discount_value' => 'required|numeric|min:1|max:100000',
            'applies_to' => ['required', Rule::in(VendorPromotion::APPLIES_TO)],
            'targets' => 'nullable|array|max:200',
            'targets.*' => 'string|max:100',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
        ], [
            'ends_at.after' => 'The promotion must end after it starts.',
        ]);

        if ($data['discount_type'] === VendorPromotion::PERCENT && $data['discount_value'] > 90) {
            throw ValidationException::withMessages(['discount_value' => 'A percentage discount can be at most 90%.']);
        }

        $targets = array_values(array_unique($data['targets'] ?? []));
        $allowed = match ($data['applies_to']) {
            'category' => ProductTaxonomy::activeValues(ProductTaxonomy::CATEGORY),
            'occasion' => ProductTaxonomy::activeValues(ProductTaxonomy::OCCASION),
            'products' => $this->products($ownerId)->pluck('id')->map(fn ($id) => (string) $id)->all(),
            default => null,
        };
        if ($allowed !== null) {
            $targets = array_values(array_intersect(array_map('strval', $targets), $allowed));
            if (! $targets) {
                throw ValidationException::withMessages(['targets' => 'Choose at least one ' . match ($data['applies_to']) {
                    'category' => 'category',
                    'occasion' => 'occasion',
                    default => 'product',
                } . '.']);
            }
        }
        $data['targets'] = $allowed === null ? null : $targets;

        return $data;
    }

    private function find(int $ownerId, int $id): VendorPromotion
    {
        return VendorPromotion::query()->where('owner_id', $ownerId)->findOrFail($id);
    }

    private function products(int $ownerId)
    {
        return Product::withoutGlobalScopes()->whereNull('deleted_at')->where('owner_id', $ownerId)
            ->get(['id', 'product_name', 'category', 'occasion_tags', 'selling_price', 'status', 'owner_id']);
    }

    private function format(VendorPromotion $promotion, $products): array
    {
        $covered = $products->filter(fn (Product $p) => $promotion->covers($p));
        $live = $covered->where('status', 'active');
        $prices = $live->map(fn (Product $p) => (float) $p->getRawOriginal('selling_price'));

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'discount_type' => $promotion->discount_type,
            'discount_value' => (float) $promotion->discount_value,
            'applies_to' => $promotion->applies_to,
            'targets' => $promotion->targets ?? [],
            'starts_at' => $promotion->starts_at->toIso8601String(),
            'ends_at' => $promotion->ends_at->toIso8601String(),
            'is_active' => $promotion->is_active,
            'state' => $promotion->state(),
            'product_count' => $live->count(),
            'product_names' => $live->pluck('product_name')->take(4)->values(),
            // Fixed amounts larger than a product's price don't apply to it
            'skipped' => $prices->filter(fn ($price) => $promotion->priceFor($price) === null)->count(),
        ];
    }
}
