<?php

namespace App\Http\Controllers;

use App\Models\OrderRequest;
use App\Models\Order;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Traits\ScopesOwner;
use App\Helpers\CloudinaryHelper;
use Illuminate\Support\Facades\Storage;


class OrderRequestController extends Controller
{
    use ScopesOwner;

    /**
     * GET /api/sc/order-requests
     * Returns all return/refund requests scoped to this SC coordinator's vendor.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type'     => ['nullable', 'string', 'in:return,refund'],
                'status'   => ['nullable', 'string', 'in:pending,approved,rejected'],
                'search'   => ['nullable', 'string', 'max:100'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page'     => ['nullable', 'integer', 'min:1'],
            ]);

            $ownerId = $this->getOwnerId();

            $query = OrderRequest::with([
                    'order',
                    'user:id,name,email',
                ])
                ->when($ownerId, fn ($q) =>
                    $q->whereHas('order', fn ($oq) =>
                        $oq->where('vendor_id', $ownerId)
                    )
                )
                ->when($request->filled('type'),   fn ($q) => $q->where('type',   $request->type))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $s = $request->search;
                    $q->where(function ($inner) use ($s) {
                        $inner->whereHas('order', fn ($oq) =>
                                $oq->where('order_number', 'LIKE', "%{$s}%")
                            )
                            ->orWhereHas('user', fn ($uq) =>
                                $uq->where('name', 'LIKE', "%{$s}%")
                                   ->orWhere('email', 'LIKE', "%{$s}%")
                            );
                    });
                })
                ->orderByDesc('created_at');

            $paginated = $query->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data'    => $paginated->items()
                    ? array_map([$this, 'formatRequest'], $paginated->items())
                    : [],
                'meta'    => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                ],
                'counts'  => $this->requestCounts($ownerId),
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[OrderRequestController] index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Failed to load requests.',
            ], 500);
        }
    }

    /**
     * POST /api/sc/order-requests/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $orderRequest = $this->findOwnedRequest($id);
        if (! $orderRequest) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }

        if ($orderRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Request is already {$orderRequest->status}.",
            ], 422);
        }

        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $orderRequest->update([
            'status'      => 'approved',
            'admin_notes' => $request->admin_notes,
        ]);

        // Sync order + delivery statuses
        $order    = $orderRequest->order;
        $delivery = $order?->delivery;

        if ($order && $delivery) {
            if ($orderRequest->type === 'return') {
                $delivery->update(['status' => 'returned', 'last_scanned_at' => now()]);
                $delivery->logs()->create([
                    'status'     => 'returned',
                    'scanned_at' => now(),
                    'notes'      => 'Return approved by SC coordinator.',
                ]);
                $order->update(['status' => 'returned']);
            } elseif ($orderRequest->type === 'refund') {
                $delivery->update(['status' => 'refunded', 'last_scanned_at' => now()]);
                $delivery->logs()->create([
                    'status'     => 'refunded',
                    'scanned_at' => now(),
                    'notes'      => 'Refund approved by SC coordinator.',
                ]);
                $order->update(['status' => 'refunded', 'payment_status' => 'refunded']);
            }
        }

        Log::info('[OrderRequest] approved', [
            'id'      => $id,
            'type'    => $orderRequest->type,
            'by'      => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($orderRequest->type) . ' request approved.',
            'data'    => $this->formatRequest($orderRequest->fresh(['order.delivery', 'user'])),
        ]);
    }

    /**
     * POST /api/sc/order-requests/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $orderRequest = $this->findOwnedRequest($id);
        if (! $orderRequest) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }

        if ($orderRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Request is already {$orderRequest->status}.",
            ], 422);
        }

        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $orderRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        Log::info('[OrderRequest] rejected', [
            'id'   => $id,
            'type' => $orderRequest->type,
            'by'   => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($orderRequest->type) . ' request rejected.',
            'data'    => $this->formatRequest($orderRequest->fresh(['order', 'user'])),
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────



    private function findOwnedRequest(int $id): ?OrderRequest
    {
        $ownerId = $this->getOwnerId();

        return OrderRequest::with(['order', 'user'])
            ->when($ownerId, fn ($q) =>
                $q->whereHas('order', fn ($oq) =>
                    $oq->where('vendor_id', $ownerId)
                )
            )
            ->find($id);
    }

    private function requestCounts(?int $ownerId): array
    {
        $base = OrderRequest::when($ownerId, fn ($q) =>
            $q->whereHas('order', fn ($oq) =>
                $oq->where('vendor_id', $ownerId)
            )
        );

        return [
            'all'      => (clone $base)->count(),
            'pending'  => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'returns'  => (clone $base)->where('type', 'return')->count(),
            'refunds'  => (clone $base)->where('type', 'refund')->count(),
        ];
    }

    private function formatRequest(OrderRequest $r): array
    {
        $order = $r->order;

        // Load delivery separately to avoid nested eager-load column constraint issues
        $deliveryStatus = null;
        if ($order) {
            $deliveryStatus = \App\Models\Delivery::where('order_id', $order->id)
                ->value('status');
        }

        return [
            'id'           => $r->id,
            'type'         => $r->type,
            'reason'       => $r->reason,
            'media_path'   => $r->media_path,
            'media_url'    => $r->media_path
                                ? CloudinaryHelper::getUrl($r->media_path, $r->media_type)
                                : null,
            'media_type'   => $r->media_type,
            'status'       => $r->status,
            'admin_notes'  => $r->admin_notes,
            'created_at'   => $r->created_at?->toIso8601String(),

            'customer' => $r->user ? [
                'id'    => $r->user->id,
                'name'  => $r->user->name,
                'email' => $r->user->email,
            ] : null,

            'order' => $order ? [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount'   => (float) ($order->total_amount ?? 0),
            ] : null,

            'delivery_status' => $deliveryStatus,
        ];
    }
}