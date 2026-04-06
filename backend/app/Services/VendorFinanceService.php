<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
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
        if ($order->payment_status !== 'paid') {
            Log::warning('VendorFinanceService::handleOrderPayment: order not paid', [
                'order_id' => $order->id,
            ]);
            return;
        }

        // Prevent double-crediting
        $alreadyProcessed = VendorTransaction::where('order_id', $order->id)
            ->where('category', 'order_revenue')
            ->exists();

        if ($alreadyProcessed) {
            Log::info('VendorFinanceService::handleOrderPayment: already processed', [
                'order_id' => $order->id,
            ]);
            return;
        }

        DB::transaction(function () use ($order) {
            $vendorBalance = VendorBalance::forVendor($order->vendor_id);

            $balanceBefore = (float) $vendorBalance->balance;
            $amount        = (float) $order->total_amount;
            $balanceAfter  = $balanceBefore + $amount;

            $vendorBalance->update([
                'balance'      => $balanceAfter,
                'total_earned' => $vendorBalance->total_earned + $amount,
            ]);

            $items = $order->items()->get();

            VendorTransaction::create([
                'vendor_id'      => $order->vendor_id,
                'order_id'       => $order->id,
                'type'           => 'credit',
                'category'       => 'order_revenue',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => "Payment received for Order #{$order->order_number}",
                'status'         => 'completed',
                'metadata'       => [
                    'order_number'  => $order->order_number,
                    'subtotal'      => (float) $order->subtotal,
                    'delivery_fee'  => (float) $order->delivery_fee,
                    'items_count'   => $items->count(),
                    'paid_at'       => now()->toIso8601String(),
                    'payment_method'=> $order->payment_method,
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
     * Called when an order is refunded.
     * Debits the vendor's balance and records a refund transaction.
     */
    public function handleOrderRefund(Order $order): void
    {
        $alreadyRefunded = VendorTransaction::where('order_id', $order->id)
            ->where('category', 'refund')
            ->exists();

        if ($alreadyRefunded) {
            Log::info('VendorFinanceService: refund already processed', ['order_id' => $order->id]);
            return;
        }

        DB::transaction(function () use ($order) {
            $vendorBalance = VendorBalance::forVendor($order->vendor_id);

            $balanceBefore = (float) $vendorBalance->balance;
            $amount        = (float) $order->total_amount;
            $balanceAfter  = max(0, $balanceBefore - $amount); // don't go negative

            // Debit vendor
            $vendorBalance->update([
                'balance'         => $balanceAfter,
                'total_withdrawn' => $vendorBalance->total_withdrawn + $amount,
            ]);

            // Record refund transaction
            VendorTransaction::create([
                'vendor_id'      => $order->vendor_id,
                'order_id'       => $order->id,
                'type'           => 'debit',
                'category'       => 'refund',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => "Refund for Order #{$order->order_number}",
                'status'         => 'completed',
                'metadata'       => [
                    'order_number' => $order->order_number,
                    'refunded_at'  => now()->toIso8601String(),
                ],
            ]);
        });

        Log::info('VendorFinanceService: refund processed', [
            'order_id'  => $order->id,
            'vendor_id' => $order->vendor_id,
            'amount'    => $order->total_amount,
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

        foreach ($items as $item) {
            if (! $item->product_id) {
                continue;
            }

            // Decrement but never go below 0
            Product::where('id', $item->product_id)
                ->where('quantity_in_stock', '>', 0)
                ->decrement('quantity_in_stock', $item->quantity);

            Log::info('Stock deducted', [
                'product_id' => $item->product_id,
                'qty'        => $item->quantity,
                'order_id'   => $order->id,
            ]);
        }
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
