<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\FeaturedProduct;
use App\Models\Product;
use App\Models\ProductTaxonomy;
use App\Models\VendorApplication;
use App\Models\VendorPromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Helpers\CloudinaryHelper;

class CustomerProductController extends Controller
{
    private $filterValidationRules = [
        'category'      => 'nullable|string|max:255',
        'flower_type'   => 'nullable|string|max:255',
        'color'         => 'nullable|string|max:100',
        'season'        => 'nullable|string|max:50',
        'occasion'      => 'nullable|string|max:255',
        'min_price'     => 'nullable|numeric|min:0',
        'max_price'     => 'nullable|numeric|min:0',
        'in_stock_only' => 'nullable',
        // Only flowers selling below their price right now (own discount or a shop promotion)
        'on_sale'       => 'nullable',
        // Only these shops (owner user ids, comma separated), e.g. the ones near the customer
        'owner_ids'     => 'nullable|string|max:2000',
        // Words typed into the Shop page search (name, category, colour, flower type)
        'search'        => 'nullable|string|max:100',
        'sort_by'       => 'nullable|string|in:created_at_desc,price_low,price_high,name_asc,name_desc,popular_desc,rating_desc,trending_desc',
        'page'          => 'nullable|integer|min:1',
        'per_page'      => 'nullable|integer|min:1|max:100',
    ];

