<?php

namespace App\Services;

use App\Models\FundingRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Receiving a delivery against a purchase order: what came, what passed
 * inspection, what was turned away or is missing. Only accepted stems go
 * into stock; rejected and missing ones stay owed unless the line is closed.
 */
class ReceivingService
{
    /** What the inspector saw → the batch's starting condition */
    private const BATCH_CONDITION = ['fresh' => 'fresh', 'slightly_open' => 'aging', 'wilting' => 'wilting', 'damaged' => 'fresh'];

    private const REASON_TEXT = [
        'wilted' => 'wilted', 'damaged' => 'damaged', 'pests_mold' => 'pests or mold', 'wrong_item' => 'wrong item',
        'short_stems' => 'short stems', 'wrong_color' => 'wrong color', 'other' => 'other',
    ];

    public function __construct(private readonly OrderService $orders, private readonly ReceiptFileStore $files) {}

    /** Orders a delivery can be received against, with what's still to come on each line */
    public function openOrders(int $ownerId)
    {
        return PurchaseOrder::where('owner_id', $ownerId)
            ->whereIn('status', ['pending', 'processing', 'shipped', 'received', 'partially_received'])
            ->with('supplier:id,company_name,logo,email,phone', 'items')
            ->orderByRaw('CASE WHEN expected_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expected_date')
            ->orderByDesc('id')
            ->get()
            ->each(fn ($o) => $o->items->each(fn ($i) => $i->setAttribute('outstanding', $i->outstanding())));
    }

    /**
     * @param array{received_date: string, delivery_ref?: ?string, notes?: ?string, lines: array} $data
     * @param UploadedFile[] $photos
     */
    public function inspect(int $orderId, array $data, int $ownerId, ?UploadedFile $receiptFile, array $photos, $actor): PurchaseOrderReceipt
    {
        $order = PurchaseOrder::where('owner_id', $ownerId)->with('items')->findOrFail($orderId);
        if (! in_array($order->status, ['pending', 'processing', 'shipped', 'received', 'partially_received'], true)) {
            throw new \RuntimeException($order->status === 'completed'
                ? 'Everything on this order has already been received.'
                : 'Approve the order before receiving a delivery for it.');
        }

        // Check every line before storing anything
        $plan = [];
        foreach ($data['lines'] as $n => $line) {
            $item = $order->items->firstWhere('id', (int) $line['item_id']);
            if (! $item) {
                continue;
            }
            $owed = $item->outstanding();
            $delivered = (int) ($line['delivered_qty'] ?? 0);
            $accepted = (int) ($line['accepted_qty'] ?? 0);
            $close = (bool) ($line['close_short'] ?? false);
            if ($delivered === 0 && ! $close) {
                continue;
            }
            $label = $item->product_name;
            if ($accepted > $delivered) {
                throw $this->lineError($n, 'accepted_qty', "{$label}: you can't accept more than was delivered.");
            }
            if ($accepted > $owed) {
                throw $this->lineError($n, 'accepted_qty', "{$label}: only {$owed} are still owed on this order.");
            }
            if ($delivered > 0 && empty($line['condition'])) {
                throw $this->lineError($n, 'condition', "{$label}: say how the delivery looked.");
            }
            if ($delivered - $accepted > 0 && empty($line['reject_reason'])) {
                throw $this->lineError($n, 'reject_reason', "{$label}: say why stems were turned away.");
            }
            if ($accepted > 0 && ! $item->product_id) {
                throw $this->lineError($n, 'accepted_qty', "{$label} isn't linked to a stock item, so it can't go into stock. Link it on the order first.");
            }
            $plan[] = compact('item', 'line', 'owed', 'delivered', 'accepted', 'close');
        }
        if (! $plan) {
            throw new \RuntimeException('Enter what was delivered for at least one item.');
        }

        // Files first: if anything below fails, they're removed again
        $stored = [];
        try {
            if ($receiptFile) {
                $stored[] = ['kind' => 'receipt', 'file' => $receiptFile] + $this->files->store($receiptFile, 'delivery-receipts');
            }
            foreach ($photos as $photo) {
                $stored[] = ['kind' => 'photo', 'file' => $photo] + $this->files->store($photo, 'delivery-photos');
            }

            return DB::transaction(fn () => $this->record($order, $data, $plan, $stored, $actor));
        } catch (\Throwable $e) {
            foreach ($stored as $f) {
                $this->files->remove($f['public_id'] ?? null);
            }
            throw $e;
        }
    }

