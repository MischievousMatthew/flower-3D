<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class DeliveryService
{
    private const VENDOR_TO_DELIVERY_STATUS_MAP = [
        'processing' => 'pending',
        'delivered' => 'to_received',
        'completed' => 'completed',
        'refunded' => 'refunded',
    ];

    // ─── Delivery Creation ─────────────────────────────────────────────────

    /**
     * Create a delivery record for an order and assign a scannable barcode.
     * Called automatically when an order moves to "processing" status.
     */
    public function createForOrder(int $orderId): Delivery
    {
        return DB::transaction(function () use ($orderId) {
            // Idempotent — don't double-create
            $existing = Delivery::where('order_id', $orderId)->first();
            if ($existing) return $existing;

            $order      = Order::findOrFail($orderId);
            $seq        = str_pad($orderId, 6, '0', STR_PAD_LEFT);
            $deliveryId = 'DLV-' . $seq;
            $barcode    = 'DLV-' . strtoupper(Str::random(10));

            return Delivery::create([
                'owner_id'    => $order->vendor_id,
                'delivery_id' => $deliveryId,
                'order_id'    => $orderId,
                'status'      => 'pending',
                'barcode'     => $barcode,
            ]);
        });
    }

    // ─── Scan Processing ───────────────────────────────────────────────────

    /**
     * Process a barcode/QR scan.
     *
     * $barcodeValue — the raw string read by the scanner
     * $scannerPage  — one of: 'to_process' | 'to_ship' | 'to_receive' | 'completed'
     *
     * Returns the updated delivery with fresh order data.
     *
     * @throws RuntimeException on invalid transition or delivery not found
     */
    public function processScan(string $barcodeValue, string $scannerPage): Delivery
    {
        return DB::transaction(function () use ($barcodeValue, $scannerPage) {
            // ── 1. Find delivery ───────────────────────────────────────────────
            $delivery = Delivery::where('barcode', $barcodeValue)
                ->orWhere('delivery_id', $barcodeValue)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                throw new RuntimeException("No delivery found for barcode: {$barcodeValue}");
            }

            // ── 2. Resolve which status this scanner page advances to ──────────
            $targetStatus = Delivery::SCANNER_PAGE_TARGET[$scannerPage]
                ?? throw new RuntimeException("Unknown scanner page: {$scannerPage}");

            // ── 3. Validate the sequential prerequisite ────────────────────────
            //   e.g. you cannot scan "To Ship" if the order is still "Pending"
            $this->assertSequentialScan($delivery, $targetStatus);

            // ── 4. Apply the transition ────────────────────────────────────────
            $scannedBy = Auth::id();
            $now       = now();

            $delivery->update([
                'status'          => $targetStatus,
                'last_scanned_by' => $scannedBy,
                'last_scanned_at' => $now,
            ]);

            // ── 5. Append audit log entry ──────────────────────────────────────
            DeliveryLog::create([
                'owner_id'    => $delivery->owner_id,
                'delivery_id' => $delivery->id,
                'status'      => $targetStatus,
                'scanned_by'  => $scannedBy,
                'scanned_at'  => $now,
                'notes'       => "Scanned via [{$scannerPage}] page",
            ]);

            // ── 6. Mirror status onto the linked Order ─────────────────────────
            $this->syncOrderStatus($delivery);

            $delivery->load(['order.items', 'logs.scanner', 'scanner']);

            return $delivery;
        });
    }

    /**
     * Enforce strict sequential advancement.
     * Throws a user-friendly RuntimeException when a stage is skipped.
     */
    private function assertSequentialScan(Delivery $delivery, string $targetStatus): void
    {
        // canTransitionTo already encodes the FSM
        if ($delivery->canTransitionTo($targetStatus)) {
            return; // all good
        }

        $labels      = Delivery::STATUS_LABELS;
        $currentLabel = $labels[$delivery->status] ?? $delivery->status;
        $targetLabel  = $labels[$targetStatus]     ?? $targetStatus;

        // Look up what status IS required to reach this target
        $required        = Delivery::SCAN_PREREQUISITE[$targetStatus] ?? null;
        $requiredLabel   = $required ? ($labels[$required] ?? $required) : 'the previous stage';

        if ($delivery->status === $targetStatus) {
            throw new \RuntimeException(
                "This order is already at " . $currentLabel . ". No action taken."
            );
        }

        throw new \RuntimeException(
            "Cannot scan to " . $targetLabel . ": order must be " . $requiredLabel . " first (currently " . $currentLabel . ")."
        );
    }

    /**
     * Advance delivery to a specific status directly (admin / override use).
     */
    public function advanceStatus(Delivery $delivery, string $targetStatus): Delivery
    {
        if (! $delivery->canTransitionTo($targetStatus)) {
            throw new RuntimeException(
                "Invalid transition: {$delivery->status} → {$targetStatus}"
            );
        }

        return DB::transaction(function () use ($delivery, $targetStatus) {
            $scannedBy = Auth::id();
            $now       = now();

            $delivery->update([
                'status'          => $targetStatus,
                'last_scanned_by' => $scannedBy,
                'last_scanned_at' => $now,
            ]);

            DeliveryLog::create([
                'delivery_id' => $delivery->id,
                'status'      => $targetStatus,
                'scanned_by'  => $scannedBy,
                'scanned_at'  => $now,
            ]);

            $this->syncOrderStatus($delivery);

            return $delivery->fresh(['order', 'logs.scanner']);
        });
    }

    /**
     * Mirror a vendor-side order status update onto the delivery lifecycle.
     * This keeps vendor and supply-chain flows interchangeable.
     */
    public function syncFromVendorOrderStatus(Order $order, string $orderStatus): ?Delivery
    {
        $deliveryStatus = self::VENDOR_TO_DELIVERY_STATUS_MAP[$orderStatus] ?? null;

        if (! $deliveryStatus) {
            return null;
        }

        return DB::transaction(function () use ($order, $deliveryStatus, $orderStatus) {
            $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();

            if (! $delivery) {
                $delivery = $this->createForOrder($order->id);
            }

            if ($delivery->status !== $deliveryStatus) {
                $delivery->update([
                    'status' => $deliveryStatus,
                    'last_scanned_by' => Auth::id(),
                    'last_scanned_at' => now(),
                ]);

                DeliveryLog::create([
                    'owner_id' => $delivery->owner_id,
                    'delivery_id' => $delivery->id,
                    'status' => $deliveryStatus,
                    'scanned_by' => Auth::id(),
                    'scanned_at' => now(),
                    'notes' => "Synced from vendor order status [{$orderStatus}]",
                ]);
            }

            return $delivery->fresh(['order', 'logs.scanner']);
        });
    }

    // ─── Private Helpers ───────────────────────────────────────────────────

    // resolveTargetStatus removed — logic moved into SCANNER_PAGE_TARGET const on Delivery model

    /**
     * Push the delivery status change onto the linked order.
     */
    private function syncOrderStatus(Delivery $delivery): void
    {
        $orderStatus = Delivery::ORDER_STATUS_MAP[$delivery->status] ?? null;
        if (! $orderStatus) return;

        try {
            $order = Order::with('items')->find($delivery->order_id);
            if (! $order) {
                return;
            }

            match ($orderStatus) {
                'processing' => $order->status !== 'processing'
                    ? $order->markAsProcessing()
                    : null,
                'delivered' => $order->status !== 'delivered'
                    ? $order->update(['status' => 'delivered', 'updated_at' => now()])
                    : null,
                'completed' => $order->status !== 'completed'
                    ? $order->markAsCompleted()
                    : null,
                'refunded' => $order->status !== 'refunded'
                    ? $order->markAsRefunded()
                    : null,
                'returned' => $order->status !== 'returned'
                    ? $order->update(['status' => 'returned', 'updated_at' => now()])
                    : null,
                default => $order->update(['status' => $orderStatus, 'updated_at' => now()]),
            };
        } catch (\Throwable $e) {
            // Non-fatal — log and continue; delivery scan already succeeded
            Log::warning("DeliveryService: failed to sync order status for order {$delivery->order_id}: {$e->getMessage()}");
        }
    }

    // ─── Queries ───────────────────────────────────────────────────────────

    /**
     * All deliveries visible to a Vendor (only their orders).
     */
    public function forVendor(int $vendorId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Delivery::with(['order.items'])
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->when(
                ! empty($filters['status']) && $filters['status'] !== 'all',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where(function ($q) use ($filters) {
                    $q->where('delivery_id', 'like', "%{$filters['search']}%")
                      ->orWhereHas('order', fn ($oq) => $oq->where('id', 'like', "%{$filters['search']}%"));
                })
            )
            ->orderByDesc('updated_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * All deliveries for the SC Coordinator, scoped to their vendor owner.
     *
     * Auth chain:
     *   Employee logs in via EmployeeAuthController → token issued for Employee model
     *   employees.owner_id  →  orders.vendor_id
     *
     * Auto-creates delivery records for any orders that don't have one yet,
     * so existing orders are visible without any manual trigger.
     */
    public function forSCCoordinator(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        // ── 1. Resolve owner_id ────────────────────────────────────────────────
        $ownerId = null;
        $authUser = Auth::user();

        if ($authUser instanceof \App\Models\Employee) {
            // Employee model is directly authenticated (most likely scenario)
            $ownerId = $authUser->owner_id;
        } else {
            // Fallback: look up employee by auth ID in the employees table
            $employee = \App\Models\Employee::where('id', Auth::id())->first();
            if ($employee) {
                $ownerId = $employee->owner_id;
            }
        }

        Log::info('[DeliveryService] forSCCoordinator', [
            'auth_id'   => Auth::id(),
            'auth_class' => $authUser ? get_class($authUser) : null,
            'owner_id'  => $ownerId,
        ]);

        // ── 2. Auto-create delivery records for orders missing one ─────────────
        if ($ownerId) {
            try {
                $missing = Order::where('vendor_id', $ownerId)
                    ->whereNotIn('status', ['cancelled', 'refunded', 'failed'])
                    ->whereDoesntHave('delivery')
                    ->pluck('id');

                foreach ($missing as $orderId) {
                    try {
                        $this->createForOrder($orderId);
                    } catch (\Throwable $e) {
                        Log::warning("[DeliveryService] auto-create failed for order {$orderId}: {$e->getMessage()}");
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[DeliveryService] auto-create block failed: ' . $e->getMessage());
            }
        }

        // ── 3. Return paginated deliveries scoped to this vendor ───────────────
        return Delivery::with(['order.items', 'scanner'])
            ->when(
                $ownerId,
                fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('vendor_id', $ownerId))
            )
            ->when(
                ! empty($filters['status']) && $filters['status'] !== 'all',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where(function ($q) use ($filters) {
                    $q->where('delivery_id', 'like', "%{$filters['search']}%")
                      ->orWhere('barcode', 'like', "%{$filters['search']}%")
                      ->orWhereHas('order', fn ($oq) => $oq
                          ->where('id', 'like', "%{$filters['search']}%")
                          ->orWhere('order_number', 'like', "%{$filters['search']}%"));
                })
            )
            ->orderByDesc('updated_at')
            ->paginate($filters['per_page'] ?? 20);
    }


    /**
     * Delivery + full log for a single customer order (for polling endpoint).
     */
    public function forCustomerOrder(int $orderId): ?Delivery
    {
        return Delivery::with(['logs' => fn ($q) => $q->orderBy('scanned_at')])
            ->where('order_id', $orderId)
            ->first();
    }

    /**
     * Status count summary (used by SC dashboard chips).
     */
    /**
     * Status counts scoped to the same vendor the SC coordinator can see.
     * Also adds an 'all' key so the frontend KPI card for "All" works.
     *
     * @param  int|null  $ownerId  vendor_id to scope to (null = global)
     */
    public function statusCounts(?int $ownerId = null): array
    {
        $query = Delivery::selectRaw('status, COUNT(*) as count')
            ->when(
                $ownerId,
                fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('vendor_id', $ownerId))
            )
            ->groupBy('status');

        $byStatus = $query->pluck('count', 'status')
            ->map(fn ($c) => (int) $c)
            ->toArray();

        // Ensure every known status key is present (zero if no rows)
        $defaults = [
            'pending'      => 0,
            'to_processed' => 0,
            'to_ship'      => 0,
            'to_receive'   => 0,
            'completed'    => 0,
            'returned'     => 0,
            'refunded'     => 0,
        ];

        $counts = array_merge($defaults, $byStatus);

        // 'all' = sum of every status
        $counts['all'] = array_sum($counts);

        return $counts;
    }
}