    /**
     * GET /api/customer/products
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->filterValidationRules);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filter parameters',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $query = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->with([
                    'primaryImage',
                    'images'  => fn ($q) => $q->orderBy('display_order')->orderBy('id'),
                    'models',
                    'owner',
                ]);

            if ($request->filled('category')) {
                $query->whereIn('category', $this->sanitizeArray($request->category));
            }
            if ($request->filled('flower_type')) {
                $query->whereIn('flower_type', $this->sanitizeArray($request->flower_type));
            }
            if ($request->filled('color')) {
                $query->whereIn('color', $this->sanitizeArray($request->color));
            }
            if ($request->filled('season')) {
                $valid   = ['all-year', 'spring', 'summer', 'autumn', 'winter'];
                $seasons = array_intersect($this->sanitizeArray($request->season), $valid);
                if (!empty($seasons)) {
                    $query->whereIn('season', $seasons);
                }
            }
            if ($request->filled('occasion')) {
                $occasions = $this->sanitizeArray($request->occasion);
                if (!empty($occasions)) {
                    $query->where(function ($q) use ($occasions) {
                        foreach ($occasions as $occ) {
                            $q->orWhereJsonContains('occasion_tags', $occ);
                        }
                    });
                }
            }
            if ($request->filled('min_price') && is_numeric($request->min_price)) {
                $query->whereRaw('selling_price >= ?', [(float) $request->min_price]);
            }
            if ($request->filled('max_price') && is_numeric($request->max_price)) {
                $query->whereRaw('selling_price <= ?', [(float) $request->max_price]);
            }
            if ($request->filled('search')) {
                $words = array_slice(preg_split('/\s+/u', trim((string) $request->search), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);
                foreach ($words as $word) {
                    // % and _ typed by the customer are matched literally; lower case on
                    // both sides, since PostgreSQL's LIKE cares about capitals
                    $like = '%' . addcslashes(mb_strtolower($word), '%_\\') . '%';
                    $query->where(function ($q) use ($like) {
                        $q->whereRaw('LOWER(product_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(category) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(color) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(flower_type) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(CAST(occasion_tags AS TEXT)) LIKE ?', [$like]);
                    });
                }
            }
            if ($request->has('owner_ids')) {
                $ownerIds = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) $request->owner_ids)))), 0, 200);
                $query->whereIn('owner_id', $ownerIds ?: [0]);
            }
            // Only the best sellers (the shop's Top Selling row and its "View all"), most sold first
            $bestIds = [];
            if (filter_var($request->get('best_sellers', false), FILTER_VALIDATE_BOOLEAN)) {
                $bestIds = $this->tags()->bestSellerIds();
                $query->whereIn('id', $bestIds ?: [0]);
            }
            // Just these products (the "Recently viewed" row refreshing what the browser saved)
            if ($request->filled('ids')) {
                $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) $request->ids)))), 0, 24);
                $query->whereIn('id', $ids ?: [0]);
            }
            if (filter_var($request->get('in_stock_only', false), FILTER_VALIDATE_BOOLEAN)) {
                $query->where('quantity_in_stock', '>', 0);
            }
            if (filter_var($request->get('on_sale', false), FILTER_VALIDATE_BOOLEAN)) {
                $query->whereIn('id', $this->onSaleIds($query) ?: [0]);
            }

            $sortBy = $request->get('sort_by', 'created_at_desc');

            if ($bestIds && in_array($sortBy, [null, '', 'popular_desc'], true)) {
                $query->orderByRaw('CASE id ' . collect($bestIds)->map(fn ($id, $i) => 'WHEN ' . (int) $id . ' THEN ' . $i)->implode(' ') . ' END');
            } elseif ($sortBy === 'popular_desc') {
                $query->orderByRaw($this->soldCountSubquery() . ' DESC')
                    ->orderBy('created_at', 'desc');
            } elseif ($sortBy === 'trending_desc') {
                // Trending: the most units ordered in the last 7 days (orders that
                // weren't cancelled, failed, rejected or declined); flowers with
                // no orders that week aren't trending, so they're left out
                $since = now()->subDays(7)->toDateTimeString();
                $weekUnits = "(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi
                    INNER JOIN orders o ON o.id = oi.order_id
                    WHERE oi.product_id = products.id AND o.created_at >= ?
                      AND o.status NOT IN ('cancelled', 'failed', 'rejected', 'declined'))";
                $query->whereRaw("$weekUnits > 0", [$since])
                    ->orderByRaw("$weekUnits DESC", [$since])
                    ->orderBy('created_at', 'desc');
            } elseif ($sortBy === 'rating_desc') {
                // Top rated: highest average of visible reviews, then most reviews; unrated last
                $reviews = 'FROM product_reviews r WHERE r.product_id = products.id AND r.hidden_at IS NULL';
                $query->orderByRaw("COALESCE((SELECT AVG(r.rating) $reviews), 0) DESC")
                    ->orderByRaw("(SELECT COUNT(*) $reviews) DESC")
                    ->orderBy('created_at', 'desc');
            } else {
                $sortMap = [
                    'created_at_desc' => ['created_at', 'desc'],
                    'price_low' => ['selling_price', 'asc'],
                    'price_high' => ['selling_price', 'desc'],
                    'name_asc' => ['product_name', 'asc'],
                    'name_desc' => ['product_name', 'desc'],
                ];
                [$col, $dir] = $sortMap[$sortBy] ?? ['created_at', 'desc'];
                $query->orderBy($col, $dir);
            }

            $perPage  = min((int) $request->get('per_page', 12), 100);
            $products = $query->paginate($perPage);

            // Batch-load rating + sold stats in 2 queries (no N+1)
            $productIds = $products->pluck('id')->all();
            $statsMap   = $this->loadProductStats($productIds);

            // Batch-load vendor names
            $ownerEmails = $products->pluck('owner.email')->filter()->unique()->all();
            $vendorMap   = $this->loadVendorNames($ownerEmails);

            return response()->json([
                'success' => true,
                'data'    => [
                    'data'         => $products->map(fn ($p) => $this->formatProduct(
                        $p, 
                        $statsMap[$p->id] ?? null,
                        $vendorMap[$p->owner->email ?? ''] ?? null
                    )),
                    'current_page' => $products->currentPage(),
                    'last_page'    => $products->lastPage(),
                    'per_page'     => $products->perPage(),
                    'total'        => $products->total(),
                ],
                'message' => 'Products retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('CustomerProductController@index: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/customer/products/featured
     * The products admins picked for the shop, in their order. Only ones
     * customers can currently buy are returned.
     */
    public function featured()
    {
        try {
            $ids = FeaturedProduct::query()->orderBy('sort_order')->pluck('product_id')->all();
            if (!$ids) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $products = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->whereIn('id', $ids)
                ->with([
                    'primaryImage',
                    'images'  => fn ($q) => $q->orderBy('display_order')->orderBy('id'),
                    'models',
                    'owner',
                ])
                ->get()
                ->sortBy(fn ($p) => array_search($p->id, $ids, true))
                ->values();

            $statsMap  = $this->loadProductStats($products->pluck('id')->all());
            $vendorMap = $this->loadVendorNames($products->pluck('owner.email')->filter()->unique()->all());

            return response()->json([
                'success' => true,
                'data'    => $products->map(fn ($p) => $this->formatProduct(
                    $p,
                    $statsMap[$p->id] ?? null,
                    $vendorMap[$p->owner->email ?? ''] ?? null
                ))->values(),
            ]);
        } catch (\Exception $e) {
            Log::error('CustomerProductController@featured: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Featured products could not be loaded.'], 500);
        }
    }

    /**
     * GET /api/customer/products/filters
     */
    public function filters(Request $request)
    {
        try {
            $categories = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->distinct()->orderBy('category')->pluck('category')
                ->filter()->values();

            $flowerTypes = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->distinct()->orderBy('flower_type')->pluck('flower_type')
                ->filter()->values();

            $colors = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->distinct()->orderBy('color')->pluck('color')
                ->filter()->values();

            // Every occasion on the admin's list (so "Shop by occasion" is never
            // empty), plus any older value a product still uses, with how many
            // products each one has.
            $occasionCounts = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->whereNotNull('occasion_tags')
                ->pluck('occasion_tags')
                ->flatten()
                ->filter()
                ->countBy();
            $occasions = ProductTaxonomy::activeValues(ProductTaxonomy::OCCASION);
            $occasions = collect([...$occasions, ...$occasionCounts->keys()])->unique()->values();

            $priceRow = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->selectRaw('MIN(selling_price) as min_price, MAX(selling_price) as max_price')
                ->first();

            // Follow the admin-managed lists: their order first, then anything unlisted
            $lists = ProductTaxonomy::query()->orderBy('sort_order')->orderBy('label')->get(['type', 'value', 'label'])->groupBy('type');
            $ordered = function ($values, string $type) use ($lists) {
                $order = ($lists[$type] ?? collect())->pluck('value')->flip();
                return collect($values)->sortBy(fn ($v) => [$order[$v] ?? PHP_INT_MAX, $v])->values();
            };
            $categories = $ordered($categories, 'category');
            $flowerTypes = $ordered($flowerTypes, 'flower_type');
            $occasions = $ordered($occasions, 'occasion');

            return response()->json([
                'success' => true,
                'data'    => [
                    'labels'       => [
                        'categories'   => ($lists['category'] ?? collect())->pluck('label', 'value'),
                        'flower_types' => ($lists['flower_type'] ?? collect())->pluck('label', 'value'),
                        'occasions'    => ($lists['occasion'] ?? collect())->pluck('label', 'value'),
                    ],
                    'categories'   => $categories,
                    'flower_types' => $flowerTypes,
                    'colors'       => $colors,
                    'occasions'    => $occasions,
                    'occasion_counts' => $occasionCounts,
                    'seasons'      => ['all-year', 'spring', 'summer', 'autumn', 'winter'],
                    'price_range'  => [
                        'min' => (float) ($priceRow->min_price ?? 0),
                        'max' => (float) ($priceRow->max_price ?? 0),
                    ],
                ],
                'message' => 'Filter options retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('CustomerProductController@filters: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/customer/products/{id}
     */
    public function show($id)
    {
        try {
            $product = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->with(['primaryImage', 'images', 'models', 'owner'])
                ->findOrFail($id);

            $statsMap = $this->loadProductStats([$product->id]);
            $vendorName = null;
            if ($product->owner?->email) {
                $vendorName = VendorApplication::where('email', $product->owner->email)
                    ->where('status', 'approved')
                    ->value('store_name');
            }

            return response()->json([
                'success' => true,
                'data'    => $this->formatProduct($product, $statsMap[$product->id] ?? null, $vendorName),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }
    }

    /**
     * Serve 3D model files
     */
    public function serveModel($filename)
    {
        $filename = basename($filename);
        // Find the model by filename/path if possible, or just redirect if we have the public_id
        // Since we are migrating, it's better to just redirect to the Cloudinary URL.
        // For GLB/RAW files, we use the raw resource type.
        
        $model = \App\Models\ProductModel::where('model_url', 'like', '%' . $filename . '%')
            ->orWhere('model_path', 'like', '%' . $filename . '%')
            ->first();

        if ($model && $model->model_path) {
             return redirect()->away(CloudinaryHelper::getUrl($model->model_path, 'raw'));
        }

        return response()->json(['success' => false, 'message' => 'Model not found'], 404);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Load average_rating, total_reviews, and sold_count for a batch of
     * product IDs using exactly 2 queries — no N+1.
     *
     * @param  int[]  $productIds
     * @return array<int, array{average_rating: float|null, total_reviews: int, sold_count: int}>
     */
    private function loadProductStats(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Query 1: review stats per product
        $reviewStats = DB::table('product_reviews')
            ->whereIn('product_id', $productIds)
            ->whereNull('hidden_at')
            ->groupBy('product_id')
            ->select([
                'product_id',
                DB::raw('ROUND(AVG(rating), 1) as average_rating'),
                DB::raw('COUNT(*) as total_reviews'),
            ])
            ->get()
            ->keyBy('product_id');

        // Query 2: units sold from completed orders (excludes returns/refunds)
        $soldStats = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereIn('oi.product_id', $productIds)
            ->where(function ($q) {
                $q->where('o.status', 'completed')
                  ->orWhereIn('o.id', function ($sub) {
                      $sub->select('order_id')
                          ->from('deliveries')
                          ->where('status', 'completed');
                  });
            })
            ->whereNotIn('o.id', function ($q) {
                $q->select('order_id')
                  ->from('order_requests')
                  ->where('status', 'approved')
                  ->whereIn('type', ['return', 'refund']);
            })
            ->groupBy('oi.product_id')
            ->select([
                'oi.product_id',
                DB::raw('SUM(oi.quantity) as sold_count'),
            ])
            ->get()
            ->keyBy('product_id');

        // Merge into a map keyed by product_id
        $map = [];
        foreach ($productIds as $id) {
            $map[$id] = [
                'average_rating' => isset($reviewStats[$id])
                    ? (float) $reviewStats[$id]->average_rating
                    : null,
                'total_reviews'  => isset($reviewStats[$id])
                    ? (int) $reviewStats[$id]->total_reviews
                    : 0,
                'sold_count'     => isset($soldStats[$id])
                    ? (int) $soldStats[$id]->sold_count
                    : 0,
            ];
        }

        return $map;
    }

    /**
     * Batch-load store names from vendor_applications
     */
    private function loadVendorNames(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        return VendorApplication::whereIn('email', $emails)
            ->where('status', 'approved')
            ->pluck('store_name', 'email')
            ->all();
    }

    private function soldCountSubquery(): string
    {
        return <<<'SQL'
(
    SELECT COALESCE(SUM(oi.quantity), 0)
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE oi.product_id = products.id
      AND (
        o.status = 'completed'
        OR o.id IN (
            SELECT d.order_id
            FROM deliveries d
            WHERE d.status = 'completed'
        )
      )
      AND o.id NOT IN (
        SELECT orq.order_id
        FROM order_requests orq
        WHERE orq.status = 'approved'
          AND orq.type IN ('return', 'refund')
      )
)
SQL;
    }

    /**
     * Ids of the products in $query selling below their price right now: the
     * same check as the sale price shown on them (their own discount inside its
     * dates, or a running shop promotion that covers them). The database narrows
     * it to likely ones; the price check decides.
     */
    private function onSaleIds($query): array
    {
        $now = now();
        $promotionOwners = VendorPromotion::query()
            ->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)
            ->distinct()->pluck('owner_id')->all();

        return (clone $query)->setEagerLoads([])
            ->where(function ($q) use ($now, $promotionOwners) {
                $q->where(function ($own) use ($now) {
                    $own->whereNotNull('discount_price')->where('discount_price', '>', 0)
                        ->whereColumn('discount_price', '<', 'selling_price')
                        ->where(fn ($s) => $s->whereNull('discount_starts_at')->orWhere('discount_starts_at', '<=', $now))
                        ->where(fn ($e) => $e->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>=', $now));
                });
                if ($promotionOwners) {
                    $q->orWhereIn('owner_id', $promotionOwners);
                }
            })
            ->limit(2000)
            ->get()
            ->filter(function (Product $product) {
                $sale = $product->discount_price;

                return $sale !== null && (float) $sale < (float) $product->getRawOriginal('selling_price');
            })
            ->pluck('id')
            ->all();
    }

    private function formatProduct(Product $product, ?array $stats = null, ?string $vendorName = null): array
    {
        $sellingPrice  = (float) ($product->getRawOriginal('selling_price') ?? 0);
        $discountPrice = $product->discount_price ? (float) $product->discount_price : null;
        $hasDiscount   = $discountPrice !== null && $discountPrice < $sellingPrice;

        $primaryImage = $product->primaryImage ?? $product->images->first();

        return [
            'id'                => $product->id,
            'product_name'      => $product->product_name,
            'description'       => $product->description ?? '',
            'category'          => $product->category ?? '',
            'flower_type'       => $product->flower_type ?? '',
            'color'             => $product->color ?? '',
            'season'            => $product->season ?? '',
            'selling_price'     => $sellingPrice,
            'discount_price'    => $discountPrice,
            'has_discount'      => $hasDiscount,
            'quantity_in_stock' => (int) $product->quantity_in_stock,
            'occasion_tags'     => $product->occasion_tags ?? [],
            'care_instructions' => $product->care_instructions ?? '',
            // Made to order: days the shop needs to prepare it; how long it stays fresh
            'preparation_days'  => (int) ($product->preparation_days ?? 0),
            'freshness_days'    => $product->freshness_days ? (int) $product->freshness_days : null,
            'selling_type'      => $product->selling_type,
            // Filled in by the shop; empty until then (the page hides them)
            'color_options'     => $this->colorOptions($product->color_options),
            'composition'       => $this->composition($product->composition),
            // SALE, BEST SELLER, TRENDING… most important first (App\Services\ProductTags)
            // In the admin's order: a shop card shows only the first
            'tags'              => $this->tags()->tagsFor($product),
            'owner_id'          => $product->owner_id,
            'vendor_id'         => $product->owner_id,
            'vendor_name'       => $vendorName ?? 'Local Vendor',

            // Live stats — null average_rating means no reviews yet
            'average_rating'    => $stats['average_rating'] ?? null,
            'total_reviews'     => $stats['total_reviews']  ?? 0,
            'sold_count'        => $stats['sold_count']     ?? 0,

            'primary_image' => $primaryImage ? [
                'id'         => $primaryImage->id,
                'image_url'  => $primaryImage->image_url,
                'is_primary' => (bool) $primaryImage->is_primary,
            ] : null,
            'images' => $product->images->map(fn ($img) => [
                'id'         => $img->id,
                'image_url'  => $img->image_url,
                'is_primary' => (bool) $img->is_primary,
            ])->values()->all(),
            'models' => $product->models->map(fn ($m) => [
                'id'         => $m->id,
                'model_url'  => $m->model_path ? CloudinaryHelper::getUrl($m->model_path, 'raw') : $m->model_url,
                'model_type' => $m->model_type,
            ])->values()->all(),
        ];
    }

    /**
     * GET /api/customer/recommendations?exclude={id}&category={c}&limit=5
     *
     * "You may like", from the customer's own "Flowers you love": favourite
     * flowers count most, then colours and occasions; anything on their
     * "please avoid" list never shows. Guests, and customers who haven't
     * said what they like, get the same category's popular flowers instead
     * (personalized: false), with their avoid list still respected.
     */
    public function recommendations(Request $request)
    {
        $limit = min(12, max(1, (int) $request->input('limit', 5)));
        $exclude = (int) $request->input('exclude', 0);
        $category = (string) $request->input('category', '');

        $user = auth('sanctum')->user();
        $pref = $user instanceof \App\Models\User ? \App\Models\CustomerPreference::find($user->id) : null;
        $words = fn ($list) => collect(is_array($list) ? $list : [])->map(fn ($w) => $this->stem($w))->filter()->unique()->values();
        $likes = ['flowers' => $words($pref?->flowers), 'colors' => $words($pref?->colors), 'occasions' => $words($pref?->occasions)];
        $avoid = $words($pref?->avoid);
        $hasLikes = $likes['flowers']->isNotEmpty() || $likes['colors']->isNotEmpty() || $likes['occasions']->isNotEmpty();

        try {
            $products = Product::listedForCustomers()->where('status', 'active')
                ->whereIn('selling_type', ['per_piece', 'bouquet'])
                ->where('quantity_in_stock', '>', 0)
                ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))
                ->with(['primaryImage', 'images', 'models', 'owner'])
                ->latest()
                ->limit(300)
                ->get();

            $scored = $products->map(function (Product $p) use ($likes, $avoid, $category) {
                $text = $this->stem(implode(' ', [
                    $p->product_name, $p->category, $p->flower_type,
                    collect($p->composition ?? [])->pluck('flower')->implode(' '),
                ]));
                if ($avoid->contains(fn ($w) => str_contains($text, $w))) {
                    return null; // never something they asked to avoid
                }
                $colors = $this->stem(implode(' ', [$p->color, collect($p->color_options ?? [])->map(fn ($c) => is_array($c) ? ($c['name'] ?? '') : $c)->implode(' ')]));
                $occasions = $this->stem(implode(' ', $p->occasion_tags ?? []));

                $score = 3 * $likes['flowers']->filter(fn ($w) => str_contains($text, $w))->count()
                    + 2 * $likes['colors']->filter(fn ($w) => str_contains($colors, $w))->count()
                    + 2 * $likes['occasions']->filter(fn ($w) => str_contains($occasions, $w))->count();

                return ['product' => $p, 'score' => $score, 'same' => $category !== '' && $p->category === $category];
            })->filter();

            // Their likes first; then (or for guests) the same category, then the rest
            $picked = $scored->filter(fn ($s) => $s['score'] > 0)->sortByDesc('score')->take($limit);
            $personalized = $hasLikes && $picked->isNotEmpty();
            if ($picked->count() < $limit) {
                $more = $scored->reject(fn ($s) => $picked->contains(fn ($x) => $x['product']->id === $s['product']->id))
                    ->sortByDesc(fn ($s) => (int) $s['same'])
                    ->take($limit - $picked->count());
                $picked = $picked->concat($more);
            }
            $list = $picked->pluck('product')->values();

            $stats = $this->loadProductStats($list->pluck('id')->all());
            $vendors = $this->loadVendorNames($list->pluck('owner.email')->filter()->unique()->all());

            return response()->json([
                'success' => true,
                'personalized' => $personalized,
                'data' => $list->map(fn ($p) => $this->formatProduct($p, $stats[$p->id] ?? null, $vendors[$p->owner->email ?? ''] ?? null))
                    // Which ones came from their likes (they get the YOU MAY LIKE tag)
                    ->map(fn ($row, $i) => $row + ['for_you' => $personalized && $i < $picked->filter(fn ($s) => $s['score'] > 0)->count()])
                    ->values(),
            ]);
        } catch (\Throwable $e) {
            Log::error('CustomerProductController@recommendations: ' . $e->getMessage());
            return response()->json(['success' => true, 'personalized' => false, 'data' => []]);
        }
    }

    /** Lower case, plurals made singular, so "Roses" matches "rose" and "Lilies" matches "lily" */
    private function stem($text): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(function ($w) {
            if (mb_strlen($w) > 4 && str_ends_with($w, 'ies')) {
                return mb_substr($w, 0, -3) . 'y';
            }
            if (mb_strlen($w) > 3 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss')) {
                return mb_substr($w, 0, -1);
            }
            return $w;
        }, $words));
    }

    /**
     * GET /api/customer/product-tags/order: the admin's tag ranking, so a shop
     * card can also rank the tags it adds itself (You may like, Recently viewed)
     */
    public function tagOrder()
    {
        $rules = $this->tags()->rules();

        return response()->json([
            'success' => true,
            'data' => $rules['order'],
            // Cards show a different one of a product's tags each hour
            'rotate_hourly' => (bool) $rules['rotate_hourly'],
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * POST /api/customer/products/{id}/view: the product page was opened (for
     * the POPULAR tag). Just a count per product per day, nothing about who.
     */
    public function recordView($id)
    {
        $id = (int) $id;
        if ($id > 0 && Product::listedForCustomers()->where('status', 'active')->whereKey($id)->exists()) {
            try {
                DB::table('product_view_counts')->upsert(
                    [['product_id' => $id, 'day' => now()->toDateString(), 'views' => 1]],
                    ['product_id', 'day'],
                    ['views' => DB::raw('product_view_counts.views + 1')],
                );
            } catch (\Throwable $e) {
                Log::info('CustomerProductController@recordView: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }

    private ?\App\Services\ProductTags $tagService = null;

    /** One tag service per request: its lists are worked out once */
    private function tags(): \App\Services\ProductTags
    {
        return $this->tagService ??= app(\App\Services\ProductTags::class);
    }

    /**
     * Colours to pick from: [{name, hex, stock}], from names or {name, hex, quantity?}
     * entries. stock is that colour's own count, or null when colours share one stock.
     */
    private function colorOptions($raw): array
    {
        return collect(is_array($raw) ? $raw : [])
            ->map(fn ($c) => is_array($c)
                ? ['name' => trim((string) ($c['name'] ?? '')), 'hex' => $c['hex'] ?? null, 'stock' => isset($c['quantity']) && is_numeric($c['quantity']) ? max(0, (int) $c['quantity']) : null]
                : ['name' => trim((string) $c), 'hex' => null, 'stock' => null])
            ->filter(fn ($c) => $c['name'] !== '')
            ->map(fn ($c) => ['name' => $c['name'], 'hex' => is_string($c['hex']) && preg_match('/^#[0-9a-f]{3,8}$/i', $c['hex']) ? $c['hex'] : null, 'stock' => $c['stock']])
            ->unique(fn ($c) => mb_strtolower($c['name']))
            ->values()
            ->all();
    }

    /** The flowers in it: [{flower, color, count}], count at least 1 */
    private function composition($raw): array
    {
        return collect(is_array($raw) ? $raw : [])
            // The bouquet's wrapper sheets, filler and ribbon stock are for the shop's count, not part of "What's inside"
            ->filter(fn ($f) => is_array($f) && trim((string) ($f['flower'] ?? '')) !== '' && ! in_array($f['kind'] ?? null, ['wrapper', 'filler', 'ribbon'], true))
            ->map(fn ($f) => [
                'flower' => trim((string) $f['flower']),
                'color'  => isset($f['color']) && trim((string) $f['color']) !== '' ? trim((string) $f['color']) : null,
                'count'  => max(1, (int) ($f['count'] ?? 1)),
            ])
            ->values()
            ->all();
    }

    private function sanitizeArray($input): array
    {
        if (is_string($input)) {
            $input = explode(',', $input);
        }
        return is_array($input)
            ? array_values(array_filter(array_map('trim', $input)))
            : [];
    }
}
