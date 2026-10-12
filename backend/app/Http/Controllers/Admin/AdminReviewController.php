<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CloudinaryHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\ProductReview;
use App\Support\VendorDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Review management: customer reviews and reported listings.
 * Admins can hide or restore a review (with a reason) but never edit its
 * rating or text, and never delete it.
 */
class AdminReviewController extends Controller
{
    /** GET /api/admin/reviews/summary */
    public function summary(): JsonResponse
    {
        $shown = ProductReview::query()->whereNull('hidden_at');

        return response()->json(['success' => true, 'data' => [
            'reviews' => ProductReview::query()->count(),
            'average_rating' => round((float) (clone $shown)->avg('rating'), 2),
            'hidden' => ProductReview::query()->whereNotNull('hidden_at')->count(),
            'low_ratings_30d' => (clone $shown)->where('rating', '<=', 2)->where('created_at', '>=', now()->subDays(30))->count(),
            'new_7d' => ProductReview::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'stars' => collect(range(5, 1))->mapWithKeys(fn ($n) => [$n => (clone $shown)->where('rating', $n)->count()]),
            'pending_reports' => ProductReport::query()->where('status', 'pending')->count(),
        ]]);
    }

    /** GET /api/admin/reviews?search=&rating=&status=shown|hidden&photo=1&page= */
    public function index(Request $request): JsonResponse
    {
        $query = ProductReview::query()
            ->with(['user:id,name,surname', 'order:id,order_number', 'product' => fn ($q) => $q->withTrashed()->select('id', 'product_name', 'owner_id')->with('primaryImage')])
            ->latest()->orderByDesc('id');

        if ($rating = (int) $request->input('rating')) {
            $query->where('rating', $rating);
        }
        if ($request->input('status') === 'hidden') {
            $query->whereNotNull('hidden_at');
        } elseif ($request->input('status') === 'shown') {
            $query->whereNull('hidden_at');
        }
        if ($request->boolean('photo')) {
            $query->whereNotNull('image_path');
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $vendorIds = VendorDirectory::idsMatching($search);
            $query->where(function ($q) use ($like, $vendorIds) {
                $q->whereRaw('lower(comment) like ?', [$like])
                    ->orWhereHas('product', fn ($p) => $p->withTrashed()->whereRaw('lower(product_name) like ?', [$like])
                        ->when($vendorIds->isNotEmpty(), fn ($p) => $p->orWhereIn('owner_id', $vendorIds)));
            });
        }

        $page = $query->paginate(min(max((int) $request->input('per_page', 20), 5), 50));
        $vendors = VendorDirectory::details($page->getCollection()->pluck('product.owner_id')->filter());
        $admins = $this->adminNames($page->getCollection()->pluck('hidden_by'));

        return response()->json([
            'success' => true,
            'data' => $page->getCollection()->map(fn (ProductReview $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'image_url' => $r->image_path ? CloudinaryHelper::getUrl($r->image_path) : null,
                'is_anonymous' => $r->is_anonymous,
                // Admins see who wrote it (name only); shoppers see "Anonymous"
                'reviewer' => trim(($r->user?->name ?? 'Customer') . ' ' . ($r->user?->surname ?? '')),
                'order_number' => $r->order?->order_number,
                'created_at' => $r->created_at?->toIso8601String(),
                'edited' => $r->updated_at && $r->created_at && $r->updated_at->gt($r->created_at->addMinute()) && !$r->hidden_at,
                'product' => [
                    'id' => $r->product_id,
                    'name' => $r->product?->product_name ?? 'Removed product',
                    'image' => $r->product?->primaryImage?->image_url,
                ],
                'vendor' => $vendors[$r->product?->owner_id] ?? VendorDirectory::unknown($r->product?->owner_id),
                'hidden' => $r->hidden_at ? [
                    'at' => $r->hidden_at->toIso8601String(),
                    'reason' => $r->hidden_reason,
                    'by' => $admins[$r->hidden_by] ?? 'An admin',
                ] : null,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** POST /api/admin/reviews/{id}/hide { reason } */
    public function hide(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $review = ProductReview::query()->findOrFail($id);

        if ($review->hidden_at) {
            return response()->json(['success' => false, 'message' => 'This review is already hidden.'], 422);
        }

        // Saved without touching updated_at, so it never looks like the customer edited it
        $review->timestamps = false;
        $review->forceFill(['hidden_at' => now(), 'hidden_reason' => $data['reason'], 'hidden_by' => $request->user()->id])->save();

        return response()->json(['success' => true, 'message' => 'Review hidden. It no longer shows on the shop or counts towards ratings.']);
    }

    /** POST /api/admin/reviews/{id}/restore */
    public function restore(int $id): JsonResponse
    {
        $review = ProductReview::query()->findOrFail($id);

        if (!$review->hidden_at) {
            return response()->json(['success' => false, 'message' => 'This review is already showing.'], 422);
        }

        $review->timestamps = false;
        $review->forceFill(['hidden_at' => null, 'hidden_reason' => null, 'hidden_by' => null])->save();

        return response()->json(['success' => true, 'message' => 'Review restored.']);
    }

    /** GET /api/admin/reviews/reports?status=pending|approved|rejected&search=&page= */
    public function reports(Request $request): JsonResponse
    {
        $query = ProductReport::query()
            ->with(['reporter:id,name,surname', 'reviewer:id,name,surname'])
            ->latest()->orderByDesc('id');

        if (in_array($request->input('status'), ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $request->input('status'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(fn ($q) => $q->whereRaw('lower(reason) like ?', [$like])
                ->orWhereRaw('lower(description) like ?', [$like])
                ->orWhereIn('product_id', Product::withTrashed()->whereRaw('lower(product_name) like ?', [$like])->select('id')));
        }

        $page = $query->paginate(min(max((int) $request->input('per_page', 20), 5), 50));
        $products = Product::withTrashed()->with('primaryImage')
            ->whereIn('id', $page->getCollection()->pluck('product_id'))
            ->get(['id', 'product_name', 'owner_id', 'moderation_status'])->keyBy('id');
        $vendors = VendorDirectory::details($products->pluck('owner_id'));
        $strikes = \App\Models\User::query()->whereIn('id', $products->pluck('owner_id'))->pluck('ban_count', 'id');
        $reportCounts = ProductReport::query()->whereIn('product_id', $products->keys())
            ->selectRaw('product_id, count(*) as total')->groupBy('product_id')->pluck('total', 'product_id');

        return response()->json([
            'success' => true,
            'data' => $page->getCollection()->map(function (ProductReport $r) use ($products, $vendors, $strikes, $reportCounts) {
                $product = $products[$r->product_id] ?? null;

                return [
                    'id' => $r->id,
                    'reason' => $r->reason,
                    'description' => $r->description,
                    'status' => $r->status,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'reviewed_at' => $r->reviewed_at?->toIso8601String(),
                    'reviewed_by' => $r->reviewer ? trim($r->reviewer->name . ' ' . $r->reviewer->surname) : null,
                    'reporter' => $r->reporter ? trim($r->reporter->name . ' ' . $r->reporter->surname) : 'Customer',
                    'product' => [
                        'id' => $r->product_id,
                        'name' => $product?->product_name ?? 'Removed product',
                        'image' => $product?->primaryImage?->image_url,
                        'moderation_status' => $product?->moderation_status,
                        'reports' => (int) ($reportCounts[$r->product_id] ?? 1),
                    ],
                    'vendor' => $vendors[$product?->owner_id] ?? VendorDirectory::unknown($product?->owner_id),
                    'vendor_strikes' => (int) ($strikes[$product?->owner_id] ?? 0),
                ];
            }),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'counts' => ProductReport::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    private function adminNames(Collection $ids): Collection
    {
        $ids = $ids->filter()->unique();

        return $ids->isEmpty() ? collect() : \App\Models\User::query()->whereIn('id', $ids)->get(['id', 'name', 'surname'])
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->name . ' ' . $u->surname)]);
    }
}
