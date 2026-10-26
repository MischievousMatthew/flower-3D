<?php

namespace App\Services;

use App\Mail\PurchaseOrderMail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\FundingRequest;
use App\Models\Product;
use App\Models\WarehouseBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    private const STATUS_TRANSITIONS = [
        'pending'    => ['processing', 'completed'],
        'processing' => ['shipped'],
        'shipped'    => ['received'],
        'received'   => ['completed'],
        'partially_received' => ['completed'],
        'completed'  => [],
    ];

    /** With the supplier, being prepared, on the way, or arrived: can be received into stock */
    private const RECEIVABLE = ['pending', 'processing', 'shipped', 'received', 'partially_received'];

    // ─── Queries ──────────────────────────────────────────────────────────────

    public function listOrders(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->where('owner_id', $ownerId)
            ->with('supplier', 'items')
            ->latest('created_at')
            ->latest('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['stage'])) {
            $query->whereIn('status', PurchaseOrder::STAGES[$filters['stage']] ?? []);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['from'])) {
            $query->whereRaw('COALESCE(order_date, DATE(created_at)) >= ?', [$filters['from']]);
        }
        if (!empty($filters['to'])) {
            $query->whereRaw('COALESCE(order_date, DATE(created_at)) <= ?', [$filters['to']]);
        }

        if (!empty($filters['overdue'])) {
            $query->whereIn('status', self::RECEIVABLE)->whereNotNull('expected_date')->whereDate('expected_date', '<', now()->toDateString());
        }

        if (!empty($filters['search'])) {
            // Case-insensitive on Postgres too
            $term = '%' . mb_strtolower($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(order_number) LIKE ?', [$term])
                  ->orWhereHas('supplier', fn ($s) => $s->whereRaw('LOWER(company_name) LIKE ?', [$term]))
                  ->orWhereHas('items', fn ($i) => $i->whereRaw('LOWER(product_name) LIKE ?', [$term]));
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /** How many orders sit in each of the five stages, and how many are late */
    public function stageCounts(int $ownerId): array
    {
        $byStatus = PurchaseOrder::where('owner_id', $ownerId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $counts = collect(PurchaseOrder::STAGES)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0));

        return [
            'total'   => (int) $byStatus->sum(),
            'stages'  => $counts,
            'overdue' => PurchaseOrder::where('owner_id', $ownerId)
                ->whereIn('status', self::RECEIVABLE)
                ->whereNotNull('expected_date')
                ->whereDate('expected_date', '<', now()->toDateString())
                ->count(),
        ];
    }

    public function findOrder(int $id, int $ownerId): PurchaseOrder
    {
        return PurchaseOrder::where('owner_id', $ownerId)->with('supplier', 'items', 'shipment', 'events', 'receipts.lines', 'receipts.files')->findOrFail($id);
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

            // draft: still being written · pending_approval: waiting for approval
            // · pending: approved and with the supplier (how orders started before)
            $status = $data['status'] ?? 'pending';
            $actor = auth()->user();

            $order = PurchaseOrder::create([
                'owner_id'        => $ownerId,
                'supplier_id'     => $supplier->id,
                'order_number'    => $this->generateOrderNumber(),
                'status'          => $status,
                'total_amount'    => 0,
                'order_date'      => $data['order_date'] ?? now()->toDateString(),
                'expected_date'   => $data['expected_date'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'created_by_name' => PurchaseOrderEvent::nameOf($actor),
                'submitted_at'    => $status !== 'draft' ? now() : null,
            ]);
            PurchaseOrderEvent::record($order, 'created', null, $status);

            $this->attachItems($order->id, $items, $ownerId);
            $this->calculateTotals($order->id, $ownerId);

            if ($status === 'pending') {
                $this->markApproved($order, null);
            }

            return $order->load('items', 'supplier');
        });
    }

    // ─── Drafts, approval and sending ─────────────────────────────────────────

    /** Change a draft (or an order waiting for approval): details and every line */
    public function updateDraft(int $orderId, array $data, int $ownerId): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $data, $ownerId) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->findOrFail($orderId);
            $this->assertEditable($order);

            if (isset($data['supplier_id']) && (int) $data['supplier_id'] !== (int) $order->supplier_id) {
                $supplier = Supplier::where('owner_id', $ownerId)->findOrFail($data['supplier_id']);
                if ($supplier->status !== 'active') {
                    throw new \RuntimeException("{$supplier->company_name} isn't active. Pick an active supplier.");
                }
            }

            $order->update(array_intersect_key($data, array_flip(['supplier_id', 'order_date', 'expected_date', 'notes'])));

            if (isset($data['items'])) {
                $order->items()->delete();
                $this->attachItems($order->id, $data['items'], $ownerId);
            }
            PurchaseOrderEvent::record($order, 'edited');

            return $this->calculateTotals($order->id, $ownerId)->load('supplier', 'items');
        });
    }

    /** Draft → waiting for approval */
    public function submitForApproval(int $orderId, int $ownerId): PurchaseOrder
    {
        $order = PurchaseOrder::where('owner_id', $ownerId)->withCount('items')->findOrFail($orderId);
        if ($order->status !== 'draft') {
            throw new \RuntimeException('Only a draft can be sent for approval.');
        }
        if (! $order->items_count) {
            throw new \RuntimeException('Add at least one item before sending it for approval.');
        }
        $order->update(['status' => 'pending_approval', 'submitted_at' => now()]);

        return $order->fresh(['supplier', 'items']);
    }

    /** Draft or waiting → ordered (with the supplier). Emails the supplier when asked to. */
    public function approve(int $orderId, int $ownerId, bool $email = true, ?string $note = null): array
    {
        $order = DB::transaction(function () use ($orderId, $ownerId, $note) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->with('supplier')->withCount('items')->lockForUpdate()->findOrFail($orderId);
            $this->assertEditable($order);
            if (! $order->items_count) {
                throw new \RuntimeException('Add at least one item before approving it.');
            }
            if ($order->supplier?->status !== 'active') {
                throw new \RuntimeException("{$order->supplier?->company_name} isn't active any more. Pick another supplier first.");
            }
            $order->quietlyMoveTo(['status' => 'pending', 'submitted_at' => $order->submitted_at ?? now()]);
            $this->markApproved($order, $note);

            return $order;
        });

        $emailed = $email ? $this->emailSupplier($order) : null;

        return ['order' => $order->fresh(['supplier', 'items', 'events']), 'emailed' => $emailed];
    }

    /** Waiting for approval → back to draft, with the reason */
    public function sendBack(int $orderId, int $ownerId, string $reason): PurchaseOrder
    {
        $order = PurchaseOrder::where('owner_id', $ownerId)->findOrFail($orderId);
        if ($order->status !== 'pending_approval') {
            throw new \RuntimeException('Only an order waiting for approval can be sent back.');
        }
        $order->quietlyMoveTo(['status' => 'draft']);
        PurchaseOrderEvent::record($order, 'sent_back', 'pending_approval', 'draft', $reason);
        // Asking for approval again starts from a fresh submission
        $order->update(['submitted_at' => null]);

        return $order->fresh(['supplier', 'items']);
    }

    /** Email the order to the supplier (again). Null when there's no email to send to. */
    public function emailSupplier(PurchaseOrder $order): ?bool
    {
        $order->loadMissing('supplier', 'items');
        if (! $order->supplier?->email) {
            return null;
        }
        try {
            $ok = (new PurchaseOrderMail($order, PurchaseOrderEvent::nameOf(auth()->user())))->send();
        } catch (\Throwable $e) {
            Log::warning('Purchase order email failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            $ok = false;
        }
        if ($ok) {
            $order->update(['sent_at' => now(), 'sent_to_email' => $order->supplier->email]);
        }
        PurchaseOrderEvent::record($order, $ok ? 'emailed' : 'email_failed', null, null, $order->supplier->email);

        return $ok;
    }

    /**
     * Record what arrived. Each line: { id, qty }. Received stock goes into the
     * warehouse as a batch, like a completed order always has. The order is
     * completed once everything has arrived, otherwise partly received.
     */
    public function receive(int $orderId, array $lines, int $ownerId, ?string $note = null): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $lines, $ownerId, $note) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->with('items', 'supplier')->lockForUpdate()->findOrFail($orderId);
            if (! in_array($order->status, self::RECEIVABLE, true)) {
                throw new \RuntimeException($order->status === 'completed'
                    ? 'Everything on this order has already been received.'
                    : 'Approve the order before receiving anything from it.');
            }

            $funding = FundingRequest::where('linked_order_id', $order->id)->first();
            $summary = [];
            foreach ($lines as $line) {
                $item = $order->items->firstWhere('id', (int) $line['id']);
                $qty = (int) ($line['qty'] ?? 0);
                if (! $item || $qty <= 0) {
                    continue;
                }
                $left = $item->outstanding();
                if ($qty > $left) {
                    throw new \RuntimeException("Only {$left} of {$item->product_name} are still to come.");
                }
                $this->receiveItem($order, $item, $qty, $funding);
                $summary[] = "{$qty} {$item->product_name}";
            }
            if (! $summary) {
                throw new \RuntimeException('Enter how many arrived for at least one item.');
            }

            $done = $order->items->every(fn ($i) => $i->outstanding() === 0);
            $from = $order->status;
            $order->quietlyMoveTo($done ? ['status' => 'completed', 'completed_at' => now()] : ['status' => 'partially_received']);
            PurchaseOrderEvent::record($order, 'received', $from, $order->status, trim(implode(', ', $summary) . ($note ? " · {$note}" : '')));

            return $order->fresh(['supplier', 'items', 'events']);
        });
    }

    /** A warehouse batch received for this order counts toward its lines */
    public function recordBatchReceipt(PurchaseOrder $order, int $productId, int $qty): void
    {
        $order->loadMissing('items');
        $item = $order->items->first(fn ($i) => (int) $i->product_id === $productId && $i->outstanding() > 0)
            ?? $order->items->firstWhere('product_id', $productId);

        if ($item) {
            $item->update(['received_qty' => (int) $item->received_qty + min($item->outstanding(), $qty)]);
        }
        // An order whose lines can't be matched completes as it always did
        $done = ! $item || $order->items->every(fn ($i) => $i->fresh()->outstanding() === 0);
        $from = $order->status;
        $order->quietlyMoveTo($done ? ['status' => 'completed', 'completed_at' => now()] : ['status' => 'partially_received']);
        PurchaseOrderEvent::record($order, 'received', $from, $order->status, "{$qty} " . ($item?->product_name ?? 'units') . ' received into the warehouse');
    }

    /** Only drafts and orders waiting for approval can be deleted; anything sent stays as history */
    public function deleteOrder(int $orderId, int $ownerId): void
    {
        DB::transaction(function () use ($orderId, $ownerId) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->findOrFail($orderId);
            if (! in_array($order->status, PurchaseOrder::EDITABLE, true)) {
                throw new \RuntimeException("This order was already sent to the supplier, so it stays in the history and can't be deleted.");
            }
            FundingRequest::where('linked_order_id', $order->id)->update(['linked_order_id' => null]);
            $order->items()->delete();
            PurchaseOrderEvent::where('purchase_order_id', $order->id)->delete();
            $order->delete();
        });
    }

    private function markApproved(PurchaseOrder $order, ?string $note): void
    {
        $order->update(['approved_at' => now(), 'approved_by_name' => PurchaseOrderEvent::nameOf(auth()->user())]);
        PurchaseOrderEvent::record($order, 'approved', null, null, $note);
    }

    private function assertEditable(PurchaseOrder $order): void
    {
        if (! in_array($order->status, PurchaseOrder::EDITABLE, true)) {
            throw new \RuntimeException('This order was already sent to the supplier, so it can no longer be changed.');
        }
    }

    // ─── Items ────────────────────────────────────────────────────────────────

    public function attachItems(int $orderId, array $items, int $ownerId): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $items, $ownerId) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->with('supplier')->findOrFail($orderId);

            if (! in_array($order->status, [...PurchaseOrder::EDITABLE, 'pending'], true)) {
                throw new \RuntimeException(
                    "Items can only be added before the supplier starts preparing the order."
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
                    'product_id'          => $productId,   // ← now stored
                    'product_name'        => $item['product_name'],
                    'quantity'            => $item['quantity'],
                    'price'               => $item['price'],
                    'unit'                => $item['unit'] ?? null,
                    'supplier_product_id' => $item['supplier_product_id'] ?? null,
                ]);
            }

            return $this->calculateTotals($orderId, $ownerId);
        });
    }

    public function removeItem(int $orderId, int $itemId, int $ownerId): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $itemId, $ownerId) {
            $order = PurchaseOrder::where('owner_id', $ownerId)->findOrFail($orderId);

            if (! in_array($order->status, [...PurchaseOrder::EDITABLE, 'pending'], true)) {
                throw new \RuntimeException("Items can only be removed before the supplier starts preparing the order.");
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
        $newStatus = trim($newStatus);

        if ($newStatus === '') {
            throw new \InvalidArgumentException('Order status is required.');
        }

        $order   = PurchaseOrder::where('owner_id', $ownerId)->with('items', 'supplier')->findOrFail($orderId);
        $allowed = self::STATUS_TRANSITIONS[$order->status] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Invalid transition: [{$order->status}] → [{$newStatus}]. "
                . "Allowed: [" . implode(', ', $allowed) . "]"
            );
        }

        DB::transaction(function () use ($order, $newStatus) {
            $order->update(['status' => $newStatus] + ($newStatus === 'completed' ? ['completed_at' => now()] : []));

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

        // Only what hasn't arrived yet: part of the order may already be in stock
        foreach ($order->items as $item) {
            $left = $item->outstanding();
            if ($left > 0) {
                $this->receiveItem($order, $item, $left, $funding);
            }
        }
    }

    /** Put `qty` of one line into the warehouse as a batch and add it to stock */
    /**
     * Put `qty` of one line into the warehouse as a batch and add it to stock.
     * $batch can set the location, dates and condition found at inspection.
     */
    public function receiveItem(PurchaseOrder $order, PurchaseOrderItem $item, int $qty, ?FundingRequest $funding, array $batch = []): WarehouseBatch
    {
        $product = $this->resolveOrderItemProduct($order, $item);

        $shelfLife      = $batch['freshness_days'] ?? $funding?->shelf_life;
        $expirationDate = $batch['expiration_date'] ?? ($shelfLife ? now()->addDays((int) $shelfLife)->toDateString() : null);
        $batchNumber    = WarehouseBatch::generateBatchNumber($product->sku, now()->toDateString());

        $created = WarehouseBatch::create([
            'owner_id'              => $order->owner_id,
            'product_id'            => $product->id,
            'qty_received'          => $qty,
            'qty_remaining'         => $qty,
            'batch_number'          => $batchNumber,
            'barcode'               => $this->generateBatchBarcode(),
            'received_date'         => $batch['received_date'] ?? now()->toDateString(),
            'harvest_date'          => $batch['harvest_date'] ?? null,
            'warehouse_location_id' => $batch['warehouse_location_id'] ?? null,
            'lot_number'            => $order->order_number,
            'condition_status'      => $batch['condition_status'] ?? 'fresh',
            'status'                => 'active',
            'freshness_days'        => $shelfLife,
            'expiration_date'       => $expirationDate,
            'notes'                 => $batch['notes'] ?? "Auto-received from Order {$order->order_number}",
        ]);

        $product->increment('quantity_in_stock', $qty);
        $item->update(['received_qty' => (int) $item->received_qty + $qty]);

        return $created;
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

            // Finance already approved the funding request, so the order goes straight to the supplier
            $order = PurchaseOrder::create([
                'owner_id'        => $ownerId,
                'supplier_id'     => $supplier->id,
                'order_number'    => $this->generateOrderNumber(),
                'status'          => 'pending',
                'total_amount'    => $quantity * $price,
                'order_date'      => now()->toDateString(),
                'created_by_name' => PurchaseOrderEvent::nameOf(auth()->user()),
                'submitted_at'    => now(),
                'approved_at'     => now(),
                'approved_by_name' => 'Funding request ' . ($funding->finance_request_id ?? "#{$funding->id}"),
                'notes'           => "From approved funding request " . ($funding->finance_request_id ?? "#{$funding->id}"),
            ]);
            PurchaseOrderEvent::record($order, 'created', null, 'pending', 'From an approved funding request');

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

    private function resolveOrderItemProduct(PurchaseOrder $order, PurchaseOrderItem $item): Product
    {
        if ($item->product_id) {
            $product = Product::withTrashed()
                ->where('owner_id', $order->owner_id)
                ->find($item->product_id);

            if ($product) {
                return $product;
            }
        }

        $product = Product::withTrashed()
            ->where('owner_id', $order->owner_id)
            ->where('product_name', $item->product_name)
            ->first();

        if ($product) {
            return $product;
        }

        Log::error('Order completion failed: product resolution missing for order item', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'owner_id' => $order->owner_id,
            'item_id' => $item->id,
            'item_product_id' => $item->product_id,
            'item_product_name' => $item->product_name,
        ]);

        throw new \RuntimeException(
            "Cannot complete order [{$order->order_number}]: no product found for item [{$item->product_name}]."
        );
    }

    private function generateBatchBarcode(): string
    {
        return 'WB-' . strtoupper(base_convert((string) time(), 10, 36))
            . '-' . strtoupper(Str::random(4));
    }
}
