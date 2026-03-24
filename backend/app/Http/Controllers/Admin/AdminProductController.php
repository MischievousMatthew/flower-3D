<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProductController extends Controller
{
    /**
     * List all products with filters for admin
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status', 'active');
            $search = $request->input('search');
            $category = $request->input('category');
            $flowerType = $request->input('flower_type');
            $minPrice = $request->input('min_price');
            $maxPrice = $request->input('max_price');

            // Build query
            $query = Product::with(['owner', 'images', 'model'])
                ->where('status', $status);

            // Apply search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('product_name', 'LIKE', "%{$search}%")
                      ->orWhere('sku', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhereHas('owner', function($ownerQuery) use ($search) {
                          $ownerQuery->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('store_name', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Apply category filter
            if ($category) {
                $query->where('category', $category);
            }

            // Apply flower type filter
            if ($flowerType) {
                $query->where('flower_type', $flowerType);
            }

            // Apply price filters
            if ($minPrice !== null) {
                $query->where('selling_price', '>=', $minPrice);
            }
            if ($maxPrice !== null) {
                $query->where('selling_price', '<=', $maxPrice);
            }

            // Order by created date (newest first)
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $products = $query->paginate($perPage);

            // Format response
            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $products->items(),
                    'meta' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'from' => $products->firstItem(),
                        'to' => $products->lastItem(),
                        'total' => $products->total(),
                        'per_page' => $products->perPage(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get product statistics for dashboard
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_products' => Product::count(),
                'active_products' => Product::where('status', 'active')->count(),
                'draft_products' => Product::where('status', 'draft')->count(),
                'inactive_products' => Product::where('status', 'inactive')->count(),
                'low_stock' => Product::whereColumn('quantity_in_stock', '<=', 'min_stock_level')
                    ->where('status', 'active')
                    ->count(),
                'out_of_stock' => Product::where('quantity_in_stock', 0)
                    ->where('status', 'active')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Show single product details
     */
    public function show($id)
    {
        try {
            $product = Product::with(['owner', 'images', 'model'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $product
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching product details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Toggle product status (active/inactive)
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $admin = $request->user();

            // Check if user is admin
            if ($admin->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Find product
            $product = Product::findOrFail($id);

            DB::beginTransaction();

            try {
                $product->toggleStatus();

                DB::commit();

                // Log the action
                Log::info("Product status toggled", [
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'new_status' => $product->status,
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Product status updated to ' . $product->status,
                    'data' => $product->fresh(['owner', 'images', 'model'])
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error toggling product status: ' . $e->getMessage(), [
                'product_id' => $id,
                'admin_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk toggle product status
     */
    public function bulkToggleStatus(Request $request)
    {
        try {
            $admin = $request->user();

            // Check if user is admin
            if ($admin->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|integer|exists:products,id',
                'status' => 'required|in:active,inactive'
            ]);

            DB::beginTransaction();

            try {
                $products = Product::whereIn('id', $request->product_ids)->get();
                $updatedCount = 0;

                foreach ($products as $product) {
                    $product->status = $request->status;
                    $product->save();
                    $updatedCount++;
                }

                DB::commit();

                Log::info("Bulk products status updated", [
                    'count' => $updatedCount,
                    'status' => $request->status,
                    'admin_id' => $admin->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "{$updatedCount} products updated to {$request->status}",
                    'data' => [
                        'updated_count' => $updatedCount
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error in bulk toggle status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update products',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a product (admin can delete any product)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $admin = $request->user();

            // Check if user is admin
            if ($admin->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $product = Product::findOrFail($id);

            DB::beginTransaction();

            try {
                // Delete 3D model if exists
                if ($product->model) {
                    \Storage::disk('public')->delete($product->model->model_path);
                    $product->model->delete();
                }

                // Delete images
                foreach ($product->images as $image) {
                    \Storage::disk('public')->delete($image->image_path);
                }

                $product->delete();

                DB::commit();

                Log::info("Product deleted by admin", [
                    'product_id' => $id,
                    'admin_id' => $admin->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Product deleted successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get categories for filter dropdown
     */
    public function categories()
    {
        try {
            $categories = Product::select('category')
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category');

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching categories: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    /**
     * Get flower types for filter dropdown
     */
    public function flowerTypes()
    {
        try {
            $flowerTypes = [
                'focal' => 'Focal Flowers',
                'secondary' => 'Secondary Flowers',
                'filler' => 'Filler Flowers',
                'line' => 'Line Flowers',
                'greenery' => 'Greenery'
            ];

            return response()->json([
                'success' => true,
                'data' => $flowerTypes
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching flower types: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch flower types'
            ], 500);
        }
    }
}