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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ResolvesOwner;

    // ──────────────────────────────────────────────────────────────────────
    // LIST endpoints (unchanged — already scope by owner_id correctly)
    // ──────────────────────────────────────────────────────────────────────

    public function myProducts(Request $request)
    {
        try {
            // Works for both vendor and employee — resolveOwnerId() returns
            // the vendor's ID in both cases so the product list is always
            // the vendor's catalogue, not just that employee's items.
            $ownerId = $this->resolveOwnerId($request);

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

    // ──────────────────────────────────────────────────────────────────────
    // STORE  ← KEY FIX: owner_id is always the vendor's ID
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a new product.
     *
     * FIX: We now call $this->resolveOwnerId($request) instead of using
     * $request->user()->id directly.  This means:
     *   • Vendor logs in  → owner_id = vendor's own users.id  ✅
     *   • Employee logs in → owner_id = employees.owner_id    ✅
     *                        (the vendor who employs them)
     *
     * The frontend still sends its own user id as owner_id in the form
     * payload, but we IGNORE that value and always compute it server-side.
     */
    public function store(Request $request)
    {
        try {
            // ── Resolve the REAL owner id ─────────────────────────────────
            $ownerId = $this->resolveOwnerId($request);
            // ─────────────────────────────────────────────────────────────

            // Normalize occasion_tags to array
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
                'images'                 => 'nullable|array|max:5',
                'images.*'               => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'model_file'             => [
                    'nullable', 'file', 'max:51200',
                    function ($attribute, $value, $fail) {
                        if (!in_array(strtolower($value->getClientOriginalExtension()), ['glb','gltf','obj','fbx'])) {
                            $fail('Invalid 3D model format. Allowed: glb, gltf, obj, fbx.');
                        }
                    },
                ],
            ], [
                'selling_price.gt'  => 'Selling price must be greater than purchase price',
                'discount_price.lt' => 'Discount price must be less than selling price',
                'occasion_tags.max' => 'You can only select up to 2 occasion tags',
                'model_file.max'    => 'Model file size must not exceed 50MB',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // ── Always override owner_id with the resolved vendor id ──────
            // This neutralises any spoofed owner_id coming from the frontend.
            $data['owner_id'] = $ownerId;
            // ─────────────────────────────────────────────────────────────

            // Handle discount
            if (empty($data['has_discount'])) {
                $data['has_discount']   = false;
                $data['discount_price'] = null;
            }

            $product = Product::create($data);

            if ($request->hasFile('model_file')) {
                $this->handle3DModel($request->file('model_file'), $product);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $imageFile) {
                    $path = $imageFile->store('product_images', 'public');
                    ProductImage::create([
                        'product_id'    => $product->id,
                        'image_url'     => asset('storage/' . $path),
                        'image_path'    => $path,
                        'is_primary'    => $index === 0,
                        'display_order' => $index,
                    ]);
                }
            }

            $product->load(['images', 'models']);

            return response()->json([
                'success' => true,
                'data'    => $product,
                'message' => $product->status === 'draft'
                    ? 'Product saved as draft successfully'
                    : 'Product published successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // UPDATE  ← Same fix: scope to resolved owner_id
    // ──────────────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);

            // Scope the lookup to the resolved owner so an employee cannot
            // accidentally edit products belonging to another vendor.
            $product = Product::where('id', $id)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            // Normalize occasion_tags
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
                'images'                 => 'nullable|array',
                'images.*'               => 'image|max:5120',
                'removed_image_ids'      => 'nullable|array',
                'removed_image_ids.*'    => 'integer',
                'model_file'             => [
                    'nullable', 'file', 'max:51200',
                    function ($attribute, $value, $fail) {
                        if (!in_array(strtolower($value->getClientOriginalExtension()), ['glb','gltf','obj','fbx'])) {
                            $fail('The model file must be: glb, gltf, obj, or fbx.');
                        }
                    },
                ],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $data = $request->all();

            // Discount resolution
            $hasDiscount = isset($data['has_discount']) && in_array($data['has_discount'], [1, '1', true, 'true'], true);

            if ($hasDiscount) {
                $discountPrice = $request->input('discount_price') !== null ? (float) $request->input('discount_price') : null;
                $sellingPrice  = isset($data['selling_price']) ? (float) $data['selling_price'] : (float) $product->getRawOriginal('selling_price');

                if (!$discountPrice || $discountPrice <= 0) {
                    return response()->json(['success' => false, 'errors' => ['discount_price' => ['Discount price is required when discount is enabled.']]], 422);
                }
                if ($discountPrice >= $sellingPrice) {
                    return response()->json(['success' => false, 'errors' => ['discount_price' => ['Discount price must be less than the selling price.']]], 422);
                }
            } else {
                $discountPrice = null;
            }

            unset($data['has_discount'], $data['discount_price'], $data['owner_id']); // never allow owner_id to change

            $product->update($data);

            DB::table('products')->where('id', $product->id)->update([
                'has_discount'   => $hasDiscount ? 1 : 0,
                'discount_price' => $hasDiscount ? $discountPrice : null,
                'updated_at'     => now(),
            ]);

            // Removed images
            if (!empty($data['removed_image_ids'])) {
                ProductImage::where('product_id', $product->id)
                    ->whereIn('id', $data['removed_image_ids'])
                    ->get()
                    ->each(function ($img) {
                        if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                            Storage::disk('public')->delete($img->image_path);
                        }
                        $img->delete();
                    });
            }

            // New images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $imageFile) {
                    $path = $imageFile->store('product_images', 'public');
                    ProductImage::create([
                        'product_id'    => $product->id,
                        'image_url'     => asset('storage/' . $path),
                        'image_path'    => $path,
                        'is_primary'    => false,
                        'display_order' => $product->images()->count() + $index,
                    ]);
                }
            }

            // 3D model
            if ($request->hasFile('model_file')) {
                if ($product->model) {
                    Storage::disk('public')->delete($product->model->model_path);
                    $product->model->delete();
                }
                $this->handle3DModel($request->file('model_file'), $product);
            }

            $product = Product::with(['primaryImage', 'images', 'models'])->find($product->id);

            return response()->json(['success' => true, 'data' => $product, 'message' => 'Product updated successfully']);

        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update product', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Remaining methods — same pattern: replace $vendor->id with resolveOwnerId
    // ──────────────────────────────────────────────────────────────────────

    public function destroy(Request $request, $id)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $product = Product::where('id', $id)->where('owner_id', $ownerId)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            if ($product->model) {
                Storage::disk('public')->delete($product->model->model_path);
                $product->model->delete();
            }
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image_path);
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

            if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
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

            if ($product->model->model_path && Storage::disk('public')->exists($product->model->model_path)) {
                Storage::disk('public')->delete($product->model->model_path);
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

    // ──────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────

    private function handle3DModel($file, Product $product): void
    {
        $extension = $file->getClientOriginalExtension();
        $filename  = Str::uuid() . '.' . $extension;
        $path      = $file->storeAs('product_models', $filename, 'public');

        ProductModel::create([
            'product_id' => $product->id,
            'model_url'  => asset('storage/' . $path),
            'model_path' => $path,
            'model_type' => $extension,
            'file_size'  => $file->getSize(),
            'metadata'   => [
                'original_filename' => $file->getClientOriginalName(),
                'uploaded_at'       => now()->toDateTimeString(),
            ],
        ]);
    }
}