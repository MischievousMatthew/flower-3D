<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\VendorApplication;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VendorStorefrontController extends Controller
{
    /**
     * GET /api/vendors
     * Paginated list of approved vendors for the Shop browse section.
     */
    public function index(Request $request)
    {
        try {
            $query = VendorApplication::where('status', 'approved');

            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $query->where(function ($q) use ($s) {
                    $q->where('store_name', 'like', $s)
                      ->orWhere('store_description', 'like', $s)
                      ->orWhere('business_type', 'like', $s);
                });
            }

            if ($request->filled('product_type')) {
                $query->whereJsonContains('product_types', $request->product_type);
            }

            if ($request->filled('area')) {
                $query->whereJsonContains('service_areas', $request->area);
            }

            if ($request->boolean('same_day_delivery')) {
                $query->where('same_day_delivery', true);
            }

            if ($request->filled('min_price')) {
                $query->where('price_min', '>=', (float) $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('price_max', '<=', (float) $request->max_price);
            }

            $perPage = min((int) $request->get('per_page', 8), 50);
            $vendors = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => [
                    'data'         => $vendors->map(fn ($v) => $this->formatVendor($v)),
                    'current_page' => $vendors->currentPage(),
                    'last_page'    => $vendors->lastPage(),
                    'per_page'     => $vendors->perPage(),
                    'total'        => $vendors->total(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('VendorStorefrontController@index: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/vendors/{id}
     * Single vendor detail page.
     *
     * We query WITHOUT a status filter first so we can return a helpful
     * error if the vendor exists but isn't approved yet.
     */
    public function show($id)
    {
        try {
            $vendor = VendorApplication::find($id);

            if (!$vendor) {
                Log::warning("VendorStorefront@show: vendor id={$id} not found in vendor_applications table");
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found.',
                ], 404);
            }

            // Allow approved vendors (and optionally pending for preview)
            if ($vendor->status !== 'approved') {
                Log::info("VendorStorefront@show: vendor id={$id} status={$vendor->status} — not approved");
                return response()->json([
                    'success' => false,
                    'message' => 'This store is not currently available.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $this->formatVendor($vendor, true),
            ]);

        } catch (\Exception $e) {
            Log::error('VendorStorefrontController@show: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/vendors/{vendorId}/products
     *
     * vendor_applications.id  = the URL param (vendorId)
     * vendor_applications.owner_id = NULL (not a FK column in this schema)
     *
     * The link between vendor ↔ products is:
     *   vendor_applications.email  →  users.email  →  users.id  →  products.owner_id
     *
     * We resolve it by joining users on email so we get the user's id,
     * then query products WHERE owner_id = that user id.
     */
    public function getProducts(Request $request, $vendorId)
    {
        try {
            $vendor = VendorApplication::find($vendorId);
            if (!$vendor) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Resolve the user id that owns the products.
            // Priority 1: vendor_applications.owner_id column (if it exists)
            // Priority 2: match users.email to vendor_applications.email → get users.id
            $ownerId = $vendor->owner_id ?? null;

            if (!$ownerId && $vendor->email) {
                $ownerId = DB::table('users')
                    ->where('email', $vendor->email)
                    ->value('id');
            }

            Log::info("VendorStorefront@getProducts", [
                'vendorId'       => $vendorId,
                'vendor_email'   => $vendor->email,
                'resolved_owner' => $ownerId,
            ]);

            if (!$ownerId) {
                Log::warning("VendorStorefront@getProducts: could not resolve owner_id for vendor id={$vendorId}");
                return response()->json(['success' => true, 'data' => []]);
            }

            $query = Product::where('owner_id', $ownerId)
                ->where('status', 'active')
                ->with([
                    'primaryImage',
                    'images'  => fn ($q) => $q->orderBy('display_order')->orderBy('id'),
                    'models',
                ]);

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }
            if ($request->filled('flower_type')) {
                $query->where('flower_type', $request->flower_type);
            }
            if ($request->filled('occasion')) {
                $query->whereJsonContains('occasion_tags', $request->occasion);
            }

            $sortMap = [
                'newest'     => ['created_at',   'desc'],
                'price_low'  => ['selling_price', 'asc'],
                'price_high' => ['selling_price', 'desc'],
            ];
            [$col, $dir] = $sortMap[$request->get('sort_by', 'newest')] ?? ['created_at', 'desc'];
            $query->orderBy($col, $dir);

            $products = $query->get();

            return response()->json([
                'success' => true,
                'data'    => $products->map(fn ($p) => $this->formatProduct($p)),
            ]);

        } catch (\Exception $e) {
            Log::error('VendorStorefrontController@getProducts: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/vendors/{vendorId}/follow  (auth required)
     */
    public function follow(Request $request, $vendorId)
    {
        // Placeholder — implement with a vendor_followers table when ready
        return response()->json(['success' => true, 'message' => 'Following store.']);
    }

    /**
     * POST /api/vendors/{vendorId}/unfollow  (auth required)
     */
    public function unfollow(Request $request, $vendorId)
    {
        return response()->json(['success' => true, 'message' => 'Unfollowed store.']);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Safely resolve a Storage path to a public URL.
     * Handles null, already-full URLs, and raw relative paths.
     */
    /**
     * Convert slug array like ["bouquet_and_flower"] to ["Bouquet and Flower"]
     */
    private function humanizeProductTypes(array $types): array
    {
        return array_map(function (string $type) {
            return ucwords(str_replace(['_', '-'], ' ', $type));
        }, $types);
    }

    /**
     * service_areas may be stored as a JSON array OR a plain string (e.g. "Cavite").
     * Always return an array so the frontend can iterate safely.
     */
    private function normalizeServiceAreas(mixed $raw): array
    {
        if (empty($raw)) return [];

        // Already a PHP array (model cast it)
        if (is_array($raw)) return $raw;

        // Try JSON decode first
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            // Plain comma-separated string: "Cavite, General Trias" or just "Cavite"
            return array_map('trim', explode(',', $raw));
        }

        return [];
    }

    private function storageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        // Already a full URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return Storage::url($path);
    }

    /**
     * Format a VendorApplication for the storefront.
     * All array-cast fields default to [] so the frontend never crashes.
     */
    private function formatVendor(VendorApplication $v, bool $full = false): array
    {
        $base = [
            'id'                  => $v->id,
            'owner_id'            => $v->owner_id,
            'store_name'          => $v->store_name ?? '',
            'store_description'   => $v->store_description ?? '',
            'store_logo_path'     => $this->storageUrl($v->store_logo_path),
            'business_type'       => $v->business_type ?? '',
            'store_address'       => $v->store_address ?? '',
            'verification_level'  => $v->verification_level ?? '',
            'status'              => $v->status ?? '',
            'same_day_delivery'   => (bool) ($v->same_day_delivery ?? false),
            'price_min'           => (float) ($v->price_min ?? 0),
            'price_max'           => (float) ($v->price_max ?? 0),
            'product_types'       => $this->humanizeProductTypes($v->product_types ?? []),
            'service_areas'       => $this->normalizeServiceAreas($v->service_areas),
            'cutoff_times'        => $v->cutoff_times ?? '',
            'operating_hours'     => $v->operating_hours ?? '',
        ];

        if (!$full) {
            return $base;
        }

        return array_merge($base, [
            'max_orders_per_day'   => $v->max_orders_per_day ?? 0,
            'default_delivery_fee' => $v->default_delivery_fee ?? 0,
            'lead_time'            => $v->lead_time ?? '',
            'cancellation_policy'  => $v->cancellation_policy ?? '',
            'portfolio_photos_paths' => array_map(
                fn ($p) => $this->storageUrl($p),
                $v->portfolio_photos_paths ?? []
            ),
            'facebook_page'   => $v->facebook_page ?? '',
            'instagram_page'  => $v->instagram_page ?? '',
            'contact_number'  => $v->contact_number ?? '',
            'email'           => $v->email ?? '',
        ]);
    }

    private function formatProduct(Product $product): array
    {
        $rawSelling    = $product->getRawOriginal('selling_price');
        $rawPrice      = $product->getRawOriginal('price');
        $sellingPrice  = (float) ($rawSelling ?? $rawPrice ?? 0);
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
            'owner_id'          => $product->owner_id,
            'vendor_id'         => $product->owner_id,
            'primary_image'     => $primaryImage ? [
                'id'        => $primaryImage->id,
                'image_url' => $primaryImage->image_url,
            ] : null,
            'images' => $product->images->map(fn ($img) => [
                'id'        => $img->id,
                'image_url' => $img->image_url,
                'is_primary'=> (bool) $img->is_primary,
            ])->values()->all(),
            'models' => $product->models->map(fn ($m) => [
                'id'         => $m->id,
                'model_url'  => url('api/customer/product-models/' . basename($m->model_url)),
                'model_type' => $m->model_type,
            ])->values()->all(),
        ];
    }
}