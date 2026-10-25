<?php

namespace App\Http\Controllers\Admin;

use App\Services\PlatformNotices;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductModerationEvent;
use App\Models\ProductReport;
use App\Models\ProductTaxonomy;
use App\Models\User;
use App\Services\ProductModerationService;
use App\Support\PaymentLedger;
use App\Support\VendorDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Admin oversight of every vendor's listings.
 *
 * Admins moderate whether customers can see a listing (approve, hide,
 * remove, restore) and manage the category / flower type / occasion lists.
 * They never edit a vendor's product details, price, stock or own status.
 */
class AdminProductController extends Controller
{
    public function __construct(private readonly ProductModerationService $moderation)
    {
    }

    /**
     * GET /api/admin/products/summary
     */
    public function summary(): JsonResponse
    {
        $byModeration = Product::query()->selectRaw('moderation_status, count(*) as total')->groupBy('moderation_status')->pluck('total', 'moderation_status');

        return response()->json([
            'success' => true,
            'data' => [
                'all' => (int) $byModeration->sum(),
                'review' => (int) (($byModeration[Product::MODERATION_PENDING] ?? 0) + ($byModeration[Product::MODERATION_RESUBMITTED] ?? 0)),
                'live' => $this->live(Product::query())->count(),
                'hidden' => (int) (($byModeration[Product::MODERATION_HIDDEN] ?? 0) + ($byModeration[Product::MODERATION_RESUBMITTED] ?? 0)),
                'removed' => (int) ($byModeration[Product::MODERATION_REMOVED] ?? 0),
                'reported' => Product::query()->whereHas('reports', fn ($q) => $q->where('status', 'pending'))->count(),
                'out_of_stock' => $this->live(Product::query())->where('quantity_in_stock', '<=', 0)->count(),
                'low_stock' => $this->live(Product::query())->where('quantity_in_stock', '>', 0)->whereColumn('quantity_in_stock', '<=', 'min_stock_level')->count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('display_order')])
            ->withCount(['reports as open_reports' => fn ($q) => $q->where('status', 'pending')]);

        match ($request->input('tab')) {
            'review' => $query->whereIn('moderation_status', [Product::MODERATION_PENDING, Product::MODERATION_RESUBMITTED]),
            'live' => $this->live($query),
            'hidden' => $query->whereIn('moderation_status', [Product::MODERATION_HIDDEN, Product::MODERATION_RESUBMITTED]),
            'removed' => $query->where('moderation_status', Product::MODERATION_REMOVED),
            'reported' => $query->whereHas('reports', fn ($q) => $q->where('status', 'pending')),
            'out_of_stock' => $this->live($query)->where('quantity_in_stock', '<=', 0),
            'low_stock' => $this->live($query)->where('quantity_in_stock', '>', 0)->whereColumn('quantity_in_stock', '<=', 'min_stock_level'),
            default => null,
        };

        foreach (['category', 'flower_type', 'selling_type'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('vendor_id')) {
            $query->where('owner_id', (int) $request->input('vendor_id'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $owners = User::query()->whereRaw('lower(name) like ?', [$like])->pluck('id')->merge(VendorDirectory::idsMatching($search));
            $query->where(fn ($q) => $q
                ->whereRaw('lower(product_name) like ?', [$like])
                ->orWhereRaw('lower(sku) like ?', [$like])
                ->orWhereIn('owner_id', $owners));
        }

        match ($request->input('sort')) {
            'oldest' => $query->orderBy('created_at'),
            'name' => $query->orderBy('product_name'),
            'price_high' => $query->orderByDesc('selling_price'),
            'stock_low' => $query->orderBy('quantity_in_stock'),
            'reports' => $query->orderByDesc('open_reports'),
            default => $query->orderByDesc('created_at'),
        };

        $page = $query->orderByDesc('id')->paginate(min(max((int) $request->input('per_page', 12), 6), 60));
        $products = $page->getCollection();
        $vendors = VendorDirectory::details($products->pluck('owner_id'));
        $suspended = User::query()->whereIn('id', $products->pluck('owner_id'))->where('is_suspended', true)->pluck('id')->flip();
        $sold = $this->soldCounts($products->pluck('id'));
        $labels = $this->taxonomyLabels();

        return response()->json([
            'success' => true,
            'data' => $products->map(fn (Product $p) => $this->formatRow($p, $vendors, $suspended, $sold, $labels))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::query()
            ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('display_order'), 'models'])
            ->withCount(['reports as open_reports' => fn ($q) => $q->where('status', 'pending')])
            ->find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $vendors = VendorDirectory::details([$product->owner_id]);
        $suspended = User::query()->whereKey($product->owner_id)->where('is_suspended', true)->pluck('id')->flip();
        $labels = $this->taxonomyLabels();
        $row = $this->formatRow($product, $vendors, $suspended, $this->soldCounts(collect([$product->id])), $labels);

        $reviews = DB::table('product_reviews')->where('product_id', $product->id)->whereNull('hidden_at');
        $reports = ProductReport::query()->with('reporter:id,name,surname')->where('product_id', $product->id)->latest()->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $row + [
                'description' => $product->description,
                'images' => $product->images->pluck('image_url')->filter()->values(),
                'has_3d_model' => $product->models->isNotEmpty(),
                'color' => $product->color === 'other' ? $product->color_other : $product->color,
                'season' => $product->season,
                'care_instructions' => $product->care_instructions,
                'occasions' => collect((array) $product->occasion_tags)->map(fn ($o) => $labels['occasion'][$o] ?? $o)->values(),
                'min_stock_level' => (int) $product->min_stock_level,
                'is_fragile' => (bool) $product->is_fragile,
                'requires_refrigeration' => (bool) $product->requires_refrigeration,
                'preparation_days' => $product->preparation_days,
                'reviews' => [
                    'count' => (clone $reviews)->count(),
                    'average' => ($avg = (clone $reviews)->avg('rating')) ? round((float) $avg, 1) : null,
                    'recent' => (clone $reviews)->latest()->limit(5)->get(['rating', 'comment', 'created_at'])
                        ->map(fn ($r) => ['rating' => (int) $r->rating, 'comment' => $r->comment, 'created_at' => $r->created_at]),
                ],
                'reports' => $reports->map(fn ($r) => [
                    'id' => $r->id,
                    'reason' => $r->reason,
                    'description' => $r->description,
                    'status' => $r->status,
                    'reporter' => PaymentLedger::personName($r->reporter),
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
                'history' => ProductModerationEvent::query()->with('admin:id,name,surname')
                    ->where('product_id', $product->id)->latest()->orderByDesc('id')->get()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'action' => $e->action,
                        'reason' => $e->reason,
                        'by' => $e->action === 'resubmitted' ? 'Vendor' : ($e->admin ? PaymentLedger::personName($e->admin) : 'Automatic'),
                        'at' => $e->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    /**
     * POST /api/admin/products/{id}/{action}  (approve | hide | remove | restore)
     */
    public function moderate(Request $request, int $id, string $action, PlatformNotices $notices): JsonResponse
    {
        $needsReason = in_array($action, ['hide', 'remove'], true);
        $data = $request->validate([
            'reason' => [$needsReason ? 'required' : 'nullable', 'string', $needsReason ? 'min:5' : 'min:0', 'max:1000'],
        ]);

        $product = Product::query()->find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $reason = isset($data['reason']) ? trim($data['reason']) : null;
        try {
            match ($action) {
                'approve' => $this->moderation->approve($product, $request->user(), $reason),
                'hide' => $this->moderation->hide($product, $request->user(), $reason),
                'remove' => $this->moderation->remove($product, $request->user(), $reason),
                'restore' => $this->moderation->restore($product, $request->user(), $reason),
            };
        } catch (LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $name = $product->product_name ?: 'your product';
        $why = $reason ? "Reason: {$reason}" : null;
        match ($action) {
            'approve' => $notices->toUser($product->owner_id, "Your listing was approved: {$name}", 'It is now showing on your shop.', '/vendor/products', false),
            'hide' => $notices->toUser($product->owner_id, "BloomCraft hid your listing: {$name}", trim(($why ? "{$why}. " : '') . 'Customers can\'t see it for now. Fix it and send it back for review from Products.'), '/vendor/products'),
            'remove' => $notices->toUser($product->owner_id, "BloomCraft removed your listing: {$name}", trim(($why ? "{$why}. " : '') . 'It is no longer on your shop. Contact support if you think this is a mistake.'), '/vendor/products'),
            'restore' => $notices->toUser($product->owner_id, "Your listing is back on your shop: {$name}", $why, '/vendor/products', false),
        };

        $messages = [
            'approve' => 'Listing approved.',
            'hide' => 'Listing hidden. The vendor can fix it and send it back for review.',
            'remove' => 'Listing removed from the shop.',
            'restore' => 'Listing is back on the shop.',
        ];

        return response()->json(['success' => true, 'message' => $messages[$action] . ' The shop was notified.']);
    }

    // ── Categories, flower types, occasions ──────────────────────────────

    /**
     * GET /api/admin/taxonomies
     */
    public function taxonomies(): JsonResponse
    {
        $products = Product::query()->get(['category', 'flower_type', 'occasion_tags']);
        $usage = [
            ProductTaxonomy::CATEGORY => $products->countBy('category'),
            ProductTaxonomy::FLOWER_TYPE => $products->countBy('flower_type'),
            ProductTaxonomy::OCCASION => $products->flatMap(fn ($p) => (array) $p->occasion_tags)->countBy(),
        ];

        $items = ProductTaxonomy::query()->orderBy('type')->orderBy('sort_order')->orderBy('label')->get()
            ->map(fn ($t) => $t->only(['id', 'type', 'value', 'label', 'description', 'is_active', 'sort_order']) + [
                'products' => (int) ($usage[$t->type][$t->value] ?? 0),
            ]);

        // Values products use that aren't on the lists (e.g. from before the lists existed)
        $known = $items->groupBy('type')->map(fn ($rows) => $rows->pluck('value')->all());
        $unlisted = collect($usage)->map(fn ($counts, $type) => collect($counts)
            ->reject(fn ($count, $value) => $value === '' || in_array($value, $known[$type] ?? [], true))
            ->map(fn ($count, $value) => ['value' => $value, 'products' => $count])->values());

        return response()->json(['success' => true, 'data' => ['items' => $items->groupBy('type'), 'unlisted' => $unlisted]]);
    }

    /**
     * POST /api/admin/taxonomies
     */
    public function storeTaxonomy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ProductTaxonomy::TYPES)],
            'label' => 'required|string|max:120',
            'value' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:255',
        ]);

        // Occasions store their label as-is (as products always have); the
        // others store a slug. An unlisted value can be adopted by passing it.
        $value = trim($data['value'] ?? '') ?: ($data['type'] === ProductTaxonomy::OCCASION ? trim($data['label']) : Str::slug($data['label']));
        if ($value === '') {
            return response()->json(['success' => false, 'errors' => ['label' => ['Enter a name.']]], 422);
        }
        if (ProductTaxonomy::query()->where('type', $data['type'])->where('value', $value)->exists()) {
            return response()->json(['success' => false, 'errors' => ['label' => ['That one is already on the list.']]], 422);
        }

        $item = ProductTaxonomy::create([
            'type' => $data['type'],
            'value' => $value,
            'label' => trim($data['label']),
            'description' => isset($data['description']) ? trim($data['description']) ?: null : null,
            'is_active' => true,
            'sort_order' => (int) ProductTaxonomy::query()->where('type', $data['type'])->max('sort_order') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Added.'], 201);
    }

    /**
     * PUT /api/admin/taxonomies/{id}
     */
    public function updateTaxonomy(Request $request, int $id): JsonResponse
    {
        $item = ProductTaxonomy::query()->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'label' => 'sometimes|required|string|max:120',
            'description' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);
        $item->update($data);

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Saved.']);
    }

    /**
     * POST /api/admin/taxonomies/reorder  { type, ids: [] }
     */
    public function reorderTaxonomies(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ProductTaxonomy::TYPES)],
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $index => $id) {
                ProductTaxonomy::query()->where('type', $data['type'])->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/admin/taxonomies/{id}  (only when no product uses it)
     */
    public function destroyTaxonomy(int $id): JsonResponse
    {
        $item = ProductTaxonomy::query()->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $inUse = match ($item->type) {
            ProductTaxonomy::OCCASION => Product::query()->get(['occasion_tags'])->contains(fn ($p) => in_array($item->value, (array) $p->occasion_tags, true)),
            default => Product::query()->where($item->type, $item->value)->exists(),
        };
        if ($inUse) {
            return response()->json(['success' => false, 'message' => 'Products still use this, so it can only be switched off.'], 422);
        }

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Products customers can actually buy right now */
    private function live(Builder $query): Builder
    {
        return $query->listedForCustomers()->where('status', 'active');
    }

    private function formatRow(Product $p, array $vendors, Collection $suspended, Collection $sold, array $labels): array
    {
        $vendorOff = $p->status !== 'active';
        $storeSuspended = isset($suspended[$p->owner_id]);
        $visibility = match (true) {
            $p->moderation_status === Product::MODERATION_REMOVED => 'removed',
            $p->moderation_status === Product::MODERATION_RESUBMITTED => 'resubmitted',
            $p->moderation_status === Product::MODERATION_HIDDEN => 'hidden',
            $storeSuspended => 'store_suspended',
            $vendorOff => 'vendor_off',
            default => 'live',
        };

        $price = (float) $p->selling_price;
        $sale = $p->has_discount && $p->discount_price ? (float) $p->discount_price : null;

        return [
            'id' => $p->id,
            'name' => $p->product_name,
            'sku' => $p->sku,
            'image' => $p->images->first()?->image_url,
            'category' => $p->category,
            'category_label' => $labels['category'][$p->category] ?? Str::headline((string) $p->category),
            'flower_type' => $p->flower_type,
            'flower_type_label' => $labels['flower_type'][$p->flower_type] ?? Str::headline((string) $p->flower_type),
            'selling_type' => $p->selling_type,
            'price' => $price,
            'sale_price' => $sale,
            'stock' => (int) $p->quantity_in_stock,
            'stock_state' => $p->quantity_in_stock <= 0 ? 'out' : ($p->min_stock_level !== null && $p->quantity_in_stock <= $p->min_stock_level ? 'low' : 'ok'),
            'vendor_status' => $p->status,
            'moderation_status' => $p->moderation_status,
            'moderation_reason' => $p->moderation_reason,
            'moderated_at' => $p->moderated_at?->toIso8601String(),
            'visibility' => $visibility,
            'open_reports' => (int) ($p->open_reports ?? 0),
            'sold' => (int) ($sold[$p->id] ?? 0),
            'vendor' => $vendors[$p->owner_id] ?? VendorDirectory::unknown($p->owner_id),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    private function soldCounts(Collection $productIds): Collection
    {
        return DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $productIds)
            ->whereIn('orders.payment_status', ['paid', 'refunded'])
            ->selectRaw('order_items.product_id, sum(order_items.quantity) as qty')
            ->groupBy('order_items.product_id')
            ->pluck('qty', 'product_id');
    }

    /** @return array<string, array<string, string>> type → value → label */
    private function taxonomyLabels(): array
    {
        return ProductTaxonomy::query()->get(['type', 'value', 'label'])
            ->groupBy('type')
            ->map(fn ($rows) => $rows->pluck('label', 'value')->all())
            ->all() + ['category' => [], 'flower_type' => [], 'occasion' => []];
    }
}