    private function record(PurchaseOrder $order, array $data, array $plan, array $stored, $actor): PurchaseOrderReceipt
    {
        $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->with('items')->first();
        $funding = FundingRequest::where('linked_order_id', $order->id)->first();

        $receipt = PurchaseOrderReceipt::create([
            'owner_id'         => $order->owner_id,
            'purchase_order_id' => $order->id,
            'receipt_number'   => 'RCV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'delivery_ref'     => $data['delivery_ref'] ?? null,
            'received_date'    => $data['received_date'],
            'notes'            => $data['notes'] ?? null,
            'received_by_type' => $actor ? class_basename($actor) : null,
            'received_by_id'   => $actor?->id,
            'received_by_name' => PurchaseOrderEvent::nameOf($actor),
        ]);

        $totals = ['delivered' => 0, 'accepted' => 0, 'rejected' => 0, 'missing' => 0];
        $summary = [];
        foreach ($plan as ['item' => $planned, 'line' => $line, 'delivered' => $delivered, 'accepted' => $accepted, 'close' => $close]) {
            $item = $order->items->firstWhere('id', $planned->id);
            $owed = $item->outstanding();
            $rejected = $delivered - $accepted;
            $missing = max(0, $owed - $delivered);

            $batch = null;
            if ($accepted > 0) {
                $batch = $this->orders->receiveItem($order, $item, $accepted, $funding, [
                    'received_date'         => $data['received_date'],
                    'warehouse_location_id' => $line['warehouse_location_id'] ?? null,
                    'expiration_date'       => $line['expiration_date'] ?? null,
                    'harvest_date'          => $line['harvest_date'] ?? null,
                    'freshness_days'        => $line['freshness_days'] ?? null,
                    'condition_status'      => self::BATCH_CONDITION[$line['condition'] ?? 'fresh'] ?? 'fresh',
                    'notes'                 => "Received from {$order->order_number} ({$receipt->receipt_number})",
                ]);
            }
            $item->rejected_qty = (int) $item->rejected_qty + $rejected;
            // Whatever is still owed after this delivery won't come
            if ($close) {
                $item->cancelled_qty = (int) $item->cancelled_qty + $item->outstanding();
            }
            $item->save();

            $receipt->lines()->create([
                'purchase_order_item_id' => $item->id,
                'product_id'    => $item->product_id,
                'product_name'  => $item->product_name,
                'expected_qty'  => $owed,
                'delivered_qty' => $delivered,
                'accepted_qty'  => $accepted,
                'rejected_qty'  => $rejected,
                'missing_qty'   => $missing,
                'condition'     => $delivered > 0 ? ($line['condition'] ?? null) : null,
                'checks'        => array_values(array_intersect($line['checks'] ?? [], PurchaseOrderReceipt::CHECKS)),
                'reject_reason' => $rejected > 0 ? ($line['reject_reason'] ?? null) : null,
                'close_short'   => $close,
                'warehouse_batch_id' => $batch?->id,
                'notes'         => $line['notes'] ?? null,
            ]);

            foreach (['delivered', 'accepted', 'rejected', 'missing'] as $k) {
                $totals[$k] += ${$k};
            }
            $bits = array_filter([
                $accepted ? "{$accepted} accepted" : null,
                $rejected ? "{$rejected} rejected (" . (self::REASON_TEXT[$line['reject_reason']] ?? 'other') . ')' : null,
                $missing ? "{$missing} missing" . ($close ? ', not coming' : '') : null,
            ]);
            $summary[] = "{$item->product_name}: " . implode(', ', $bits ?: ['closed']);
        }

        $receipt->update([
            'delivered_total' => $totals['delivered'],
            'accepted_total'  => $totals['accepted'],
            'rejected_total'  => $totals['rejected'],
            'missing_total'   => $totals['missing'],
        ]);
        foreach ($stored as $f) {
            $receipt->files()->create([
                'owner_id'      => $order->owner_id,
                'kind'          => $f['kind'],
                'url'           => $f['url'],
                'public_id'     => $f['public_id'] ?? null,
                'mime'          => $f['file']->getMimeType(),
                'original_name' => mb_substr($f['file']->getClientOriginalName(), 0, 200),
                'created_at'    => now(),
            ]);
        }

        // Completed once nothing is owed; part received once anything has gone into stock
        $from = $order->status;
        $done = $order->items->every(fn ($i) => $i->outstanding() === 0);
        $anyIn = $order->items->sum('received_qty') > 0;
        if ($done) {
            $order->quietlyMoveTo(['status' => 'completed', 'completed_at' => now()]);
        } elseif ($anyIn && $order->status !== 'partially_received') {
            $order->quietlyMoveTo(['status' => 'partially_received']);
        }
        PurchaseOrderEvent::record($order, 'inspected', $from, $order->status, "{$receipt->receipt_number} · " . implode(' · ', $summary));

        return $receipt->fresh(['lines', 'files']);
    }

    private function lineError(int $n, string $field, string $message): \Illuminate\Validation\ValidationException
    {
        return \Illuminate\Validation\ValidationException::withMessages(["lines.{$n}.{$field}" => $message]);
    }
}
