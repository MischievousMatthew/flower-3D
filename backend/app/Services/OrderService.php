<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\FundingRequest;
use App\Models\Product;
use App\Models\WarehouseBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    private const STATUS_TRANSITIONS = [
        'pending'    => ['processing', 'completed'],
        'processing' => ['shipped'],
        'shipped'    => ['received'],
        'received'   => ['completed'],
        'completed'  => [],
    ];

    // ─── Queries ──────────────────────────────────────────────────────────────

    public function listOrders(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->where('owner_id', $ownerId)
            ->with('supplier', 'items')
            ->latest('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn ($s) => $s->where('company_name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findOrder(int $id, int $ownerId): PurchaseOrder
    {
        return PurchaseOrder::where('owner_id', $ownerId)->with('supplier', 'items', 'shipment')->findOrFail($id);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function createOrder(array $data, int $ownerId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $ownerId) {
            $supplier = Supplier::where('owner_id', $ownerId)->findOrFail($data['supplier_id']);

            if ($supplier->status !== 'active') {
                throw new \RuntimeException(
                    "Cannot create order: supplier [{$supplier->company_name}] is not active."
                );
            }

            $items = $data['items'] ?? [];

            if (empty($items)) {
                throw new \RuntimeException('A purchase order must have at least one item.');
            }

            $order = PurchaseOrder::create([
                'owner_id'     => $ownerId,
                'supplier_id'  => $supplier->id,
                'order_number' => $this->generateOrderNumber(),
                'status'       => 'pending',
                'total_amount' => 0,
            ]);

            $this->attachItems($order->id, $items, $ownerId);
            $this->calculateTotals($order->id, $ownerId);

            return $order->load('items', 'supplier');
        });
    }

    // ─── Items ────────────────────────────────────────────────────────────────

    public function attachItems(int $orderId, array $items, int $ownerId): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $items, $ownerId) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->with('supplier')->findOrFail($orderId);

            if ($order->status !== 'pending') {
                throw new \RuntimeException(
                    "Items can only be added to orders in [pending] status."
                );
            }

            foreach ($items as $item) {
                // FIXED: resolve product_id if the caller didn't supply it.
                // Try the supplied product_id first, then fall back to a name
                // lookup scoped to the supplier's owner so we get the right tenant.
                $productId = $item['product_id'] ?? null;

                if (!$productId && !empty($item['product_name'])) {
                    $ownerId   = $order->supplier->owner_id ?? null;
                    $product   = Product::where('product_name', $item['product_name'])
                        ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
                        ->first();
                    $productId = $product?->id;
                }

                $order->items()->create([
                    'product_id'   => $productId,   // ← now stored
                    'product_name' => $item['product_name'],
                    'quantity'     => $item['quantity'],
                    'price'        => $item['price'],
                ]);
            }

            return $this->calculateTotals($orderId, $ownerId);
        });
    }

    public function removeItem(int $orderId, int $itemId, int $ownerId): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $itemId, $ownerId) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->findOrFail($orderId);

            if ($order->status !== 'pending') {
                throw new \RuntimeException("Items can only be removed from [pending] orders.");
            }

            PurchaseOrderItem::where('id', $itemId)
                ->where('purchase_order_id', $orderId)
                ->firstOrFail()
                ->delete();

            return $this->calculateTotals($orderId, $ownerId);
        });
    }

    // ─── Totals & Status ──────────────────────────────────────────────────────

    public function calculateTotals(int $orderId, int $ownerId): PurchaseOrder
    {
        $order = PurchaseOrder::where('owner_id', $ownerId)->findOrFail($orderId);
        $total = $order->items()->sum(DB::raw('quantity * price'));

        $order->update(['total_amount' => $total]);

        return $order->fresh('items');
    }

    /**
     * Transition an order to a new status.
     * When transitioning to "completed", automatically creates warehouse batches
     * and increments product stock for every line item on the order.
     */
    public function updateOrderStatus(int $orderId, string $newStatus, int $ownerId): PurchaseOrder
    {
        $order   = PurchaseOrder::where('owner_id', $ownerId)->with('items', 'supplier')->findOrFail($orderId);
        $allowed = self::STATUS_TRANSITIONS[$order->status] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Invalid transition: [{$order->status}] → [{$newStatus}]. "
                . "Allowed: [" . implode(', ', $allowed) . "]"
            );
        }

        DB::transaction(function () use ($order, $newStatus) {
            $order->update(['status' => $newStatus]);

            if ($newStatus === 'completed') {
                $this->autoReceiveBatches($order);
            }
        });

        return $order->fresh();
    }

    /**
     * Auto-create a WarehouseBatch for every item on a completed order.
     * FIXED: uses item->product_id directly (now stored) instead of a
     * fragile name-lookup that silently fails for duplicate product names.
     */
    private function autoReceiveBatches(PurchaseOrder $order): void
    {
        $funding = FundingRequest::where('linked_order_id', $order->id)->first();

        foreach ($order->items as $item) {
            // FIXED: prefer the stored product_id, fall back to name match only
            // if product_id was never set (legacy rows).
            $product = null;

            if ($item->product_id) {
                $product = Product::withTrashed()->find($item->product_id);
            }

            if (!$product) {
                $ownerId = $order->supplier->owner_id ?? null;
                $product = Product::where('product_name', $item->product_name)
                    ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
                    ->first();
            }

            $shelfLife      = $funding?->shelf_life;
            $expirationDate = $shelfLife ? now()->addDays((int) $shelfLife)->toDateString() : null;

            WarehouseBatch::create([
                'owner_id'              => $order->owner_id,
                'product_id'            => $product?->id,
                'product_name_snapshot' => $item->product_name,
                'qty_received'          => $item->quantity,
                'qty_remaining'         => $item->quantity,
                'received_date'         => now()->toDateString(),
                'lot_number'            => $order->order_number,
                'status'                => 'active',
                'freshness_days'        => $shelfLife,
                'expiration_date'       => $expirationDate,
                'notes'                 => "Auto-received from Order {$order->order_number}",
                'source_order_id'       => $order->id,
            ]);

            if ($product) {
                $product->increment('quantity_in_stock', $item->quantity);
            }
        }
    }

    // ─── Funding requests ─────────────────────────────────────────────────────

    public function getApprovedFundingRequests(int $ownerId): \Illuminate\Support\Collection
    {
        return FundingRequest::where('owner_id', $ownerId)
            ->where('request_status', 'Approved')
            ->whereNull('linked_order_id')
            ->latest()
            ->get();
    }

    public function createOrderFromFunding(int $fundingRequestId, int $supplierId, int $ownerId): PurchaseOrder
    {
        return DB::transaction(function () use ($fundingRequestId, $supplierId, $ownerId) {
            $funding = FundingRequest::where('owner_id', $ownerId)->findOrFail($fundingRequestId);

            if ($funding->request_status !== 'Approved') {
                throw new \RuntimeException(
                    "Cannot create order: funding request [{$funding->finance_request_id}] is not approved."
                );
            }

            if ($funding->linked_order_id) {
                throw new \RuntimeException(
                    "Funding request [{$funding->finance_request_id}] already has a linked order."
                );
            }

            $supplier = Supplier::where('id', $supplierId)
                ->where('owner_id', $ownerId)
                ->where('status', 'active')
                ->firstOrFail();

            $quantity = $funding->recommended_qty ?? $funding->requested_qty;
            $price    = $funding->estimated_unit_cost;

            // FIXED: look up the product so we can store product_id on the item.
            // Scope to owner so multi-tenant lookups don't cross-contaminate.
            $product = Product::where('product_name', $funding->product_name)
                ->where('owner_id', $funding->owner_id)
                ->first();

            $order = PurchaseOrder::create([
                'owner_id'     => $ownerId,
                'supplier_id'  => $supplier->id,
                'order_number' => $this->generateOrderNumber(),
                'status'       => 'pending',
                'total_amount' => $quantity * $price,
            ]);

            $order->items()->create([
                'product_id'   => $product?->id,   // ← now stored
                'product_name' => $funding->product_name,
                'quantity'     => $quantity,
                'price'        => $price,
            ]);

            $funding->update(['linked_order_id' => $order->id]);

            return $order->load('items', 'supplier');
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateOrderNumber(): string
    {
        do {
            $number = 'PO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        } while (PurchaseOrder::where('order_number', $number)->exists());

        return $number;
    }
}