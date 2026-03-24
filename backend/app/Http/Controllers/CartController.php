<?php
namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\VendorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Get user's cart items
     */
     public function index(Request $request)
    {
        try {
            $user = $request->user();

            $cartItems = Cart::where('user_id', $user->id)
                ->with([
                    'product.primaryImage', 
                    'product.images', 
                    'product.owner'
                ])
                ->get()
                ->map(function ($cartItem) {
                    return [
                        'id' => $cartItem->id,
                        'quantity' => $cartItem->quantity,
                        'price' => (float)$cartItem->price,
                        'color' => $cartItem->color,
                        'size' => $cartItem->size,
                        'notes' => $cartItem->notes,
                        'customizations' => $cartItem->customizations,
                        'subtotal' => $cartItem->subtotal,
                        'total' => $cartItem->total,
                        'is_available' => $cartItem->is_available,
                        'is_low_stock' => $cartItem->is_low_stock,
                        'stock_status' => $cartItem->stock_status,
                        'product' => $cartItem->product ? $this->sanitizeProductData($cartItem->product) : null,
                        'created_at' => $cartItem->created_at,
                        'updated_at' => $cartItem->updated_at,
                    ];
                });

            $subtotal = $cartItems->sum('subtotal');
            $totalItems = $cartItems->sum('quantity');
            $availableItems = $cartItems->where('is_available', true)->sum('quantity');

            $unavailableItems = $cartItems->filter(fn($item) => !$item['is_available'])->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $cartItems,
                    'summary' => [
                        'total_items' => $totalItems,
                        'available_items' => $availableItems,
                        'subtotal' => $subtotal,
                        'tax_rate' => 0.12,
                        'tax_amount' => $subtotal * 0.12,
                        'delivery_fee' => 50.00,
                        'total_amount' => $subtotal + ($subtotal * 0.12) + 50.00,
                    ],
                    'unavailable_items' => $unavailableItems,
                ],
                'message' => 'Cart retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching cart: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch cart'], 500);
        }
    }

    /**
     * Add item to cart
     */
    public function addToCart(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1|max:100',
                'color' => 'nullable|string|max:100',
                'size' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:500',
                'customizations' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $product = Product::where('id', $request->product_id)
                ->where('status', 'active')
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not available'
                ], 404);
            }

            if ($product->quantity_in_stock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock. Only ' . $product->quantity_in_stock . ' items available.',
                    'max_quantity' => $product->quantity_in_stock
                ], 400);
            }

            $price = $product->discount_price ?: $product->selling_price;

            $existingCartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->where('color', $request->color)
                ->where('size', $request->size)
                ->first();

            if ($existingCartItem) {
                $newQuantity = $existingCartItem->quantity + $request->quantity;
                if ($product->quantity_in_stock < $newQuantity) {
                    $availableQuantity = $product->quantity_in_stock - $existingCartItem->quantity;
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add more. You already have ' . $existingCartItem->quantity . ' in cart. Can add up to ' . $availableQuantity . ' more.',
                        'max_additional_quantity' => $availableQuantity
                    ], 400);
                }

                $existingCartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $price,
                    'notes' => $request->notes ?? $existingCartItem->notes,
                    'customizations' => $request->customizations ?? $existingCartItem->customizations,
                ]);

                $cartItem = $existingCartItem;
            } else {
                $cartItem = Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'price' => $price,
                    'color' => $request->color,
                    'size' => $request->size,
                    'notes' => $request->notes,
                    'customizations' => $request->customizations,
                ]);
            }

            $cartItem->load(['product.primaryImage', 'product.images', 'product.owner']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'price' => (float)$cartItem->price,
                    'color' => $cartItem->color,
                    'size' => $cartItem->size,
                    'notes' => $cartItem->notes,
                    'customizations' => $cartItem->customizations,
                    'product' => $cartItem->product ? $this->sanitizeProductData($cartItem->product) : null,
                ],
                'message' => 'Item added to cart successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding to cart: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add item to cart'], 500);
        }
    }

    /**
     * Update cart item
     */
    public function updateCartItem(Request $request, $id)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1|max:100',
                'color' => 'nullable|string|max:100',
                'size' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:500',
                'customizations' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['success'=>false,'errors'=>$validator->errors()],422);
            }

            $cartItem = Cart::where('id', $id)->where('user_id', $user->id)->first();

            if (!$cartItem) return response()->json(['success'=>false,'message'=>'Cart item not found'],404);

            $product = Product::where('id', $cartItem->product_id)
                ->where('status', 'active')
                ->first();

            if (!$product) {
                return response()->json(['success'=>false,'message'=>'Product no longer available'],400);
            }

            if ($product->quantity_in_stock < $request->quantity) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Insufficient stock. Only '.$product->quantity_in_stock.' items available.',
                    'max_quantity'=>$product->quantity_in_stock
                ],400);
            }

            $price = $product->discount_price ?: $product->selling_price;

            $cartItem->update([
                'quantity'=>$request->quantity,
                'price'=>$price,
                'color'=>$request->color ?? $cartItem->color,
                'size'=>$request->size ?? $cartItem->size,
                'notes'=>$request->notes ?? $cartItem->notes,
                'customizations'=>$request->customizations ?? $cartItem->customizations,
            ]);

            $cartItem->load(['product.primaryImage', 'product.images', 'product.owner']);

            return response()->json([
                'success'=>true,
                'data'=> [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'price' => (float)$cartItem->price,
                    'color' => $cartItem->color,
                    'size' => $cartItem->size,
                    'notes' => $cartItem->notes,
                    'customizations' => $cartItem->customizations,
                    'product' => $cartItem->product ? $this->sanitizeProductData($cartItem->product) : null,
                ],
                'message'=>'Cart item updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating cart item: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to update cart item'],500);
        }
    }


    /**
     * Remove item from cart
     */
    public function removeFromCart(Request $request, $id)
    {
        try {
            $user = $request->user();

            $cartItem = Cart::where('id', $id)->where('user_id', $user->id)->first();

            if (!$cartItem) return response()->json(['success'=>false,'message'=>'Cart item not found'],404);

            $cartItem->delete();

            return response()->json(['success'=>true,'message'=>'Item removed from cart successfully']);
        } catch (\Exception $e) {
            Log::error('Error removing from cart: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to remove item from cart'],500);
        }
    }

    /**
     * Clear cart
     */
    public function clearCart(Request $request)
    {
        try {
            $user = $request->user();
            Cart::where('user_id',$user->id)->delete();

            return response()->json(['success'=>true,'message'=>'Cart cleared successfully']);
        } catch (\Exception $e) {
            Log::error('Error clearing cart: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to clear cart'],500);
        }
    }

    /**
     * Get cart summary
     */
    public function getSummary(Request $request)
    {
        try {
            $user = $request->user();
            $cartItems = Cart::where('user_id',$user->id)->with('product')->get();

            $subtotal = $cartItems->sum(fn($item)=>$item->price*$item->quantity);
            $totalItems = $cartItems->sum('quantity');
            $availableItems = $cartItems->where('is_available',true)->sum('quantity');
            $unavailableCount = $cartItems->where('is_available',false)->count();

            return response()->json([
                'success'=>true,
                'data'=>[
                    'total_items'=>$totalItems,
                    'available_items'=>$availableItems,
                    'unavailable_items'=>$unavailableCount,
                    'subtotal'=>(float)$subtotal,
                    'tax_rate'=>0.12,
                    'tax_amount'=>$subtotal*0.12,
                    'delivery_fee'=>50.00,
                    'total_amount'=>$subtotal+($subtotal*0.12)+50.00
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting cart summary: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to get cart summary'],500);
        }
    }

    /**
     * Validate cart items before checkout
     */
    public function validateCart(Request $request)
    {
        try {
            $user = $request->user();
            $cartItems = Cart::where('user_id',$user->id)->with(['product.primaryImage','product.images','product.models'])->get();

            $validItems = [];
            $invalidItems = [];
            $totalValidAmount = 0;

            foreach($cartItems as $item){
                if($item->is_available){
                    $validItems[] = $item;
                    $totalValidAmount += $item->subtotal;
                } else {
                    $invalidItems[] = [
                        'cart_item_id'=>$item->id,
                        'product_id'=>$item->product_id,
                        'product_name'=>$item->product?->product_name,
                        'reason'=>$this->getUnavailabilityReason($item),
                        'requested_quantity'=>$item->quantity,
                        'available_quantity'=>$item->product?->quantity_in_stock,
                    ];
                }
            }

            return response()->json([
                'success'=>true,
                'data'=>[
                    'valid_items'=>count($validItems),
                    'invalid_items'=>count($invalidItems),
                    'total_valid_amount'=>$totalValidAmount,
                    'invalid_items_details'=>$invalidItems,
                    'can_proceed'=>count($invalidItems)===0
                ],
                'message'=>count($invalidItems)>0?'Some items in your cart are no longer available':'All cart items are valid'
            ]);
        } catch (\Exception $e) {
            Log::error('Error validating cart: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to validate cart'],500);
        }
    }

    /**
     * Helper: Get reason for unavailability
     */
    private function getUnavailabilityReason($cartItem)
    {
        if(!$cartItem->product) return 'Product not found';
        if($cartItem->product->approval_status !== 'approved') return 'Product not approved';
        if($cartItem->product->quantity_in_stock===0) return 'Out of stock';
        if($cartItem->product->quantity_in_stock<$cartItem->quantity) return 'Insufficient stock';
        return 'Unknown reason';
    }

    /**
     * Sanitize product data
     */
     private function sanitizeProductData($product)
    {
        // Get vendor application info for store name
        $vendorStoreName = null;
        $vendorBusinessName = null;
        
        if ($product->owner && $product->owner->role === 'vendor') {
            $vendorApplication = VendorApplication::where('email', $product->owner->email)
                ->where('status', 'approved')
                ->first();
            
            if ($vendorApplication) {
                $vendorStoreName = $vendorApplication->store_name;
                $vendorBusinessName = $vendorApplication->business_name;
            }
        }

        return [
            'id' => $product->id,
            'product_name' => $product->product_name,
            'description' => $product->description,
            'category' => $product->category,
            'flower_type' => $product->flower_type,
            'color' => $product->color,
            'size' => $product->size,
            'weight' => $product->weight ?? null,
            'selling_price' => (float)$product->selling_price,
            'discount_price' => $product->discount_price ? (float)$product->discount_price : null,
            'quantity_in_stock' => $product->quantity_in_stock,
            'min_stock_level' => $product->min_stock_level,
            'care_instructions' => $product->care_instructions ?? null,
            'freshness_days' => $product->freshness_days ?? null,
            'is_featured' => $product->is_featured ?? false,
            'images' => $product->images->map(fn($image) => [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'is_primary' => (bool)$image->is_primary
            ]),
            'primary_image' => $product->primaryImage ? [
                'id' => $product->primaryImage->id,
                'image_url' => $product->primaryImage->image_url
            ] : null,
            'stock_status' => $product->stock_status ?? null,
            // Enhanced owner information with store name
            'owner' => $product->owner ? [
                'id' => $product->owner->id,
                'name' => $product->owner->name,
                'email' => $product->owner->email,
                'role' => $product->owner->role,
                'full_name' => $product->owner->name . ' ' . ($product->owner->surname ?? ''),
                'store_name' => $vendorStoreName,
                'business_name' => $vendorBusinessName,
                'display_name' => $vendorStoreName ?? $product->owner->name ?? 'Local Vendor',
            ] : null,
        ];
    }
}
