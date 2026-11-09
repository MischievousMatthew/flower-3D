<?php

namespace App\Services;

use App\Models\FundingRequest;
use App\Models\FundingRequestPayment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
use App\Support\SupplierPaymentTerms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Where an approved funding request's money stands.
 *
 * Approving sets a budget; nothing is paid then. The amount to pay comes from
 * the purchase order made from the request, at the time the supplier's terms
 * say:
 *  - pay before delivery: what the supplier confirmed they can supply
 *  - pay on/after delivery: what was actually received
 * plus the delivery fee, never more than the budget. Whatever the supplier
 * didn't need is simply never paid. If a supplier was paid first and then
 * delivered less, they owe the difference until it's refunded or kept as credit.
 */
class FundingPaymentService
{
    /** Order statuses where the supplier hasn't confirmed yet */
    private const NOT_CONFIRMED = ['draft', 'pending_approval', 'pending'];

    public function state(FundingRequest $f): ?array
    {
        if ($f->request_status !== FundingRequest::STATUS_APPROVED) {
            return null;
        }

        $payments = $f->relationLoaded('payments') ? $f->payments : $f->payments()->get();
        $budget = round((float) $f->approved_amount, 2);

        // Approved before payments were tracked: it was paid from the shop balance on approval
        if ($payments->isEmpty() && $f->payment_status === 'paid') {
            return [
                'stage' => 'paid',
                'legacy' => true,
                'budget' => $budget,
                'paid' => $budget,
                'to_pay' => 0.0,
                'supplier_owes' => 0.0,
                'payments' => [],
            ];
        }

        $order = $f->linked_order_id ? ($f->relationLoaded('linkedOrder') ? $f->linkedOrder : $f->linkedOrder()->first()) : null;
        $order?->loadMissing('items', 'supplier');
        // The order's supplier decides until money moves (the order may have gone back
        // to draft and to another supplier); after that, the terms it was paid under stand
        $liveTerms = $order?->supplier ? $order->supplier->payment_terms : null;
        $useLive = $order?->supplier && $payments->isEmpty();
        $timing = $useLive ? SupplierPaymentTerms::timing($liveTerms) : ($f->payment_timing ?: SupplierPaymentTerms::timing($liveTerms));
        $terms = $useLive ? $liveTerms : ($f->supplier_terms ?: $liveTerms);
        $dueDays = $useLive ? SupplierPaymentTerms::dueDays($liveTerms) : $f->payment_due_days;
        $sum = fn (string $kind) => round((float) $payments->where('kind', $kind)->sum('amount'), 2);
        $paid = $sum(FundingRequestPayment::KIND_PAYMENT);
        $netPaid = round($paid - $sum(FundingRequestPayment::KIND_REFUND) - $sum(FundingRequestPayment::KIND_CREDIT), 2);
        $delivery = round((float) $f->logistics_cost, 2);

        $base = [
            'legacy' => false,
            'timing' => $timing,
            'supplier_terms' => $terms,
            'due_days' => $dueDays,
            'budget' => $budget,
            'delivery_fee' => $delivery,
            'paid' => $paid,
            'net_paid' => $netPaid,
            'payments' => $payments->sortBy('id')->values()->map(fn ($p) => [
                'id' => $p->id,
                'kind' => $p->kind,
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'payment_channel' => $p->payment_channel,
                'reference' => $p->reference,
                'paid_on' => $p->paid_on?->toDateString(),
                'note' => $p->note,
                'recorded_by_name' => $p->recorded_by_name,
            ])->all(),
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'supplier_name' => $order->supplier?->company_name,
                'supplier_confirmed_at' => $order->supplier_confirmed_at?->toIso8601String(),
                'completed_at' => $order->completed_at?->toIso8601String(),
            ] : null,
        ];

        if (!$order) {
            return $base + ['stage' => 'needs_order', 'due' => null, 'to_pay' => 0.0, 'released' => null, 'supplier_owes' => 0.0];
        }

        $confirmedValue = round($order->items->sum(fn ($i) => max(0, (int) $i->quantity - (int) $i->cancelled_qty) * (float) $i->price), 2);
        $receivedValue = round($order->items->sum(fn ($i) => (int) $i->received_qty * (float) $i->price), 2);
        $cancelled = $order->status === 'cancelled';
        $confirmed = !$cancelled && !in_array($order->status, self::NOT_CONFIRMED, true);
        // Everything that will come has come (or nothing will)
        $done = $cancelled || ($confirmed && $order->items->every(fn ($i) => $i->outstanding() === 0));
        $delivered = min($budget, $receivedValue + ($receivedValue > 0 ? $delivery : 0));

        $due = null;
        if ($timing === SupplierPaymentTerms::BEFORE && $confirmed) {
            $due = $done ? $delivered : min($budget, $confirmedValue + ($confirmedValue > 0 ? $delivery : 0));
        } elseif ($timing === SupplierPaymentTerms::AFTER && $done && !$cancelled) {
            $due = $delivered;
        }
        $due = $due === null ? null : round($due, 2);
        $toPay = $due === null ? 0.0 : round(max(0, $due - $netPaid), 2);
        $owes = $done ? round(max(0, $netPaid - $delivered), 2) : 0.0;

        $dueDate = null;
        if ($timing === SupplierPaymentTerms::AFTER && $done && $order->completed_at) {
            $dueDate = Carbon::parse($order->completed_at)->addDays((int) $dueDays)->toDateString();
        }

