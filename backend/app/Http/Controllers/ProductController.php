<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductModel;
use App\Traits\ResolvesOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Helpers\CloudinaryHelper;

class ProductController extends Controller
{
    use ResolvesOwner;

    public function myProducts(Request $request)
    {
        try {
            $ownerId  = $this->resolveOwnerId($request);
            $products = Product::where('owner_id', $ownerId)
                ->where('status', 'active')
                ->with(['primaryImage', 'images', 'models'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $products,
                'message' => 'Active products retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching active products: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch products'], 500);
        }
    }

    public function draftProducts(Request $request)
    {
        try {
            $ownerId  = $this->resolveOwnerId($request);
            $products = Product::where('owner_id', $ownerId)
                ->where('status', 'draft')
                ->with(['primaryImage', 'images', 'models'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $products, 'message' => 'Draft products retrieved successfully']);
        } catch (\Exception $e) {
            Log::error('Error fetching draft products: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch draft products'], 500);
        }
    }

    public function inactiveProducts(Request $request)
    {
        try {
            $ownerId  = $this->resolveOwnerId($request);
            $products = Product::where('owner_id', $ownerId)
                ->where('status', 'inactive')
                ->with(['primaryImage', 'images', 'models'])
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $products, 'message' => 'Inactive products retrieved successfully']);
        } catch (\Exception $e) {
            Log::error('Error fetching inactive products: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch inactive products'], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $id)
                ->where('owner_id', $ownerId)
                ->with(['images', 'models'])
                ->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            return response()->json(['success' => true, 'data' => $product]);
        } catch (\Exception $e) {
            Log::error('Error fetching product: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch product'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);

            // ── Debug log: confirm files received ────────────────────────────
            Log::info('ProductController::store — files received', [
                'has_images'     => $request->hasFile('images'),
                'has_model'      => $request->hasFile('model_file'),
                'all_files'      => array_keys($request->allFiles()),
                'content_type'   => $request->header('Content-Type'),
            ]);

            if ($request->has('occasion_tags') && !is_array($request->occasion_tags)) {
                $tags = json_decode($request->occasion_tags, true);
                if (is_array($tags)) {
                    $request->merge(['occasion_tags' => $tags]);
                }
            }

            $validator = Validator::make($request->all(), [
                'product_name'           => 'required|string|max:255',
                'description'            => 'required|string',
                'sku'                    => 'required|string|max:255|unique:products,sku',
                'category'               => 'required|string|max:255',
                'flower_type'            => 'required|in:focal,secondary,filler,line,greenery',
                'color'                  => 'required|in:white,yellow,red,pink,purple,orange,blue,green,cream,other',
                'color_other'            => 'required_if:color,other|nullable|string|max:100',
                'purchase_price'         => 'required|numeric|min:0',
                'selling_price'          => 'required|numeric|min:0|gt:purchase_price',
                'has_discount'           => 'nullable|boolean',
                'discount_price'         => 'nullable|numeric|min:0|lt:selling_price',
                'quantity_in_stock'      => 'required|integer|min:0',
                'min_stock_level'        => 'required|integer|min:0',
                'max_stock_level'        => 'nullable|integer|min:0',
                'selling_type'           => 'required|in:per_piece,per_piece_customizable,bouquet',
                'season'                 => 'nullable|in:all-year,spring,summer,autumn,winter',
                'supplier_name'          => 'nullable|string|max:255',
                'supplier_contact'       => 'nullable|string|max:100',
                'supplier_sku'           => 'nullable|string|max:255',
                'supplier_lead_time'     => 'nullable|integer|min:0',
                'care_instructions'      => 'nullable|string',
                'occasion_tags'          => 'nullable|array|max:2',
                'occasion_tags.*'        => 'string',
                'notes'                  => 'nullable|string',
                'is_fragile'             => 'nullable|boolean',
                'requires_refrigeration' => 'nullable|boolean',
                'status'                 => 'required|in:draft,active,discontinued',
                // ── files validated separately below ──
            ], [
                'selling_price.gt'  => 'Selling price must be greater than purchase price',
                'discount_price.lt' => 'Discount price must be less than selling price',
                'occasion_tags.max' => 'You can only select up to 2 occasion tags',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            // ── Strip file keys from validated data so they don't hit Product::create ──
            $data = collect($validator->validated())
                ->except(['images', 'model_file'])
                ->toArray();

            $data['owner_id'] = $ownerId;

            if (empty($data['has_discount'])) {
                $data['has_discount']   = false;
                $data['discount_price'] = null;
            }

            // ── Create the product (no file columns here) ─────────────────
            $product = Product::create($data);

            Log::info('Product created', ['product_id' => $product->id]);

            // ── Upload images to Cloudinary ───────────────────────────────
            $this->handleImageUploads($request, $product);

            // ── Upload 3D model to Cloudinary ─────────────────────────────
            $this->handleModelUpload($request, $product);

            $product->load(['images', 'models']);

            return response()->json([
                'success' => true,
                'data'    => $product,
                'message' => $product->status === 'draft'
                    ? 'Product saved as draft successfully'
                    : 'Product published successfully',
            ], 201);

        } catch (\Throwable $e) {
            $errorId = (string) Str::uuid();

            Log::error('ProductController::store failed', [
                'error_id' => $errorId,
                'type'     => get_class($e),
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'user_id'  => $request->user()?->id,
            ]);

            return response()->json([
                'success'  => false,
                'message'  => 'Failed to create product',
                'error_id' => $errorId,
                'debug'    => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $id)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            if ($request->has('occasion_tags') && !is_array($request->occasion_tags)) {
                $tags = json_decode($request->occasion_tags, true);
                if (is_array($tags)) {
                    $request->merge(['occasion_tags' => $tags]);
                }
            }

            $validator = Validator::make($request->all(), [
                'product_name'           => 'nullable|string|max:255',
                'description'            => 'nullable|string',
                'sku'                    => 'nullable|string|max:255|unique:products,sku,' . $id,
                'category'               => 'nullable|string|max:255',
                'flower_type'            => 'nullable|in:focal,secondary,filler,line,greenery',
                'color'                  => 'nullable|in:white,yellow,red,pink,purple,orange,blue,green,cream,other',
                'color_other'            => 'required_if:color,other|nullable|string|max:100',
                'purchase_price'         => 'nullable|numeric|min:0',
                'selling_price'          => 'nullable|numeric|min:0',
                'has_discount'           => 'nullable|boolean',
                'discount_price'         => 'nullable|numeric|min:0',
                'quantity_in_stock'      => 'nullable|integer|min:0',
                'min_stock_level'        => 'nullable|integer|min:0',
                'max_stock_level'        => 'nullable|integer|min:0',
                'selling_type'           => 'nullable|in:per_piece,per_piece_customizable,bouquet',
                'season'                 => 'nullable|in:all-year,spring,summer,autumn,winter',
                'supplier_name'          => 'nullable|string|max:255',
                'supplier_contact'       => 'nullable|string|max:100',
                'supplier_sku'           => 'nullable|string|max:255',
                'supplier_lead_time'     => 'nullable|integer|min:0',
                'care_instructions'      => 'nullable|string',
                'occasion_tags'          => 'nullable|array|max:2',
                'notes'                  => 'nullable|string',
                'is_fragile'             => 'nullable|boolean',
                'requires_refrigeration' => 'nullable|boolean',
                'status'                 => 'nullable|in:draft,active,inactive,discontinued',
                'removed_image_ids'      => 'nullable|array',
                'removed_image_ids.*'    => 'integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $data = collect($request->all())
                ->except(['images', 'model_file', 'removed_image_ids', 'owner_id', '_method'])
                ->toArray();

            $hasDiscount   = isset($data['has_discount']) && in_array($data['has_discount'], [1, '1', true, 'true'], true);
            $discountPrice = null;

            if ($hasDiscount) {
                $discountPrice = $request->input('discount_price') !== null ? (float) $request->input('discount_price') : null;
                $sellingPrice  = isset($data['selling_price']) ? (float) $data['selling_price'] : (float) $product->getRawOriginal('selling_price');

                if (!$discountPrice || $discountPrice <= 0) {
                    return response()->json(['success' => false, 'errors' => ['discount_price' => ['Discount price is required when discount is enabled.']]], 422);
                }
                if ($discountPrice >= $sellingPrice) {
                    return response()->json(['success' => false, 'errors' => ['discount_price' => ['Discount price must be less than the selling price.']]], 422);
                }
            }

            unset($data['has_discount'], $data['discount_price']);
            $product->update($data);

            DB::table('products')->where('id', $product->id)->update([
                'has_discount'   => $hasDiscount ? 1 : 0,
                'discount_price' => $hasDiscount ? $discountPrice : null,
                'updated_at'     => now(),
            ]);

            // ── Delete removed images ─────────────────────────────────────
            if ($request->has('removed_image_ids') && is_array($request->removed_image_ids)) {
                ProductImage::where('product_id', $product->id)
                    ->whereIn('id', $request->removed_image_ids)
                    ->get()
                    ->each(function ($img) {
                        if ($img->image_path) {
                            CloudinaryHelper::destroy($img->image_path);
                        }
                        $img->delete();
                    });
            }

            // ── Upload new images ─────────────────────────────────────────
            $this->handleImageUploads($request, $product);

            // ── Replace 3D model ──────────────────────────────────────────
            if ($request->hasFile('model_file')) {
                if ($product->model) {
                    CloudinaryHelper::destroy($product->model->model_path, ['resource_type' => 'raw']);
                    $product->model->delete();
                }
                $this->handleModelUpload($request, $product);
            }

            $product = Product::with(['primaryImage', 'images', 'models'])->find($product->id);

            return response()->json(['success' => true, 'data' => $product, 'message' => 'Product updated successfully']);

        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update product', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $id)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            if ($product->model && $product->model->model_path) {
                CloudinaryHelper::destroy($product->model->model_path, ['resource_type' => 'raw']);
                $product->model->delete();
            }

            foreach ($product->images as $image) {
                if ($image->image_path) {
                    CloudinaryHelper::destroy($image->image_path);
                }
            }

            $product->delete();

            return response()->json(['success' => true, 'message' => 'Product deleted successfully']);

        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete product'], 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $id)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $product->toggleStatus();

            return response()->json(['success' => true, 'data' => $product, 'message' => 'Product status updated to ' . $product->status]);
        } catch (\Exception $e) {
            Log::error('Error toggling product status: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update product status'], 500);
        }
    }

    public function updateStock(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $id)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $request->validate(['quantity_in_stock' => 'required|integer|min:0']);
            $product->updateStock($request->quantity_in_stock);

            return response()->json(['success' => true, 'data' => $product, 'message' => 'Stock updated successfully']);
        } catch (\Exception $e) {
            Log::error('Error updating stock: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update stock'], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $id)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:draft,active,inactive,discontinued',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $product->status = $request->status;
            $product->save();

            return response()->json(['success' => true, 'data' => $product, 'message' => 'Product status updated to ' . $product->status]);
        } catch (\Exception $e) {
            Log::error('Error updating product status: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update product status'], 500);
        }
    }

    public function deleteImage(Request $request, $productId, $imageId)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $productId)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $image = ProductImage::where('id', $imageId)->where('product_id', $productId)->first();
            if (!$image) {
                return response()->json(['success' => false, 'message' => 'Image not found'], 404);
            }

            if ($image->image_path) {
                CloudinaryHelper::destroy($image->image_path);
            }

            $image->delete();

            return response()->json(['success' => true, 'message' => 'Image deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting image: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete image'], 500);
        }
    }

    public function deleteModel(Request $request, $productId)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $productId)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            if (!$product->model) {
                return response()->json(['success' => false, 'message' => '3D model not found'], 404);
            }

            if ($product->model->model_path) {
                CloudinaryHelper::destroy($product->model->model_path, ['resource_type' => 'raw']);
            }

            $product->model->delete();

            return response()->json(['success' => true, 'message' => '3D model deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting 3D model: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete 3D model'], 500);
        }
    }

    public function searchForWarehouse(Request $request)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);

            $query = Product::where('owner_id', $ownerId)
                ->whereIn('status', ['active', 'inactive'])
                ->whereNull('deleted_at')
                ->with(['primaryImage'])
                ->orderBy('product_name');

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $perPage  = (int) $request->query('per_page', 50);
            $products = $query->paginate(min($perPage, 100));

            $products->getCollection()->transform(function ($p) {
                $p->image_url = $p->primaryImage?->image_url ?? null;
                return $p;
            });

            return response()->json(['success' => true, 'data' => $products]);
        } catch (\Exception $e) {
            Log::error('searchForWarehouse error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to search products'], 500);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Handle image uploads from request to Cloudinary.
     * Accepts images[] (array) sent by the Vue form.
     */
    private function handleImageUploads(Request $request, Product $product): void
    {
        // Support both images[] and images
        $files = $request->file('images') ?? [];

        if (!$request->hasFile('images')) {
            Log::info('No images in request for product ' . $product->id);
            return;
        }

        // Normalize to array
        if (!is_array($files)) {
            $files = [$files];
        }

        // Flatten — sometimes Laravel wraps in extra array
        $files = array_values(array_filter($files));

        $existingCount = $product->images()->count();

        foreach ($files as $index => $imageFile) {
            if (!($imageFile instanceof \Illuminate\Http\UploadedFile)) {
                Log::warning('Skipping non-UploadedFile at index ' . $index);
                continue;
            }

            Log::info('Uploading image to Cloudinary', [
                'product_id'      => $product->id,
                'original_name'   => $imageFile->getClientOriginalName(),
                'size'            => $imageFile->getSize(),
                'mime'            => $imageFile->getMimeType(),
            ]);

            try {
                $result = CloudinaryHelper::upload($imageFile, [
                    'folder'        => 'product_images',
                    'resource_type' => 'image',
                ]);

                ProductImage::create([
                    'product_id'    => $product->id,
                    'image_url'     => $result['secure_url'],
                    'image_path'    => $result['public_id'],
                    'is_primary'    => ($existingCount === 0 && $index === 0),
                    'display_order' => $existingCount + $index,
                ]);

                Log::info('Image uploaded successfully', [
                    'product_id' => $product->id,
                    'public_id'  => $result['public_id'],
                    'url'        => $result['secure_url'],
                ]);

            } catch (\Throwable $e) {
                // Log but don't fail the whole product creation
                Log::error('Image upload failed for product ' . $product->id, [
                    'index'   => $index,
                    'error'   => $e->getMessage(),
                    'file'    => $imageFile->getClientOriginalName(),
                ]);
            }
        }
    }

    /**
     * Handle 3D model upload from request to Cloudinary.
     */
    private function handleModelUpload(Request $request, Product $product): void
    {
        if (!$request->hasFile('model_file')) {
            Log::info('No model_file in request for product ' . $product->id);
            return;
        }

        $modelFile = $request->file('model_file');

        // Laravel may parse it as an array if browser sends model_file[]
        if (is_array($modelFile)) {
            $modelFile = $modelFile[0] ?? null;
        }

        if (!($modelFile instanceof \Illuminate\Http\UploadedFile)) {
            Log::warning('model_file is not a valid UploadedFile for product ' . $product->id);
            return;
        }

        $extension = strtolower($modelFile->getClientOriginalExtension());
        $allowed   = ['glb', 'gltf', 'obj', 'fbx'];

        if (!in_array($extension, $allowed)) {
            Log::warning('Invalid 3D model extension: ' . $extension);
            return;
        }

        if ($modelFile->getSize() > 50 * 1024 * 1024) {
            Log::warning('3D model too large: ' . $modelFile->getSize() . ' bytes');
            return;
        }

        Log::info('Uploading 3D model to Cloudinary', [
            'product_id'    => $product->id,
            'original_name' => $modelFile->getClientOriginalName(),
            'size'          => $modelFile->getSize(),
            'extension'     => $extension,
        ]);

        try {
            $result = CloudinaryHelper::upload($modelFile, [
                'folder'        => 'product_models',
                'resource_type' => 'raw',
            ]);

            ProductModel::create([
                'product_id' => $product->id,
                'model_url'  => $result['secure_url'],
                'model_path' => $result['public_id'],
                'model_type' => $extension,
                'file_size'  => $modelFile->getSize(),
                'metadata'   => [
                    'original_filename' => $modelFile->getClientOriginalName(),
                    'uploaded_at'       => now()->toDateTimeString(),
                ],
            ]);

            Log::info('3D model uploaded successfully', [
                'product_id' => $product->id,
                'public_id'  => $result['public_id'],
                'url'        => $result['secure_url'],
            ]);

        } catch (\Throwable $e) {
            Log::error('3D model upload failed for product ' . $product->id, [
                'error' => $e->getMessage(),
                'file'  => $modelFile->getClientOriginalName(),
            ]);
        }
    }

    // ── Admin index ───────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $perPage  = $request->input('per_page', 15);
            $status   = $request->input('status', 'active');
            $search   = $request->input('search');
            $category = $request->input('category');

            $query = Product::with(['owner', 'images', 'model'])
                ->where('status', $status);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('product_name', 'LIKE', "%{$search}%")
                      ->orWhere('sku', 'LIKE', "%{$search}%")
                      ->orWhereHas('owner', fn ($o) => $o->where('name', 'LIKE', "%{$search}%"));
                });
            }

            if ($category) {
                $query->where('category', $category);
            }

            $query->orderBy('created_at', 'desc');
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => [
                    'data' => $products->items(),
                    'meta' => [
                        'current_page' => $products->currentPage(),
                        'last_page'    => $products->lastPage(),
                        'from'         => $products->firstItem(),
                        'to'           => $products->lastItem(),
                        'total'        => $products->total(),
                        'per_page'     => $products->perPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch products'], 500);
        }
    }

    public function statistics()
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'total_products'   => Product::count(),
                    'active_products'  => Product::where('status', 'active')->count(),
                    'draft_products'   => Product::where('status', 'draft')->count(),
                    'inactive_products'=> Product::where('status', 'inactive')->count(),
                    'low_stock'        => Product::whereColumn('quantity_in_stock', '<=', 'min_stock_level')->where('status', 'active')->count(),
                    'out_of_stock'     => Product::where('quantity_in_stock', 0)->where('status', 'active')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching statistics: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch statistics'], 500);
        }
    }
}