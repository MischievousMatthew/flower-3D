<?php

namespace App\Services;

use App\Models\CodCollection;
use App\Models\Order;
use App\Models\PayMongoWebhookEvent;
use App\Models\VendorTransaction;
use App\Support\PayMongoPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Finance → Payments: every payment on the orders placed in a period, PayMongo
 * and cash on delivery, with its status, PayMongo's fee where known, where the
 * cash on delivery is (collected, settled), and anything that doesn't match
 * between the order, PayMongo and the books.
 */
class VendorPaymentsService
{
    public const STATUSES = ['paid', 'pending', 'failed', 'expired', 'refunded', 'cancelled'];
    public const COD_STAGES = ['awaiting_delivery', 'to_collect', 'collected', 'settled', 'void'];

    /** A PayMongo checkout left unpaid this long has run out */
    public const CHECKOUT_EXPIRES_HOURS = 24;

    private static ?bool $codReady = null;

    public function codReady(): bool
    {
        return self::$codReady ??= Schema::hasTable('cod_collections');
    }

    public static function isCod(?string $method): bool
    {
        return ($method ?? 'cod') === 'cod';
    }

    public function paymentStatus(Order $order): string
    {
        return match (true) {
            $order->payment_status === 'refunded' => 'refunded',
            $order->payment_status === 'paid' => 'paid',
            $order->payment_status === 'failed' || in_array($order->status, ['payment_failed', 'failed'], true) => 'failed',
            $order->status === 'cancelled' => 'cancelled',
            ! self::isCod($order->payment_method) && $order->status === 'pending'
                && $order->created_at?->lt(now()->subHours(self::CHECKOUT_EXPIRES_HOURS)) => 'expired',
            default => 'pending',
        };
    }

    public function codStage(Order $order, ?CodCollection $collection): string
    {
        return match (true) {
            in_array($order->status, ['cancelled', 'failed', 'payment_failed'], true) => 'void',
            $collection?->settled_on !== null => 'settled',
            $collection !== null => 'collected',
            in_array($order->status, ['completed', 'complete', 'delivered'], true) || $order->delivered_at !== null => 'to_collect',
            default => 'awaiting_delivery',
        };
    }

    /** One row per order placed in [from, to], newest first */
    public function rows(int $vendorId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $orders = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->with('user:id,name,surname')
            ->orderByDesc('created_at')
            ->get(['id', 'order_number', 'user_id', 'delivery_contact_name', 'status', 'payment_status', 'payment_method',
                'total_amount', 'paymongo_payment_intent_id', 'paymongo_response', 'paid_at', 'delivered_at', 'created_at']);
        if ($orders->isEmpty()) {
            return collect();
        }
        $ids = $orders->pluck('id');

        $booked = VendorTransaction::forVendor($vendorId)
            ->where('category', 'order_revenue')
            ->whereIn('order_id', $ids)
            ->selectRaw('order_id, SUM(amount) as total')
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        // PayMongo's word on each payment (older payments only have it in the webhook log)
        $events = PayMongoWebhookEvent::query()
            ->whereIn('order_id', $ids)
            ->orderBy('id')
            ->get(['order_id', 'event_type', 'payload', 'status']);
        $paidEvents = $events->whereIn('event_type', ['checkout_session.payment.paid', 'payment.paid'])->keyBy('order_id');
        $failedNotices = $events->where('status', 'failed')->pluck('order_id')->flip();

        $collections = $this->codReady()
            ? CodCollection::where('owner_id', $vendorId)->whereIn('order_id', $ids)->get()->keyBy('order_id')
            : collect();

        return $orders->map(function (Order $o) use ($booked, $paidEvents, $failedNotices, $collections) {
            $cod = self::isCod($o->payment_method);
            $status = $this->paymentStatus($o);
            $total = round((float) $o->total_amount, 2);
            $response = is_array($o->paymongo_response) ? $o->paymongo_response : [];
            $pm = $cod ? null : (isset($response['charged_amount']) ? $response : (($e = $paidEvents->get($o->id)) ? PayMongoPayment::fromEvent((array) $e->payload) : null));
            $fee = $pm['fee'] ?? null;
            $collection = $collections->get($o->id);
            $books = isset($booked[$o->id]) ? round((float) $booked[$o->id], 2) : null;

            $row = [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer' => $o->delivery_contact_name ?: (trim(($o->user?->name ?? '') . ' ' . ($o->user?->surname ?? '')) ?: 'Customer'),
                'method' => $cod ? 'cod' : 'paymongo',
                'provider' => $cod ? 'cod' : ($pm['source'] ?? $o->payment_method),
                'status' => $status,
                'order_status' => $o->status,
                'amount' => $total,
                'charged' => $pm['charged_amount'] ?? null,
                'fee' => $fee,
                'net' => $fee === null ? null : round(($pm['net_amount'] ?? (($pm['charged_amount'] ?? $total) - $fee)), 2),
                'reference' => $response['reference_number'] ?? ($pm['payment_id'] ?? $o->paymongo_payment_intent_id),
                'books' => $books,
                'placed_at' => $o->created_at,
                'paid_at' => $o->paid_at,
                'cod' => $cod ? $this->codInfo($o, $collection) : null,
            ];
            $row['issues'] = $this->issues($row, $o, $failedNotices->has($o->id), $collection);

            return $row;
        });
    }

