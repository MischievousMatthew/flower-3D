<?php

namespace App\Http\Controllers;

use App\Models\ProductReport;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductReportController extends Controller
{
    // ── Customer: submit a report ─────────────────────────────────────────────

    /**
     * POST /api/products/{id}/report
     */
    public function store(Request $request, $id)
    {
        try {
            $reporter = $request->user();
            $product  = Product::find($id);

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            // One report per user per product
            $exists = ProductReport::where('product_id', $id)
                ->where('reporter_id', $reporter->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this product.',
                ], 409);
            }

            $request->validate([
                'reason'      => 'required|in:counterfeit,misleading,inappropriate,prohibited,spam,other',
                'description' => 'nullable|string|max:500',
            ]);

            $report = ProductReport::create([
                'product_id'  => $id,
                'reporter_id' => $reporter->id,
                'reason'      => $request->reason,
                'description' => $request->description,
                'status'      => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data'    => $report,
                'message' => 'Product reported successfully.',
            ], 201);

        } catch (\Exception $e) {
            Log::error('ProductReport store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to submit report'], 500);
        }
    }

    // ── Admin: list all reports ───────────────────────────────────────────────

    /**
     * GET /api/admin/reports
     *
     * Query params:
     *   status  = pending | approved | rejected  (omit for all)
     *   search  = string (matches product name or reason)
     *   page, per_page
     */
    public function index(Request $request)
    {
        try {
            $query = ProductReport::with([
                // ── Product + its relationships ───────────────────────────────
                'product.primaryImage',   // for thumbnail
                'product.images',         // for gallery in modal
                'product.models',         // for 3D viewer in modal
                // ── Product owner (vendor) ────────────────────────────────────
                // We need owner to get ban_count, is_suspended, and vendor_data
                // (vendor_data JSON column contains store_name)
                'product.owner',
                // ── People ───────────────────────────────────────────────────
                'reporter',
                'reviewer',
            ])->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Search by product name or reason
            if ($request->filled('search')) {
                $search = '%' . $request->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->whereHas('product', fn ($p) => $p->where('product_name', 'like', $search))
                      ->orWhere('reason', 'like', $search);
                });
            }

            $perPage = min((int) $request->input('per_page', 20), 100);
            $reports = $query->paginate($perPage);

            // ── Transform each report to include flattened vendor / image data ─
            $reports->getCollection()->transform(function ($report) {
                $product = $report->product;
                $owner   = $product?->owner;

                // ── Vendor store_name ─────────────────────────────────────────
                // Priority: vendor_data JSON on user → vendor_data.store_name
                // The users table stores {"store_name":"Flower Flux De Luxe",...}
                $vendorData  = $owner?->vendor_data;
                $storeName   = null;

                if (is_array($vendorData)) {
                    $storeName = $vendorData['store_name'] ?? null;
                } elseif (is_string($vendorData)) {
                    // In case it's stored as a JSON string (not cast)
                    $decoded   = json_decode($vendorData, true);
                    $storeName = $decoded['store_name'] ?? null;
                }

                // Fallback chain: store_name → owner name → null
                $report->vendor_store_name = $storeName
                    ?? $owner?->name
                    ?? null;

                // ── Vendor ban info ───────────────────────────────────────────
                $report->vendor_ban_count  = $owner?->ban_count  ?? 0;
                $report->vendor_suspended  = $owner?->is_suspended ?? false;

                // ── Primary image (convenience field) ────────────────────────
                // Attach primary_image directly on the product so the template
                // can use report.product.primary_image.image_url
                if ($product && !isset($product->primary_image)) {
                    $product->primary_image =
                        $product->primaryImage          // via relationship
                        ?? $product->images?->firstWhere('is_primary', true)
                        ?? $product->images?->first()
                        ?? null;
                }

                return $report;
            });

            return response()->json([
                'success' => true,
                'data'    => $reports,
            ]);

        } catch (\Exception $e) {
            Log::error('ProductReport index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch reports'], 500);
        }
    }

    // ── Admin: approve (ban) or reject (dismiss) a report ────────────────────

    /**
     * POST /api/admin/reports/{id}/review
     * Body: { "action": "approve" | "reject" }
     */
    public function review(Request $request, $id)
    {
        try {
            $report = ProductReport::with(['product.owner'])->find($id);

            if (!$report) {
                return response()->json(['success' => false, 'message' => 'Report not found'], 404);
            }

            if ($report->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This report has already been reviewed.',
                ], 422);
            }

            $request->validate(['action' => 'required|in:approve,reject']);

            $admin = $request->user();

            // ── Use DB::table() to bypass fillable entirely ──────────────────
            // This avoids 500s caused by missing columns in $fillable or
            // columns that don't exist yet in the migration.
            $updateData = [
                'status'     => $request->action === 'approve' ? 'approved' : 'rejected',
                'updated_at' => now(),
            ];

            // Only set reviewer fields if the columns exist
            try {
                \DB::table('product_reports')
                    ->where('id', $id)
                    ->update(array_merge($updateData, [
                        'reviewer_id' => $admin?->id,
                        'reviewed_at' => now(),
                    ]));
            } catch (\Exception $e) {
                // reviewer_id / reviewed_at columns may not exist — update without them
                \DB::table('product_reports')
                    ->where('id', $id)
                    ->update($updateData);
            }

            $report->refresh();

            $vendorSuspended = false;
            $vendorBanCount  = 0;

            if ($request->action === 'approve') {
                $product = $report->product;
                $owner   = $product?->owner;

                if ($product) {
                    \DB::table('products')
                        ->where('id', $product->id)
                        ->update(['status' => 'banned', 'updated_at' => now()]);
                }

                if ($owner) {
                    \DB::table('users')
                        ->where('id', $owner->id)
                        ->increment('ban_count');

                    $owner->refresh();
                    $vendorBanCount = $owner->ban_count;

                    if ($owner->ban_count >= 3 && !$owner->is_suspended) {
                        \DB::table('users')
                            ->where('id', $owner->id)
                            ->update(['is_suspended' => true, 'updated_at' => now()]);
                        $vendorSuspended = true;
                    }
                }
            }

            return response()->json([
                'success'          => true,
                'message'          => $request->action === 'approve'
                    ? 'Product has been banned.'
                    : 'Report has been dismissed.',
                'vendor_suspended' => $vendorSuspended,
                'vendor_ban_count' => $vendorBanCount,
            ]);

        } catch (\Exception $e) {
            \Log::error('ProductReport review error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to review report: ' . $e->getMessage(),
            ], 500);
        }
    }
}