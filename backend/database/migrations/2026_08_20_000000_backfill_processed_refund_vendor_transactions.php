<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill refunds approved before OrderRequestController used the existing
     * VendorFinanceService path. A refund ledger row is the authoritative guard
     * against creating or counting a refund twice.
     */
    public function up(): void
    {
        DB::table('order_requests as request')
            ->join('orders as order', 'order.id', '=', 'request.order_id')
            ->where('request.type', 'refund')
            ->where('request.status', 'approved')
            ->where('order.status', 'refunded')
            ->select([
                'request.id as request_id',
                'request.updated_at as refunded_at',
                'order.id as order_id',
                'order.vendor_id',
                'order.order_number',
                'order.total_amount',
            ])
            ->orderBy('request.id')
            ->chunkById(100, function ($refunds) {
                foreach ($refunds as $refund) {
                    DB::transaction(function () use ($refund) {
                        $order = DB::table('orders')
                            ->where('id', $refund->order_id)
                            ->lockForUpdate()
                            ->first(['id', 'vendor_id', 'order_number', 'total_amount', 'status']);

                        if (! $order || $order->status !== 'refunded') {
                            return;
                        }

                        $alreadyRecorded = DB::table('vendor_transactions')
                            ->where('order_id', $order->id)
                            ->where('category', 'refund')
                            ->exists();

                        if ($alreadyRecorded) {
                            return;
                        }

                        $timestamp = $refund->refunded_at ?? now();
                        $amount = (float) $order->total_amount;

                        DB::table('vendor_balances')->insertOrIgnore([
                            'vendor_id' => $order->vendor_id,
                            'balance' => 0,
                            'total_earned' => 0,
                            'total_withdrawn' => 0,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);

                        $balance = DB::table('vendor_balances')
                            ->where('vendor_id', $order->vendor_id)
                            ->lockForUpdate()
                            ->first();

                        $balanceBefore = (float) $balance->balance;
                        $balanceAfter = max(0, $balanceBefore - $amount);

                        DB::table('vendor_balances')
                            ->where('vendor_id', $order->vendor_id)
                            ->update([
                                'balance' => $balanceAfter,
                                'total_withdrawn' => (float) $balance->total_withdrawn + $amount,
                                'updated_at' => $timestamp,
                            ]);

                        DB::table('vendor_transactions')->insert([
                            'vendor_id' => $order->vendor_id,
                            'order_id' => $order->id,
                            'type' => 'debit',
                            'category' => 'refund',
                            'amount' => $amount,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'description' => "Refund for Order #{$order->order_number}",
                            'status' => 'completed',
                            'metadata' => json_encode([
                                'order_number' => $order->order_number,
                                'refund_request_id' => $refund->request_id,
                                'refunded_at' => $timestamp,
                                'backfilled' => true,
                            ]),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                    });
                }
            }, 'request.id', 'request_id');
    }

    public function down(): void
    {
        // Financial ledger backfills are intentionally not reversed.
    }
};