    private function codInfo(Order $o, ?CodCollection $c): array
    {
        return [
            'stage' => $this->codStage($o, $c),
            'collected_amount' => $c?->collected_amount,
            'collected_by' => $c?->collected_by,
            'collected_on' => $c?->collected_on?->toDateString(),
            'settled_on' => $c?->settled_on?->toDateString(),
            'settlement_reference' => $c?->settlement_reference,
            'difference' => $c ? $c->difference() : null,
            'notes' => $c?->notes,
        ];
    }

    /** What doesn't match between the order, PayMongo, the books and the cash */
    private function issues(array $row, Order $o, bool $failedNotice, ?CodCollection $collection): array
    {
        $issues = [];
        $add = function (string $code, string $message) use (&$issues) {
            $issues[] = ['code' => $code, 'message' => $message];
        };
        $money = fn ($v) => '₱' . number_format((float) $v, 2);
        $settledMoney = in_array($row['status'], ['paid', 'refunded'], true);

        if ($settledMoney && $row['books'] === null) {
            $add('missing_in_books', 'Paid, but the payment is missing from the books');
        }
        if ($row['books'] !== null && abs($row['books'] - $row['amount']) > 0.009) {
            $add('books_amount', "The books show {$money($row['books'])} but the order total is {$money($row['amount'])}");
        }
        if ($row['books'] !== null && ! $settledMoney) {
            $add('books_unpaid', "In the books, but the order isn't marked as paid");
        }
        if ($row['charged'] !== null && abs($row['charged'] - $row['amount']) > 0.009) {
            $add('charged_amount', "PayMongo charged {$money($row['charged'])} but the order total is {$money($row['amount'])}");
        }
        if ($failedNotice && $row['status'] !== 'paid') {
            $add('webhook_failed', "PayMongo sent a payment notice that couldn't be processed");
        }
        if ($row['method'] === 'cod' && in_array($o->status, ['completed', 'complete'], true) && $o->payment_status === 'unpaid') {
            $add('cod_unconfirmed', "Completed, but the cash payment isn't recorded as paid");
        }
        if ($collection && $row['cod']['stage'] === 'void') {
            $add('cod_void', 'Cash was recorded for an order that was cancelled');
        }
        if ($collection && abs($collection->difference()) > 0.009) {
            $diff = $collection->difference();
            $add($diff < 0 ? 'cod_short' : 'cod_over', ($diff < 0 ? 'Cash short by ' : 'Cash over by ') . $money(abs($diff)));
        }

        return $issues;
    }

    /** Headline numbers for the rows */
    public function summary(Collection $rows): array
    {
        $sum = fn (Collection $c) => round((float) $c->sum('amount'), 2);
        $byStatus = fn (Collection $c) => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => [
            'count' => $c->where('status', $s)->count(),
            'amount' => $sum($c->where('status', $s)),
        ]])->all();

        $paymongo = $rows->where('method', 'paymongo');
        $paidOnline = $paymongo->where('status', 'paid');
        $withFee = $paidOnline->filter(fn ($r) => $r['fee'] !== null);
        $tried = $paymongo->whereIn('status', ['paid', 'failed', 'expired', 'refunded'])->count();
        $cod = $rows->where('method', 'cod');
        $codStage = fn ($stage) => $cod->filter(fn ($r) => $r['cod']['stage'] === $stage);

        return [
            'all' => $byStatus($rows),
            'paymongo' => [
                'statuses' => $byStatus($paymongo),
                'success_rate' => $tried ? round(($paidOnline->count() + $paymongo->where('status', 'refunded')->count()) / $tried * 100, 1) : null,
                'fees' => round((float) $withFee->sum('fee'), 2),
                'fees_known' => $withFee->count(),
                'paid_count' => $paidOnline->count(),
                // What reached the shop: net where PayMongo said, the full amount otherwise
                'net_received' => round((float) $paidOnline->sum(fn ($r) => $r['net'] ?? $r['amount']), 2),
                'providers' => $paidOnline->groupBy('provider')
                    ->map(fn ($c, $p) => ['provider' => $p, 'count' => $c->count(), 'amount' => $sum($c), 'fees' => round((float) $c->sum('fee'), 2)])
                    ->sortByDesc('amount')->values()->all(),
            ],
            'cod' => [
                'statuses' => $byStatus($cod),
                'stages' => collect(self::COD_STAGES)->mapWithKeys(fn ($s) => [$s => ['count' => $codStage($s)->count(), 'amount' => $sum($codStage($s))]])->all(),
                'collected' => round((float) $cod->sum(fn ($r) => $r['cod']['collected_amount'] ?? 0), 2),
                'short' => round((float) $cod->sum(fn ($r) => min(0, $r['cod']['difference'] ?? 0)) * -1, 2),
            ],
        ];
    }

    /** How many payments line up, and the ones that don't */
    public function reconciliation(Collection $rows): array
    {
        $checked = $rows->filter(fn ($r) => in_array($r['status'], ['paid', 'refunded'], true) || $r['books'] !== null || $r['issues']);
        $withIssues = $rows->filter(fn ($r) => $r['issues']);

        return [
            'checked' => $checked->count(),
            'matched' => $checked->count() - $withIssues->count(),
            'issue_count' => $withIssues->count(),
            'issues' => $withIssues->take(100)->map(fn ($r) => [
                'id' => $r['id'],
                'order_number' => $r['order_number'],
                'customer' => $r['customer'],
                'method' => $r['method'],
                'amount' => $r['amount'],
                'issues' => $r['issues'],
            ])->values()->all(),
        ];
    }
}
