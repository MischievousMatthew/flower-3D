<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Delivery;
use App\Models\VendorBalance;
use App\Models\VendorTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorFinanceService
{
    /**
     * Called when the vendor accepts / receives the order for processing.
     * Deducts stock once so inventory reflects committed customer orders
     * before the order reaches completion.
     */
    public function handleOrderVendorReceipt(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->ensureOrderStockDeducted($order);
        });

        Log::info('VendorFinanceService: stock deduction checked on vendor receipt', [
            'order_id'  => $order->id,
            'vendor_id' => $order->vendor_id,
        ]);
    }

    /** Deduct stock at the point an order is committed, once only. */
    public function deductOrderStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->ensureOrderStockDeducted($order);
        });
    }

    /**
     * Puts back the stock an order was holding (an e-wallet payment that was
     * cancelled, failed or never finished). Safe to call more than once.
     */
    public function releaseOrderStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::query()->with('items')->whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->stock_deducted_at || $locked->stock_restored_at) {
                return;
            }

            foreach ($this->takenLines($locked) as [$productId, $color, $quantity]) {
                Product::putBackStock($productId, $quantity, $color);
            }

            $locked->update(['stock_restored_at' => now()]);
        });
    }

    /**
     * Cancel an Ordered (not yet Packed) order and restore its previously
     * deducted quantities.
     * Row locks and stock timestamps make retries/double-clicks safe.
     */
    public function cancelPendingOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->with('items')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $delivery = Delivery::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            // A confirmed online payment is not cancellable from the customer
            // order screen. It must follow the existing support/refund process.
            if (in_array($lockedOrder->payment_method, ['ewallet', 'gcash', 'maya', 'paymaya'], true)
                && $lockedOrder->payment_status === 'paid') {
                throw new \RuntimeException('Paid online orders cannot be cancelled. Please contact support if you need help.');
            }
            // Cash on delivery outside the delivery areas, with its down payment made online: the same
            if ($lockedOrder->deposit_paid_at) {
                throw new \RuntimeException('The down payment for this order was already paid online, so it can\'t be cancelled here. Please contact support if you need help.');
            }

            if (! in_array($lockedOrder->status, ['pending', 'processing'], true)
                || ($delivery && $delivery->status !== 'pending')) {
                throw new \RuntimeException('Only orders that are still Ordered can be cancelled.');
            }

            if ($lockedOrder->stock_deducted_at && ! $lockedOrder->stock_restored_at) {
                foreach ($this->takenLines($lockedOrder) as [$productId, $color, $quantity]) {
                    Product::putBackStock($productId, $quantity, $color);
                }
            }

            $lockedOrder->update([
                'status'            => 'cancelled',
                'cancelled_at'      => now(),
                'stock_restored_at' => $lockedOrder->stock_deducted_at
                    ? now()
                    : $lockedOrder->stock_restored_at,
            ]);

            return $lockedOrder->fresh(['items']);
        });
    }

    /**
     * Called when an order is marked as completed.
     *
     * Performs three things atomically:
     *   1. Reduces each ordered product's quantity_in_stock
     *   2. Credits the vendor's balance with the order total
     *   3. Records an immutable transaction entry for the finance ledger
     *
     * Wrapped in a DB transaction — if anything fails, nothing is committed.
     */
    public function handleOrderPayment(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || $lockedOrder->payment_status !== 'paid') {
                return;
            }

            // This check must happen after locking the order so concurrent
            // receipt/review requests cannot credit the vendor twice.
            if (VendorTransaction::where('order_id', $lockedOrder->id)
                ->where('category', 'order_revenue')
                ->exists()) {
                return;
            }

            $vendorBalance = VendorBalance::forVendor($lockedOrder->vendor_id);

            $balanceBefore = (float) $vendorBalance->balance;
            $amount        = (float) $lockedOrder->total_amount;
            $balanceAfter  = $balanceBefore + $amount;

            $vendorBalance->update([
                'balance'      => $balanceAfter,
                'total_earned' => $vendorBalance->total_earned + $amount,
            ]);

            $items = $lockedOrder->items()->get();

            VendorTransaction::create([
                'vendor_id'      => $lockedOrder->vendor_id,
                'order_id'       => $lockedOrder->id,
                'type'           => 'credit',
                'category'       => 'order_revenue',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => "Payment received for Order #{$lockedOrder->order_number}",
                'status'         => 'completed',
                'metadata'       => [
                    'order_number'  => $lockedOrder->order_number,
                    'subtotal'      => (float) $lockedOrder->subtotal,
                    'delivery_fee'  => (float) $lockedOrder->delivery_fee,
                    'items_count'   => $items->count(),
                    'paid_at'       => now()->toIso8601String(),
                    'payment_method'=> $lockedOrder->payment_method,
                ],
            ]);
        });

        Log::info('VendorFinanceService::handleOrderPayment: balance credited', [
            'order_id'  => $order->id,
            'vendor_id' => $order->vendor_id,
            'amount'    => $order->total_amount,
        ]);
    }

    public function handleOrderCompletion(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Keep completion idempotent so older orders still get their stock
            // deducted if they reached completion without the vendor-receipt step.
            $this->ensureOrderStockDeducted($order);
        });

        Log::info('VendorFinanceService: stock deduction checked on completion', [
            'order_id'  => $order->id,
            'vendor_id' => $order->vendor_id,
        ]);
    }

    /**
     * Settle COD only after the order has been completed by the customer.
     * Online methods remain exclusively controlled by their PayMongo callbacks.
     */
    public function settleCashOnDeliveryOrder(Order $order): bool
    {
        $settled = DB::transaction(function () use ($order): bool {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder
                || $lockedOrder->status !== 'completed'
                || $lockedOrder->payment_method !== 'cod'
                || $lockedOrder->payment_status !== 'unpaid') {
                return false;
            }

            $lockedOrder->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            return true;
        });

        if ($settled) {
            $this->handleOrderPayment($order->fresh());
        }

        return $settled;
    }

    /**
     * Called when an order is refunded.
     * Debits the vendor's balance and records a refund transaction, which the
     * vendor dashboard counts as an expense (it comes off net profit).
     *
     * $amount defaults to the order total; a settled return/refund case passes
     * the agreed amount. One refund entry per order, so repeats are ignored.
     */
    public function handleOrderRefund(Order $order, ?float $amount = null, array $metadata = []): void
    {
        $processed = DB::transaction(function () use ($order, $amount, $metadata): bool {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder) {
                throw new \RuntimeException("Order [{$order->id}] not found for refund processing.");
            }

            $alreadyRefunded = VendorTransaction::where('order_id', $lockedOrder->id)
                ->where('category', 'refund')
                ->exists();

            if ($alreadyRefunded) {
                Log::info('VendorFinanceService: refund already processed', ['order_id' => $lockedOrder->id]);
                return false;
            }

            VendorBalance::query()->insertOrIgnore([
                'vendor_id' => $lockedOrder->vendor_id,
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $vendorBalance = VendorBalance::query()
                ->where('vendor_id', $lockedOrder->vendor_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) $vendorBalance->balance;
            $amount        = round(min((float) ($amount ?? $lockedOrder->total_amount), (float) $lockedOrder->total_amount), 2);
            $balanceAfter  = max(0, $balanceBefore - $amount); // don't go negative

            // Debit vendor
            $vendorBalance->update([
                'balance'         => $balanceAfter,
                'total_withdrawn' => $vendorBalance->total_withdrawn + $amount,
            ]);

            // Record refund transaction
            VendorTransaction::create([
                'vendor_id'      => $lockedOrder->vendor_id,
                'order_id'       => $lockedOrder->id,
                'type'           => 'debit',
                'category'       => 'refund',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => ($amount < (float) $lockedOrder->total_amount ? 'Partial refund' : 'Refund') . " for Order #{$lockedOrder->order_number}",
                'status'         => 'completed',
                'metadata'       => [
                    'order_number' => $lockedOrder->order_number,
                    'refunded_at'  => now()->toIso8601String(),
                    ...$metadata,
                ],
            ]);

            return true;
        });

        if (! $processed) {
            return;
        }

        Log::info('VendorFinanceService: refund processed', [
            'order_id'  => $order->id,
            'vendor_id' => $order->vendor_id,
            'amount'    => $amount ?? $order->total_amount,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Reduce quantity_in_stock for every item in the order.
     * Uses a single query per product to avoid race conditions.
     */
    private function deductProductStock(Order $order): void
    {
        // Load items if not already loaded
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        // A bouquet made from linked flowers takes those flowers, not itself.
        // Lock in id order so two checkouts of the same products can't deadlock.
        $plan = Product::stockPlan($this->stockLines($items));
        foreach ($plan as [$productId, $color, $quantity, $forBouquet]) {
            // Only takes stock that is there (the row is locked): when two people
            // check out the last ones at once, one gets them and the other is told
            $taken = Product::takeStock($productId, $quantity, $color);

            if (! $taken) {
                // Short of stems: name the bouquet and how many of it can still be made
                $bouquet = $forBouquet ? Product::query()->find($forBouquet) : null;
                if ($bouquet) {
                    throw new \App\Exceptions\OutOfStockException($bouquet->product_name, max(0, (int) $bouquet->stockFromFlowers()));
                }
                $product = Product::query()->find($productId);
                $name = $product?->product_name ?? 'This item';
                if ($product && $color && $product->tracksColorStock() && $product->colorIndex($color) !== null) {
                    $name .= " ({$color})";
                }
                throw new \App\Exceptions\OutOfStockException($name, $product ? $product->stockFor($color) : 0);
            }

            Log::info('Stock deducted', [
                'product_id'  => $productId,
                'color'       => $color,
                'qty'         => $quantity,
                'for_bouquet' => $forBouquet,
                'order_id'    => $order->id,
            ]);
        }

        // Remember exactly what was taken, to put the same back if it's cancelled
        if (self::ordersKeepStockLines()) {
            DB::table('orders')->where('id', $order->id)->update([
                'stock_lines' => json_encode(array_map(fn ($l) => [$l[0], $l[1], $l[2]], $plan)),
            ]);
        }
    }

    /** What an order took off the shelves: what it recorded, else (older orders) its items */
    private function takenLines(Order $order): array
    {
        $saved = self::ordersKeepStockLines() ? DB::table('orders')->where('id', $order->id)->value('stock_lines') : null;
        $lines = is_string($saved) ? json_decode($saved, true) : null;
        if (is_array($lines)) {
            return array_map(fn ($l) => [(int) $l[0], $l[1] ?? null, (int) $l[2]], array_filter($lines, 'is_array'));
        }

        return $this->stockLines($order->items);
    }

    private static ?bool $hasStockLines = null;

    private static function ordersKeepStockLines(): bool
    {
        return self::$hasStockLines ??= \Illuminate\Support\Facades\Schema::hasColumn('orders', 'stock_lines');
    }

    /**
     * An order's items as stock lines [product id, colour, quantity]: one per
     * product and flower colour, in product id order (a steady lock order).
     */
    private function stockLines($items): array
    {
        return collect($items)
            ->filter(fn ($item) => $item->product_id)
            ->groupBy(fn ($item) => $item->product_id . '|' . mb_strtolower(trim((string) $item->color)))
            ->map(fn ($group) => [(int) $group->first()->product_id, trim((string) $group->first()->color) ?: null, (int) $group->sum('quantity')])
            ->sortBy(fn ($line) => sprintf('%012d|%s', $line[0], (string) $line[1]))
            ->values()
            ->all();
    }

    /**
     * Deduct stock once per order.
     */
    private function ensureOrderStockDeducted(Order $order): void
    {
        $lockedOrder = Order::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedOrder) {
            throw new \RuntimeException("Order [{$order->id}] not found for stock deduction.");
        }

        if ($lockedOrder->stock_deducted_at) {
            Log::info('VendorFinanceService: stock already deducted', [
                'order_id' => $lockedOrder->id,
            ]);
            return;
        }

        $lockedOrder->loadMissing('items');
        $this->deductProductStock($lockedOrder);
        $lockedOrder->update([
            'stock_deducted_at' => now(),
        ]);
    }

    /**
     * Credit the vendor's balance and write the ledger entry.
     */
    private function creditVendor(Order $order): void
    {
        $vendorBalance = VendorBalance::forVendor($order->vendor_id);

        $balanceBefore = (float) $vendorBalance->balance;
        $amount        = (float) $order->total_amount;
        $balanceAfter  = $balanceBefore + $amount;

        // Update running balance
        $vendorBalance->update([
            'balance'      => $balanceAfter,
            'total_earned' => $vendorBalance->total_earned + $amount,
        ]);

        // Load items for metadata (safe — already deducted above)
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        // Write immutable ledger entry
        VendorTransaction::create([
            'vendor_id'      => $order->vendor_id,
            'order_id'       => $order->id,
            'type'           => 'credit',
            'category'       => 'order_revenue',
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'description'    => "Revenue from Order #{$order->order_number}",
            'status'         => 'completed',
            'metadata'       => [
                'order_number'    => $order->order_number,
                'subtotal'        => (float) $order->subtotal,
                'delivery_fee'    => (float) $order->delivery_fee,
                'items_count'     => $items->count(),
                'completed_at'    => now()->toIso8601String(),
            ],
        ]);
    }
}
