<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStockAdjustment;
use App\Models\ProductTaxonomy;
use App\Services\ProductModerationService;
use App\Traits\ResolvesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The vendor portal's Shop & product management screens: the product list,
 * inventory (stock changes with reasons), categories & occasions in bulk,
 * and each product's own scheduled discount.
 */
class VendorCatalogController extends Controller
{
    use ResolvesOwner;

    private const STATUSES = ['active', 'draft', 'inactive', 'discontinued'];
    private const MAX_OCCASIONS = 2;

    /** GET /vendor/catalog/products — every product the shop has, with what each screen needs. */
    public function products(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);

        $products = $this->query($ownerId)
            ->with(['images', 'models:id,product_id'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Product $p) => $this->format($p))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $products,
            'reasons' => collect(ProductStockAdjustment::REASONS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }

    /** POST /vendor/catalog/products/bulk — archive, restore, or file several products at once. */
    public function bulk(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
            'action' => 'required|in:archive,restore,set_category,add_occasion,remove_occasion',
            'value' => 'nullable|string|max:100',
        ]);

        $value = $data['value'] ?? null;
        if ($data['action'] === 'set_category' && ! in_array($value, ProductTaxonomy::activeValues(ProductTaxonomy::CATEGORY), true)) {
            return $this->fail('Pick one of the categories.');
        }
        if (in_array($data['action'], ['add_occasion', 'remove_occasion'], true)
            && ! in_array($value, ProductTaxonomy::activeValues(ProductTaxonomy::OCCASION), true)) {
            return $this->fail('Pick one of the occasions.');
        }

        $products = $this->query($ownerId)->whereIn('id', $data['ids'])->get();
        if ($products->isEmpty()) {
            return $this->fail('Those products were not found.', 404);
        }

        $moderation = app(ProductModerationService::class);
        $changed = 0;
        $full = 0;

        DB::transaction(function () use ($products, $data, $value, $moderation, &$changed, &$full) {
            foreach ($products as $product) {
                $tags = array_values((array) ($product->occasion_tags ?? []));
                // A product can carry at most MAX_OCCASIONS occasions (same rule as the product form)
                if ($data['action'] === 'add_occasion' && ! in_array($value, $tags, true) && count($tags) >= self::MAX_OCCASIONS) {
                    $full++;
                    continue;
                }
                $updates = match ($data['action']) {
                    'archive' => $product->status === 'inactive' ? [] : ['status' => 'inactive'],
                    'restore' => $product->status === 'active' ? [] : ['status' => 'active'],
                    'set_category' => $product->category === $value ? [] : ['category' => $value],
                    'add_occasion' => in_array($value, $tags, true) ? [] : ['occasion_tags' => [...$tags, $value]],
                    'remove_occasion' => in_array($value, $tags, true)
                        ? ['occasion_tags' => array_values(array_diff($tags, [$value]))]
                        : [],
                };
                if (! $updates) {
                    continue;
                }

                $product->update($updates);
                if (! in_array($data['action'], ['archive', 'restore'], true)) {
                    $moderation->markEdited($product);
                }
                $changed++;
            }
        });

        $noun = $changed === 1 ? 'product' : 'products';
        $message = $changed ? "Updated {$changed} {$noun}." : 'Nothing needed changing.';
        if ($full) {
            $message .= ' ' . $full . ($full === 1 ? ' product already has' : ' products already have')
                . ' ' . self::MAX_OCCASIONS . ' occasions, so ' . ($full === 1 ? 'it was' : 'they were') . ' skipped.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'changed' => $changed,
            'skipped' => $full,
        ]);
    }

    /** POST /vendor/catalog/products/{id}/stock — add, remove or set stock, with a reason. */
    public function adjustStock(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'mode' => 'required|in:add,remove,set',
            'quantity' => 'required|integer|min:0|max:1000000',
            'reason' => ['required', Rule::in(array_keys(ProductStockAdjustment::REASONS))],
            'note' => 'nullable|string|max:500',
        ]);

        $actor = $request->user();
        $result = DB::transaction(function () use ($ownerId, $id, $data, $actor) {
            $product = $this->query($ownerId)->whereKey($id)->lockForUpdate()->first();
            if (! $product) {
                return null;
            }

            $before = (int) $product->quantity_in_stock;
            $after = match ($data['mode']) {
                'add' => $before + $data['quantity'],
                'remove' => $before - $data['quantity'],
                'set' => $data['quantity'],
            };
            if ($after < 0) {
                return ['error' => "Only {$before} in stock, so you can't remove {$data['quantity']}."];
            }
            if ($after === $before) {
                return ['error' => 'That leaves the stock unchanged.'];
            }

            $product->update(['quantity_in_stock' => $after]);
            $adjustment = ProductStockAdjustment::create([
                'owner_id' => $ownerId,
                'product_id' => $product->id,
                'change' => $after - $before,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'actor_name' => trim(($actor->name ?? '') . ' ' . ($actor->surname ?? '')) ?: ($actor->full_name ?? null),
            ]);

            return ['product' => $product->fresh(['images', 'models:id,product_id']), 'adjustment' => $adjustment];
        });

        if ($result === null) {
            return $this->fail('Product not found.', 404);
        }
        if (isset($result['error'])) {
            return $this->fail($result['error']);
        }

        $result['adjustment']->setRelation('product', $result['product']);

        return response()->json([
            'success' => true,
            'message' => 'Stock updated.',
            'data' => $this->format($result['product']),
            'adjustment' => $this->formatAdjustment($result['adjustment']),
        ]);
    }

    /** PATCH /vendor/catalog/products/{id}/stock-levels — when to warn about low stock. */
    public function stockLevels(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'min_stock_level' => 'required|integer|min:0|max:1000000',
            'max_stock_level' => 'nullable|integer|gte:min_stock_level|max:1000000',
        ]);

        $product = $this->query($ownerId)->whereKey($id)->first();
        if (! $product) {
            return $this->fail('Product not found.', 404);
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Stock alert levels saved.',
            'data' => $this->format($product->fresh(['images', 'models:id,product_id'])),
        ]);
    }

    /** GET /vendor/catalog/stock-history — recent manual stock changes, newest first. */
    public function stockHistory(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'product_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $rows = ProductStockAdjustment::query()
            ->where('owner_id', $ownerId)
            ->when($data['product_id'] ?? null, fn ($q, $pid) => $q->where('product_id', $pid))
            ->with('product.images')
            ->latest('created_at')
            ->latest('id')
            ->limit($data['limit'] ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($a) => $this->formatAdjustment($a))->values(),
        ]);
    }

    /** PATCH /vendor/catalog/products/{id}/discount — the product's own sale price and its dates. */
    public function discount(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $product = $this->query($ownerId)->whereKey($id)->first();
        if (! $product) {
            return $this->fail('Product not found.', 404);
        }

        $price = (float) $product->getRawOriginal('selling_price');
        $data = $request->validate([
            'discount_price' => "nullable|numeric|min:0.01|lt:{$price}",
            'starts_at' => 'nullable|date',
            'ends_at' => ['nullable', 'date', $request->filled('starts_at') ? 'after:starts_at' : 'after:now'],
        ], [
            'discount_price.lt' => 'The sale price must be lower than the regular price.',
            'ends_at.after' => $request->filled('starts_at') ? 'The sale must end after it starts.' : 'The end date has already passed.',
        ]);

        $on = isset($data['discount_price']);
        $product->update([
            'has_discount' => $on,
            'discount_price' => $on ? round((float) $data['discount_price'], 2) : null,
            'discount_starts_at' => $on ? ($data['starts_at'] ?? null) : null,
            'discount_ends_at' => $on ? ($data['ends_at'] ?? null) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $on ? 'Sale price saved.' : 'Sale price removed.',
            'data' => $this->format($product->fresh(['images', 'models:id,product_id'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function query(int $ownerId)
    {
        return Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('owner_id', $ownerId);
    }

    private function format(Product $p): array
    {
        $price = (float) $p->getRawOriginal('selling_price');
        $own = $p->getRawOriginal('discount_price');
        $sale = $p->discount_price;                      // what customers pay now (own or promotion)
        $promotion = $p->runningPromotion();
        $usesPromotion = $promotion && $sale !== null && abs($promotion['price'] - (float) $sale) < 0.005
            && ($p->ownDiscountNow() === null || $promotion['price'] < $p->ownDiscountNow());
        $image = $p->images->firstWhere('is_primary', true) ?? $p->images->first();
        $stock = (int) $p->quantity_in_stock;
        $min = $p->min_stock_level !== null ? (int) $p->min_stock_level : null;

        return [
            'id' => $p->id,
            'name' => $p->product_name,
            'sku' => $p->sku,
            'description' => $p->description,
            'category' => $p->category,
            'flower_type' => $p->flower_type,
            'color' => $p->color === 'other' ? ($p->color_other ?: 'Other') : $p->color,
            'selling_type' => $p->selling_type,
            'occasion_tags' => array_values((array) ($p->occasion_tags ?? [])),
            'price' => $price,
            'purchase_price' => $p->getRawOriginal('purchase_price') !== null ? (float) $p->getRawOriginal('purchase_price') : null,
            'own_discount' => $own !== null ? (float) $own : null,
            'discount_starts_at' => $p->discount_starts_at?->toIso8601String(),
            'discount_ends_at' => $p->discount_ends_at?->toIso8601String(),
            'sale_price' => $sale !== null ? (float) $sale : null,
            'sale_source' => $sale === null ? null : ($usesPromotion ? 'promotion' : 'own'),
            'promotion_name' => $usesPromotion ? $promotion['promotion']->name : null,
            'stock' => $stock,
            'min_stock' => $min,
            'max_stock' => $p->max_stock_level !== null ? (int) $p->max_stock_level : null,
            'stock_state' => $stock <= 0 ? 'out' : ($min !== null && $min > 0 && $stock <= $min ? 'low' : 'ok'),
            'status' => in_array($p->status, self::STATUSES, true) ? $p->status : 'active',
            'moderation_status' => $p->moderation_status,
            'moderation_reason' => $p->moderation_reason,
            'image' => $image?->image_url,
            'image_count' => $p->images->count(),
            'has_model' => $p->models->isNotEmpty(),
            'stock_value' => round($stock * (float) ($p->getRawOriginal('purchase_price') ?? 0), 2),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    private function formatAdjustment(ProductStockAdjustment $a): array
    {
        $product = $a->product;
        $image = $product?->images?->firstWhere('is_primary', true) ?? $product?->images?->first();

        return [
            'key' => md5('adj' . $a->id),
            'product_id' => $a->product_id,
            'product_name' => $product?->product_name ?? 'Deleted product',
            'product_image' => $image?->image_url,
            'change' => $a->change,
            'before' => $a->quantity_before,
            'after' => $a->quantity_after,
            'reason' => $a->reason,
            'reason_label' => ProductStockAdjustment::REASONS[$a->reason] ?? ucfirst($a->reason),
            'note' => $a->note,
            'by' => $a->actor_name,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
