<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VendorApplication;
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
        'sort_by'       => 'nullable|string|in:created_at_desc,price_low,price_high,name_asc,name_desc',
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

            $query = Product::where('status', 'active')
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
            if (filter_var($request->get('in_stock_only', false), FILTER_VALIDATE_BOOLEAN)) {
                $query->where('quantity_in_stock', '>', 0);
            }

            $sortMap = [
                'created_at_desc' => ['created_at',   'desc'],
                'price_low'       => ['selling_price', 'asc'],
                'price_high'      => ['selling_price', 'desc'],
                'name_asc'        => ['product_name',  'asc'],
                'name_desc'       => ['product_name',  'desc'],
            ];
            [$col, $dir] = $sortMap[$request->get('sort_by', 'created_at_desc')] ?? ['created_at', 'desc'];
            $query->orderBy($col, $dir);

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
     * GET /api/customer/products/filters
     */
    public function filters(Request $request)
    {
        try {
            $categories = Product::where('status', 'active')
                ->distinct()->orderBy('category')->pluck('category')
                ->filter()->values();

            $flowerTypes = Product::where('status', 'active')
                ->distinct()->orderBy('flower_type')->pluck('flower_type')
                ->filter()->values();

            $colors = Product::where('status', 'active')
                ->distinct()->orderBy('color')->pluck('color')
                ->filter()->values();

            $occasions = Product::where('status', 'active')
                ->whereNotNull('occasion_tags')
                ->pluck('occasion_tags')
                ->flatten()
                ->unique()
                ->filter()
                ->sort()
                ->values();

            $priceRow = Product::where('status', 'active')
                ->selectRaw('MIN(selling_price) as min_price, MAX(selling_price) as max_price')
                ->first();

            return response()->json([
                'success' => true,
                'data'    => [
                    'categories'   => $categories,
                    'flower_types' => $flowerTypes,
                    'colors'       => $colors,
                    'occasions'    => $occasions,
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
            $product = Product::where('status', 'active')
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