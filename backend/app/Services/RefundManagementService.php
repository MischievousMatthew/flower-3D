<?php

namespace App\Services;

use App\Models\OrderRequest;
use App\Models\VendorTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Finance → Refunds: the refund cases customers opened, where each stands
 * (requested, approved, processing, completed, closed), the order and payment
 * each belongs to, and whether the money on the books matches what was agreed.
 *
 * Cases are the ones opened in the period plus any still open from before.
 */
class RefundManagementService
{
    public const STATUSES = ['requested', 'approved', 'processing', 'completed', 'closed'];

    /** A refund sent this many days ago that the customer still hasn't confirmed needs a follow-up */
    public const FOLLOW_UP_DAYS = 7;

    public function __construct(private ReturnCaseService $cases) {}

    public static function refundResolution(?string $resolution): bool
    {
        return in_array($resolution, [OrderRequest::FULL_REFUND, OrderRequest::PARTIAL_REFUND], true);
    }

    /** Where a case stands, in finance's terms */
    public static function status(OrderRequest $c): string
    {
        return match (true) {
            $c->status === 'pending' => 'requested',
            $c->status === 'approved' && $c->refund_sent_at !== null => 'processing',
            $c->status === 'approved' => 'approved',
            $c->status === 'completed' => 'completed',
            default => 'closed',
        };
    }

    /** What the case would pay back: agreed, else offered, else asked for */
    public static function amount(OrderRequest $c): float
    {
        $resolution = $c->settled_resolution ?? ($c->status === 'pending' ? ($c->offer_resolution ?? $c->resolution_requested) : $c->resolution_requested);
        $value = $c->settled_amount ?? ($c->status === 'pending' ? ($c->offer_amount ?? $c->amount_requested) : $c->amount_requested);
        if ($value === null && $resolution === OrderRequest::FULL_REFUND) {
            $value = (float) ($c->order?->total_amount ?? 0);
        }

        return round((float) $value, 2);
    }

    public function cases(int $vendorId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return OrderRequest::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->where(fn ($q) => $q->whereBetween('created_at', [$from, $to])->orWhereIn('status', OrderRequest::OPEN_STATUSES))
            ->with([
                'order:id,order_number,vendor_id,user_id,delivery_contact_name,total_amount,payment_method,payment_status,status,paid_at,paymongo_response,paymongo_payment_intent_id',
                'user:id,name,surname',
            ])
            ->orderByDesc('created_at')
            ->get()
            // Replacements don't move money; cases that never asked for money back aren't refunds
            ->filter(fn (OrderRequest $c) => self::refundResolution($c->settled_resolution)
                || ($c->settled_resolution === null && (self::refundResolution($c->resolution_requested) || self::refundResolution($c->offer_resolution))))
            ->values();
    }

    /** One row per case, with its order, payment and books entry, and anything that doesn't add up */
    public function rows(int $vendorId, Collection $cases, CarbonInterface $from): Collection
    {
        $orderIds = $cases->pluck('order_id')->unique();
        $ledger = VendorTransaction::forVendor($vendorId)
            ->whereIn('order_id', $orderIds)
            ->whereIn('category', ['refund', 'order_revenue'])
            ->get(['id', 'order_id', 'category', 'amount', 'created_at', 'metadata']);
        $refunds = $ledger->where('category', 'refund')->groupBy('order_id');
        $payments = $ledger->where('category', 'order_revenue')->keyBy('order_id');

        return $cases->map(function (OrderRequest $c) use ($refunds, $payments, $from) {
            $order = $c->order;
            $status = self::status($c);
            $amount = self::amount($c);
            $booked = $refunds->get($c->order_id, collect());
            // The books entry for this case, or (older entries) the order's refund
            $entry = $booked->first(fn ($t) => (int) data_get($t->metadata, 'order_request_id') === $c->id) ?? ($booked->count() === 1 ? $booked->first() : null);
            $payment = $payments->get($c->order_id);
            $response = is_array($order?->paymongo_response) ? $order->paymongo_response : [];
            $customer = $order?->delivery_contact_name ?: (trim(($c->user?->name ?? '') . ' ' . ($c->user?->surname ?? '')) ?: 'Customer');

            $row = [
                'id' => $c->id,
                'type' => $c->type,
                'status' => $status,
                'stage' => $c->stage(),
                'case_status' => $c->status,
                'customer' => $customer,
                'reason_label' => ReturnCaseService::REASONS[$c->reason_category] ?? 'Something else',
                'reason' => $c->reason,
                'resolution' => $c->settled_resolution ?? $c->offer_resolution ?? $c->resolution_requested,
                'amount' => $amount,
                'requested_amount' => $c->amount_requested === null && $c->resolution_requested === OrderRequest::FULL_REFUND ? (float) ($order?->total_amount ?? 0) : $c->amount_requested,
                'agreed' => $c->settled_resolution !== null,
                'refund_method' => $c->refund_method,
                'refund_method_label' => $c->refund_method ? (ReturnCaseService::REFUND_METHODS[$c->refund_method] ?? $c->refund_method) : null,
                'refund_reference' => $c->refund_reference,
                'opened_at' => $c->created_at?->toIso8601String(),
                'settled_at' => $c->settled_at?->toIso8601String(),
                'sent_at' => $c->refund_sent_at?->toIso8601String(),
                'completed_at' => $c->completed_at?->toIso8601String(),
                'closed_reason' => $c->closed_reason,
                'carried_over' => $c->created_at && $c->created_at->lt($from),
                'order' => [
                    'id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'total' => round((float) ($order?->total_amount ?? 0), 2),
                    'payment_method' => $order?->payment_method,
                    'payment_status' => $order?->payment_status,
                    'status' => $order?->status,
                    'paid_at' => $order?->paid_at?->toIso8601String(),
                    'payment_reference' => $response['reference_number'] ?? ($response['payment_id'] ?? $order?->paymongo_payment_intent_id),
                    'payment_booked' => $payment ? round((float) $payment->amount, 2) : null,
                ],
                'books' => $entry ? ['amount' => round((float) $entry->amount, 2), 'at' => $entry->created_at?->toIso8601String()] : null,
            ];
            $row['issues'] = $this->issues($row, $c, $booked->count());

            return $row;
        });
    }

