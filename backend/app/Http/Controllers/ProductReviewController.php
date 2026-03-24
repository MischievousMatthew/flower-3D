<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductReviewController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC — GET /api/products/{productId}/reviews
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * All reviews + rating summary for a product.
     * Public — no auth required.
     */
    public function index(Request $request, int $productId): JsonResponse
    {
        // withTrashed so soft-deleted products can still show their reviews
        $product = Product::withTrashed()->find($productId);

        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        // Support both param styles:
        //   ?sort=newest|oldest|highest|lowest  (existing ProductReviews.vue)
        //   ?sort_by=created_at&sort_dir=desc   (new ProductReviews.vue)
        [$orderCol, $orderDir] = $this->resolveSort($request);

        $reviews = ProductReview::with('user:id,name')
            ->where('product_id', $productId)
            ->orderBy($orderCol, $orderDir)
            ->paginate($request->input('per_page', 10));

        // ── THE FIX: merge sold_count so the shop modal shows real numbers ──
        $summary = array_merge(
            $this->buildSummary($productId),
            ['sold_count' => $this->soldCount($productId)]
        );

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data'    => collect($reviews->items())->map(fn ($r) => $this->formatReview($r)),
            'meta'    => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'total'        => $reviews->total(),
                'per_page'     => $reviews->perPage(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CUSTOMER — POST /api/customer/products/{productId}/review
    // ═══════════════════════════════════════════════════════════════════════════

    public function store(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'order_id'     => ['required', 'integer'],
            'rating'       => ['required', 'integer', 'min:1', 'max:5'],
            'comment'      => ['nullable', 'string', 'max:2000'],
            'image'        => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        // ── Find product (include soft-deleted so older order items still work) ──
        $product = Product::withTrashed()->find($productId);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        // ── Eligibility checks ────────────────────────────────────────────────

        // 1. Order exists and belongs to user
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or does not belong to you.',
            ], 422);
        }

        // 2. Order delivery must be completed
        //    Support both: order.status === 'completed'
        //    OR order.delivery.status === 'completed'
        $deliveryCompleted = false;

        if ($order->status === 'completed') {
            $deliveryCompleted = true;
        } else {
            // Check via delivery table if it exists
            $deliveryStatus = DB::table('deliveries')
                ->where('order_id', $order->id)
                ->value('status');

            if ($deliveryStatus === 'completed') {
                $deliveryCompleted = true;
            }
        }

        if (! $deliveryCompleted) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review products from completed orders.',
            ], 422);
        }

        // 3. Order is not returned or refunded
        $hasReturnRefund = DB::table('order_requests')
            ->where('order_id', $order->id)
            ->where('status', 'approved')
            ->whereIn('type', ['return', 'refund'])
            ->exists();

        if ($hasReturnRefund) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews are not available for returned or refunded orders.',
            ], 422);
        }

        // 4. Product was actually in this order
        $inOrder = DB::table('order_items')
            ->where('order_id', $order->id)
            ->where('product_id', $productId)
            ->exists();

        if (! $inOrder) {
            return response()->json([
                'success' => false,
                'message' => 'This product was not part of the specified order.',
            ], 422);
        }

        // 5. No duplicate review for this product + order
        $exists = ProductReview::where('product_id', $productId)
            ->where('user_id', $user->id)
            ->where('order_id', $order->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product for this order.',
            ], 422);
        }

        // ── Store image ───────────────────────────────────────────────────────

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')
                ->store('reviews', 'public');
        }

        // ── Create ────────────────────────────────────────────────────────────

        $review = ProductReview::create([
            'product_id'   => $productId,
            'user_id'      => $user->id,
            'order_id'     => $order->id,
            'rating'       => $request->rating,
            'comment'      => $request->comment,
            'image_path'   => $imagePath,
            'is_anonymous' => (bool) $request->input('is_anonymous', false),
        ]);

        $review->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data'    => $this->formatReview($review),
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CUSTOMER — PUT /api/reviews/{id}
    // ═══════════════════════════════════════════════════════════════════════════

    public function update(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $review = ProductReview::where('id', $id)
            ->where('user_id', $user?->id)
            ->first();

        if (! $review) {
            return response()->json(['success' => false, 'message' => 'Review not found.'], 404);
        }

        $request->validate([
            'rating'       => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'comment'      => ['nullable', 'string', 'max:2000'],
            'image'        => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        // Handle image replacement / removal
        if ($request->boolean('remove_image') && $review->image_path) {
            Storage::disk('public')->delete($review->image_path);
            $review->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($review->image_path) {
                Storage::disk('public')->delete($review->image_path);
            }
            $review->image_path = $request->file('image')->store('reviews', 'public');
        }

        $review->fill($request->only(['rating', 'comment', 'is_anonymous']));
        $review->save();
        $review->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Review updated.',
            'data'    => $this->formatReview($review),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CUSTOMER — DELETE /api/reviews/{id}
    // ═══════════════════════════════════════════════════════════════════════════

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $review = ProductReview::where('id', $id)
            ->where('user_id', $user?->id)
            ->first();

        if (! $review) {
            return response()->json(['success' => false, 'message' => 'Review not found.'], 404);
        }

        if ($review->image_path) {
            Storage::disk('public')->delete($review->image_path);
        }

        $review->delete();

        return response()->json(['success' => true, 'message' => 'Review deleted.']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CUSTOMER — GET /api/customer/reviewable-products
    // Returns products the user can review (completed orders, not yet reviewed)
    // ═══════════════════════════════════════════════════════════════════════════

    public function reviewableProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Completed order IDs — check both order.status and delivery.status
        $completedOrderIds = Order::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('status', 'completed')
                  ->orWhereIn('id', function ($sub) {
                      $sub->select('order_id')
                          ->from('deliveries')
                          ->where('status', 'completed');
                  });
            })
            ->whereNotIn('id', function ($q) {
                $q->select('order_id')
                  ->from('order_requests')
                  ->where('status', 'approved')
                  ->whereIn('type', ['return', 'refund']);
            })
            ->pluck('id');

        if ($completedOrderIds->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Already reviewed combos
        $reviewed = ProductReview::where('user_id', $user->id)
            ->whereIn('order_id', $completedOrderIds)
            ->select('product_id', 'order_id', 'id as review_id', 'rating')
            ->get()
            ->map(fn ($r) => $r->product_id . '_' . $r->order_id)
            ->toArray();

        // All items from those completed orders
        $items = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->whereIn('oi.order_id', $completedOrderIds)
            ->select(
                'oi.product_id',
                'oi.order_id',
                'p.product_name as product_name',
                DB::raw("COALESCE(
                    (SELECT pi.image_url FROM product_images pi
                     WHERE pi.product_id = oi.product_id
                     ORDER BY pi.is_primary DESC, pi.id ASC
                     LIMIT 1), ''
                ) as product_image"),
                'o.order_number',
                'o.created_at as order_date'
            )
            ->distinct()
            ->get();

        $result = $items->map(function ($item) use ($reviewed, $user) {
            $key        = $item->product_id . '_' . $item->order_id;
            $alreadyRev = in_array($key, $reviewed);

            $existingReview = null;
            if ($alreadyRev) {
                $existingReview = ProductReview::where('product_id', $item->product_id)
                    ->where('order_id', $item->order_id)
                    ->where('user_id', $user->id)
                    ->first();
            }

            return [
                'product_id'       => $item->product_id,
                'product_name'     => $item->product_name,
                'product_image'    => $item->product_image,
                'order_id'         => $item->order_id,
                'order_number'     => $item->order_number,
                'order_date'       => $item->order_date,
                'already_reviewed' => $alreadyRev,
                'review'           => $existingReview ? $this->formatReview($existingReview) : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // VENDOR — GET /api/vendor/products/{productId}/reviews
    // ═══════════════════════════════════════════════════════════════════════════

    public function vendorProductReviews(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();

        // Verify this product belongs to the vendor
        $product = Product::withTrashed()
            ->where('id', $productId)
            ->where(function ($q) use ($user) {
                $q->where('vendor_id', $user->id)
                  ->orWhere('owner_id', $user->id);
            })
            ->first();

        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        [$orderCol, $orderDir] = $this->resolveSort($request);

        $reviews = ProductReview::with('user:id,name')
            ->where('product_id', $productId)
            ->orderBy($orderCol, $orderDir)
            ->paginate($request->input('per_page', 15));

        $summary   = $this->buildSummary($productId);
        $soldCount = $this->soldCount($productId);

        return response()->json([
            'success' => true,
            'summary' => array_merge($summary, ['sold_count' => $soldCount]),
            'data'    => collect($reviews->items())->map(fn ($r) => $this->formatReview($r)),
            'meta'    => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'total'        => $reviews->total(),
                'per_page'     => $reviews->perPage(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    private function buildSummary(int $productId): array
    {
        $base = ProductReview::where('product_id', $productId);

        $total   = (clone $base)->count();
        $average = $total ? round((clone $base)->avg('rating'), 1) : 0;

        $breakdown = (clone $base)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Ensure all 5 stars are present
        $stars = [];
        for ($i = 5; $i >= 1; $i--) {
            $stars[$i] = (int) ($breakdown[$i] ?? 0);
        }

        // Build breakdown with both count and percentage
        // (supports both old Vue component reading breakdown[n].percentage
        //  and new component reading stars[n])
        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = $stars[$i];
            $breakdown[$i] = [
                'count'      => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        }

        return [
            'average_rating' => (float) $average,
            'total_reviews'  => $total,
            'stars'          => $stars,       // new component uses this
            'breakdown'      => $breakdown,   // old component uses this
        ];
    }

    private function soldCount(int $productId): int
    {
        return (int) DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.product_id', $productId)
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
            ->sum('oi.quantity');
    }

    private function formatReview(ProductReview $r): array
    {
        return [
            'id'            => $r->id,
            'product_id'    => $r->product_id,
            'order_id'      => $r->order_id,
            'rating'        => $r->rating,
            'comment'       => $r->comment,
            'image_url'     => $r->image_path ? Storage::url($r->image_path) : null,
            'is_anonymous'  => $r->is_anonymous,
            'reviewer_name' => $r->is_anonymous ? 'Anonymous User' : ($r->user?->name ?? 'Customer'),
            // Alias so both old and new Vue components work regardless of which field they read
            'display_name'  => $r->is_anonymous ? 'Anonymous User' : ($r->user?->name ?? 'Customer'),
            'created_at'    => $r->created_at?->toIso8601String(),
            'updated_at'    => $r->updated_at?->toIso8601String(),
        ];
    }
    /**
     * Resolve sort column and direction from the request.
     *
     * Supports two calling conventions:
     *   1. ?sort=newest|oldest|highest|lowest   (your existing ProductReviews.vue)
     *   2. ?sort_by=created_at&sort_dir=desc    (new ProductReviews.vue)
     *
     * @return array{string, string}  [$column, $direction]
     */
    private function resolveSort(\Illuminate\Http\Request $request): array
    {
        // Convention 1 — named sort preset
        $preset = $request->input('sort');
        if ($preset) {
            return match ($preset) {
                'oldest'  => ['created_at', 'asc'],
                'highest' => ['rating',     'desc'],
                'lowest'  => ['rating',     'asc'],
                default   => ['created_at', 'desc'], // newest
            };
        }

        // Convention 2 — explicit column + direction
        $col = $request->input('sort_by', 'created_at');
        $dir = $request->input('sort_dir', 'desc');

        // Whitelist allowed columns to prevent SQL injection
        $allowedCols = ['created_at', 'rating'];
        $col = in_array($col, $allowedCols, true) ? $col : 'created_at';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        return [$col, $dir];
    }

}