        $stage = match (true) {
            $owes > 0 => 'supplier_owes',
            $toPay > 0 => 'to_pay',
            $netPaid > 0 && $due !== null => 'paid',
            $cancelled => 'cancelled',
            !$confirmed => 'with_supplier',
            $timing === SupplierPaymentTerms::AFTER && !$done => 'awaiting_delivery',
            default => 'nothing_due',
        };

        return $base + [
            'stage' => $stage,
            'confirmed_value' => $confirmed ? $confirmedValue : null,
            'received_value' => $receivedValue,
            'due' => $due,
            'due_date' => $dueDate,
            'overdue' => $dueDate !== null && $toPay > 0 && $dueDate < now()->toDateString(),
            'to_pay' => $toPay,
            'released' => $due === null ? null : round(max(0, $budget - $due), 2),
            'supplier_owes' => $owes,
        ];
    }

    /** Record money sent to the supplier */
    public function pay(int $ownerId, int $id, array $data): array
    {
        return DB::transaction(function () use ($ownerId, $id, $data) {
            $f = FundingRequest::where('owner_id', $ownerId)->lockForUpdate()->findOrFail($id);
            $state = $this->state($f);
            if (!$state || $state['legacy']) {
                throw new HttpException(422, 'This request has nothing to pay.');
            }
            if ($state['stage'] !== 'to_pay') {
                throw new HttpException(422, match ($state['stage']) {
                    'needs_order' => 'Procurement hasn\'t made the purchase order yet.',
                    'with_supplier' => 'Wait until the supplier confirms what they can supply.',
                    'awaiting_delivery' => 'This supplier is paid after delivery. Pay once the stock is in.',
                    default => 'There\'s nothing left to pay on this request.',
                });
            }
            $amount = round((float) $data['amount'], 2);
            if ($amount > $state['to_pay']) {
                throw new HttpException(422, sprintf('You can pay up to ₱%s: what the supplier %s, less what was already paid.',
                    number_format($state['to_pay'], 2), $state['timing'] === SupplierPaymentTerms::BEFORE ? 'confirmed' : 'delivered'));
            }

            // Keep the terms it was paid under, even if the supplier's change later
            $f->update([
                'payment_timing' => $state['timing'],
                'supplier_terms' => $state['supplier_terms'] ? mb_substr($state['supplier_terms'], 0, 60) : null,
                'payment_due_days' => $state['due_days'],
            ]);

            $f->payments()->create([
                'owner_id' => $ownerId,
                'purchase_order_id' => $f->linked_order_id,
                'kind' => FundingRequestPayment::KIND_PAYMENT,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? $f->payment_method,
                'payment_channel' => $data['payment_channel'] ?? $f->payment_channel,
                'reference' => $data['reference'],
                'paid_on' => $data['paid_on'],
                'note' => $data['note'] ?? null,
                'recorded_by_name' => PurchaseOrderEvent::nameOf(auth()->user()),
            ]);

            $after = $this->state($f->fresh());
            if ($after['to_pay'] <= 0) {
                $f->update(['payment_status' => 'paid', 'paid_at' => now()]);
            }
            if ($order = PurchaseOrder::find($f->linked_order_id)) {
                PurchaseOrderEvent::record($order, 'paid', null, null, sprintf('Paid ₱%s through %s (ref. %s)',
                    number_format($amount, 2), $data['payment_channel'] ?? $f->payment_channel ?? 'bank or e-wallet', $data['reference']));
            }

            return $this->state($f->fresh());
        });
    }

    /** Close what a supplier owes: they refunded it, or it's kept as credit with them */
    public function settleCredit(int $ownerId, int $id, array $data): array
    {
        return DB::transaction(function () use ($ownerId, $id, $data) {
            $f = FundingRequest::where('owner_id', $ownerId)->lockForUpdate()->findOrFail($id);
            $state = $this->state($f);
            if (!$state || ($state['supplier_owes'] ?? 0) <= 0) {
                throw new HttpException(422, 'The supplier doesn\'t owe anything on this request.');
            }
            $refund = $data['outcome'] === 'refund';
            $amount = $refund ? round((float) $data['amount'], 2) : $state['supplier_owes'];
            if ($amount <= 0 || $amount > $state['supplier_owes']) {
                throw new HttpException(422, sprintf('The supplier owes ₱%s.', number_format($state['supplier_owes'], 2)));
            }

            $f->payments()->create([
                'owner_id' => $ownerId,
                'purchase_order_id' => $f->linked_order_id,
                'kind' => $refund ? FundingRequestPayment::KIND_REFUND : FundingRequestPayment::KIND_CREDIT,
                'amount' => $amount,
                'payment_method' => $refund ? ($data['payment_method'] ?? $f->payment_method) : null,
                'payment_channel' => $refund ? ($data['payment_channel'] ?? $f->payment_channel) : null,
                'reference' => $data['reference'] ?? null,
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'recorded_by_name' => PurchaseOrderEvent::nameOf(auth()->user()),
            ]);

            $after = $this->state($f->fresh());
            if (($after['supplier_owes'] ?? 0) <= 0) {
                $f->update(['supplier_credit_settled_at' => now()]);
                // A cancelled order whose money is sorted out: the request can be ordered again
                if (($after['order']['status'] ?? null) === 'cancelled' && round($after['net_paid'], 2) <= 0) {
                    $f->update(['linked_order_id' => null, 'payment_timing' => null, 'supplier_terms' => null, 'payment_due_days' => null]);
                }
            }

            return $this->state($f->fresh());
        });
    }
}