    private function issues(array $r, OrderRequest $c, int $bookedCount): array
    {
        $issues = [];
        $add = function (string $code, string $message) use (&$issues) {
            $issues[] = ['code' => $code, 'message' => $message];
        };
        $money = fn ($v) => '₱' . number_format((float) $v, 2);

        if ($r['status'] === 'completed' && ! $r['books']) {
            $add('missing_in_books', $bookedCount
                ? 'Confirmed, but the books only have an earlier refund for this order'
                : 'Confirmed by the customer, but missing from the books');
        }
        if ($r['books'] && abs($r['books']['amount'] - $r['amount']) > 0.009) {
            $add('books_amount', "The books show {$money($r['books']['amount'])} but {$money($r['amount'])} was agreed");
        }
        if ($r['amount'] - $r['order']['total'] > 0.009) {
            $add('over_refund', "More than the order total of {$money($r['order']['total'])}");
        }
        if (in_array($r['status'], ['approved', 'processing', 'completed'], true) && $r['order']['payment_status'] === 'unpaid') {
            $add('unpaid_order', "The order was never paid, so there's nothing to refund");
        }
        if ($r['status'] === 'completed' && $c->settled_resolution === OrderRequest::FULL_REFUND && $r['order']['payment_status'] !== 'refunded') {
            $add('order_not_refunded', "Fully refunded, but the order isn't marked as refunded");
        }
        if ($r['status'] === 'processing' && $c->refund_sent_at?->lt(now()->subDays(self::FOLLOW_UP_DAYS))) {
            $add('unconfirmed', 'Sent ' . $c->refund_sent_at->diffInDays(now()) . " days ago and the customer hasn't confirmed");
        }

        return $issues;
    }

    /** Refunds on the books in the period that no case accounts for */
    public function unmatchedBooks(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $entries = VendorTransaction::forVendor($vendorId)
            ->where('category', 'refund')
            ->whereBetween('created_at', [$from, $to])
            ->with('order:id,order_number')
            ->get();
        $caseIds = $entries->map(fn ($t) => (int) data_get($t->metadata, 'order_request_id'))->filter()->unique();
        $known = OrderRequest::whereIn('id', $caseIds)->pluck('id')->flip();

        return $entries
            ->filter(fn ($t) => ! $known->has((int) data_get($t->metadata, 'order_request_id')))
            ->map(fn ($t) => [
                'order_id' => $t->order_id,
                'order_number' => $t->order?->order_number,
                'amount' => round((float) $t->amount, 2),
                'at' => $t->created_at?->toIso8601String(),
                'message' => 'Refund on the books with no refund case behind it',
            ])
            ->values()
            ->all();
    }

    public function summary(Collection $rows, int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $by = fn (string $s) => $rows->where('status', $s);
        $refunded = (float) VendorTransaction::forVendor($vendorId)->where('category', 'refund')->whereBetween('created_at', [$from, $to])->sum('amount');
        $sales = (float) VendorTransaction::forVendor($vendorId)->where('category', 'order_revenue')->whereBetween('created_at', [$from, $to])->sum('amount');
        $done = $by('completed')->filter(fn ($r) => $r['completed_at']);
        $days = $done->map(fn ($r) => Carbon::parse($r['opened_at'])->diffInHours(Carbon::parse($r['completed_at'])) / 24);

        return [
            'statuses' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => ['count' => $by($s)->count(), 'amount' => round((float) $by($s)->sum('amount'), 2)]])->all(),
            'refunded' => round($refunded, 2),
            'sales' => round($sales, 2),
            'refund_rate' => $sales > 0 ? round($refunded / $sales * 100, 1) : null,
            'owed' => round((float) $rows->whereIn('status', ['approved', 'processing'])->sum('amount'), 2),
            'average_days' => $days->isNotEmpty() ? round($days->avg(), 1) : null,
            'reasons' => $rows->groupBy('reason_label')
                ->map(fn ($c, $label) => ['label' => $label, 'count' => $c->count(), 'amount' => round((float) $c->sum('amount'), 2)])
                ->sortByDesc('count')->values()->all(),
            'methods' => $rows->filter(fn ($r) => $r['refund_method'])->groupBy('refund_method_label')
                ->map(fn ($c, $label) => ['label' => $label, 'count' => $c->count(), 'amount' => round((float) $c->sum('amount'), 2)])
                ->sortByDesc('amount')->values()->all(),
        ];
    }

    /** The case with its full conversation, for the detail panel */
    public function detail(OrderRequest $c): array
    {
        return $this->cases->detail($c);
    }
}
