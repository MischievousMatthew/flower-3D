<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStockAdjustment;
use App\Models\PurchaseOrderEvent;
use App\Models\WarehouseBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inventory & stock replenishment on the existing product stock count
 * (products.quantity_in_stock): what's on hand, what's low, what to order
 * and how much, and every manual change, including damage.
 */
class InventoryStockService
{
    public const KINDS = ['flowers', 'greenery', 'wrapping', 'ribbons', 'vases', 'supplies'];

    /** Purchase orders still bringing stock in */
    private const OPEN_PO = ['pending', 'processing', 'shipped', 'received', 'partially_received'];

    private const USAGE_DAYS = 30;

    public function __construct(private readonly WarehouseBatchService $batches) {}

    /** Kind of stock: what the shop set, or a good guess from the category and name */
    public static function kindOf(object $p): string
    {
        if (! empty($p->stock_kind) && in_array($p->stock_kind, self::KINDS, true)) {
            return $p->stock_kind;
        }
        $text = mb_strtolower(($p->category ?? '') . ' ' . ($p->product_name ?? ''));
        return match (true) {
            (bool) preg_match('/ribbon/', $text)                        => 'ribbons',
            (bool) preg_match('/wrap|paper|sleeve|tissue|cellophane/', $text) => 'wrapping',
            (bool) preg_match('/vase|jar|pot|basket|box|container/', $text)   => 'vases',
            (bool) preg_match('/green|leaf|fern|eucalyptus|foliage/', $text)  => 'greenery',
            (bool) preg_match('/foam|wire|tape|card|tool|pin/', $text)        => 'supplies',
            default                                                    => 'flowers',
        };
    }

    public function overview(int $ownerId): array
    {
        $products = DB::table('products')
            ->where('owner_id', $ownerId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'discontinued')
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'sku', 'category', 'stock_kind', 'stock_unit', 'moderation_status', 'status',
                'quantity_in_stock', 'min_stock_level', 'max_stock_level', 'supplier_lead_time', 'purchase_price']);
        $ids = $products->pluck('id');

        // Still to come on approved purchase orders
        $onOrder = DB::table('purchase_order_items as i')
            ->join('purchase_orders as o', 'o.id', '=', 'i.purchase_order_id')
            ->where('o.owner_id', $ownerId)
            ->whereIn('o.status', self::OPEN_PO)
            ->whereIn('i.product_id', $ids)
            ->get(['i.product_id', 'i.quantity', 'i.received_qty', 'i.cancelled_qty'])
            ->groupBy('product_id')
            ->map(fn ($rows) => (int) $rows->sum(fn ($r) => max(0, $r->quantity - $r->received_qty - $r->cancelled_qty)));

        // Used in the last 30 days: sold to customers, plus used in arrangements
        $since = now()->subDays(self::USAGE_DAYS);
        $sold = DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->where('o.vendor_id', $ownerId)
            ->whereNotIn('o.status', ['cancelled', 'refunded'])
            ->where('o.created_at', '>=', $since)
            ->whereIn('i.product_id', $ids)
            ->groupBy('i.product_id')
            ->selectRaw('i.product_id, SUM(i.quantity) as qty')
            ->pluck('qty', 'product_id');
        $used = DB::table('product_stock_adjustments')
            ->where('owner_id', $ownerId)
            ->whereIn('reason', ['used', 'sold_offline'])
            ->where('change', '<', 0)
            ->where('created_at', '>=', $since)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(-"change") as qty')
            ->pluck('qty', 'product_id');

        // Who it was last bought from, and for how much
        $lastBuy = DB::table('purchase_order_items as i')
            ->join('purchase_orders as o', 'o.id', '=', 'i.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'o.supplier_id')
            ->where('o.owner_id', $ownerId)
            ->whereNotIn('o.status', ['draft', 'pending_approval'])
            ->whereIn('i.product_id', $ids)
            ->orderByDesc('o.created_at')
            ->orderByDesc('o.id')
            ->get(['i.product_id', 'i.price', 'i.unit', 'o.supplier_id', 's.company_name', 's.status as supplier_status', 's.lead_time_days', 'o.created_at'])
            ->unique('product_id')
            ->keyBy('product_id');

        $batchTracked = DB::table('warehouse_batches')
            ->where('owner_id', $ownerId)
            ->where('status', 'active')
            ->whereIn('product_id', $ids)
            ->distinct()
            ->pluck('product_id')
            ->flip();

        $damaged = $this->damagedSince($ownerId, $since);

        $items = $products->map(function ($p) use ($onOrder, $sold, $used, $lastBuy, $batchTracked, $damaged) {
            $onHand = (int) $p->quantity_in_stock;
            $min = (int) $p->min_stock_level;
            $target = $p->max_stock_level !== null ? (int) $p->max_stock_level : null;
            $coming = (int) ($onOrder[$p->id] ?? 0);
            $use = (int) ($sold[$p->id] ?? 0) + (int) ($used[$p->id] ?? 0);
            $daily = $use / self::USAGE_DAYS;
            $buy = $lastBuy[$p->id] ?? null;
            $lead = $p->supplier_lead_time !== null ? (int) $p->supplier_lead_time : ($buy?->lead_time_days !== null ? (int) $buy->lead_time_days : null);

            $state = match (true) {
                $onHand <= 0                      => 'out',
                $min > 0 && $onHand <= $min       => 'low',
                $target !== null && $onHand > $target => 'over',
                default                           => 'ok',
            };

            return [
                'id'            => $p->id,
                'name'          => $p->product_name,
                'sku'           => $p->sku,
                'kind'          => self::kindOf($p),
                'kind_set'      => (bool) $p->stock_kind,
                'unit'          => $p->stock_unit,
                'material'      => $p->moderation_status === Product::MODERATION_INTERNAL,
                'status'        => $p->status,
                'on_hand'       => $onHand,
                'min'           => $min,
                'target'        => $target,
                'on_order'      => $coming,
                'used_30d'      => $use,
                'damaged_30d'   => (int) ($damaged[$p->id] ?? 0),
                'days_left'     => $daily > 0 ? (int) floor($onHand / $daily) : null,
                'lead_days'     => $lead,
                'state'         => $state,
                'batch_tracked' => isset($batchTracked[$p->id]),
                'unit_cost'     => (float) ($buy?->price ?? $p->purchase_price ?? 0),
                'suggestion'    => $this->suggest($onHand, $coming, $min, $target, $daily, $lead),
                'last_supplier' => $buy && $buy->supplier_id ? [
                    'id' => $buy->supplier_id, 'name' => $buy->company_name, 'active' => $buy->supplier_status === 'active',
                    'price' => (float) $buy->price, 'unit' => $buy->unit, 'at' => $buy->created_at,
                ] : null,
            ];
        });

        return [
            'items'  => $items->values(),
            'counts' => [
                'total'     => $items->count(),
                'out'       => $items->where('state', 'out')->count(),
                'low'       => $items->where('state', 'low')->count(),
                'reorder'   => $items->where('suggestion.qty', '>', 0)->count(),
                'no_min'    => $items->where('min', 0)->count(),
                'by_kind'   => collect(self::KINDS)->mapWithKeys(fn ($k) => [$k => $items->where('kind', $k)->count()]),
                'stock_value' => round($items->sum(fn ($i) => $i['on_hand'] * $i['unit_cost']), 2),
                'damaged_30d' => (int) $items->sum('damaged_30d'),
            ],
        ];
    }

    /**
     * How much to order: when what's on hand plus what's coming is at or under
     * the minimum, fill up to the target (or twice the minimum), plus what will
     * be used while the next order is on its way.
     */
    private function suggest(int $onHand, int $coming, int $min, ?int $target, float $daily, ?int $lead): array
    {
        if ($min <= 0 && $onHand > 0) {
            return ['qty' => 0, 'why' => 'Set a minimum to get reorder suggestions.'];
        }
        if ($onHand + $coming > $min) {
            return ['qty' => 0, 'why' => $coming ? "{$coming} already on order." : 'Enough on hand.'];
        }
        $fillTo = $target ?? max($min * 2, 1);
        $duringLead = $lead && $daily > 0 ? (int) ceil($daily * $lead) : 0;
        $qty = max(0, $fillTo + $duringLead - $onHand - $coming);

        $parts = [$target !== null ? "fills up to your target of {$target}" : "fills up to {$fillTo}, twice the minimum"];
        if ($duringLead) {
            $parts[] = "plus {$duringLead} for the {$lead} " . ($lead === 1 ? 'day' : 'days') . ' it takes to arrive';
        }
        if ($coming) {
            $parts[] = "less {$coming} already on order";
        }

        return ['qty' => $qty, 'why' => ucfirst(implode(', ', $parts)) . '.'];
    }

    /** Units thrown away or damaged per product since a date (adjustments and warehouse culls) */
    private function damagedSince(int $ownerId, Carbon $since)
    {
        $adjusted = DB::table('product_stock_adjustments')
            ->where('owner_id', $ownerId)->where('reason', 'damaged')->where('change', '<', 0)->where('created_at', '>=', $since)
            ->groupBy('product_id')->selectRaw('product_id, SUM(-"change") as qty')->pluck('qty', 'product_id');
        $culled = DB::table('warehouse_batch_logs as l')
            ->join('warehouse_batches as b', 'b.id', '=', 'l.warehouse_batch_id')
            ->where('b.owner_id', $ownerId)->where('l.event_type', 'CULLED')->where('l.created_at', '>=', $since)
            ->where(fn ($q) => $q->whereNull('l.notes')->orWhere('l.notes', 'not like', 'Stock adjustment%'))
            ->groupBy('b.product_id')->selectRaw('b.product_id, SUM(-l.qty_change) as qty')->pluck('qty', 'product_id');

        return collect($adjusted->keys())->merge($culled->keys())->unique()
            ->mapWithKeys(fn ($id) => [$id => (int) ($adjusted[$id] ?? 0) + (int) ($culled[$id] ?? 0)]);
    }

    /** Minimum, target, kind, unit and lead time */
    public function setLevels(Product $product, array $data): Product
    {
        $product->update(array_intersect_key($data, array_flip(['min_stock_level', 'max_stock_level', 'stock_kind', 'stock_unit', 'supplier_lead_time'])));

        return $product->fresh();
    }

    /**
     * A manual change to stock. For flowers kept in warehouse batches the
     * batches change too (oldest first), so the next batch update doesn't undo it.
     */
    public function adjust(Product $product, string $mode, int $qty, string $reason, ?string $note, $actor): array
    {
        return DB::transaction(function () use ($product, $mode, $qty, $reason, $note, $actor) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = (int) $product->quantity_in_stock;
            $after = match ($mode) {
                'add'    => $before + $qty,
                'remove' => $before - $qty,
                'set'    => $qty,
            };
            if ($after < 0) {
                throw new \RuntimeException("Only {$before} in stock, so you can't take away {$qty}.");
            }
            if ($after === $before) {
                throw new \RuntimeException('That leaves the stock unchanged.');
            }

            $label = ProductStockAdjustment::REASONS[$reason] ?? $reason;
            $batchNote = "Stock adjustment: {$label}" . ($note ? " · {$note}" : '');
            $active = WarehouseBatch::where('owner_id', $product->owner_id)->where('product_id', $product->id)->where('status', 'active')->where('qty_remaining', '>', 0)
                ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END')->orderBy('expiration_date')->orderBy('received_date')->orderBy('id')
                ->get();

            if ($active->isNotEmpty()) {
                if ($after < $before) {
                    // Take it out of the batches that expire first
                    $left = min($before - $after, (int) $active->sum('qty_remaining'));
                    foreach ($active as $batch) {
                        if ($left <= 0) {
                            break;
                        }
                        $take = min($left, (int) $batch->qty_remaining);
                        $batch->reduceQuantity($take, $reason === 'damaged' ? 'CULLED' : 'QUANTITY_ADJUSTED', $actor?->id, $batchNote);
                        $left -= $take;
                    }
                } else {
                    // More than the batches show: a batch for the difference
                    WarehouseBatch::create([
                        'owner_id'         => $product->owner_id,
                        'product_id'       => $product->id,
                        'qty_received'     => $after - $before,
                        'qty_remaining'    => $after - $before,
                        'batch_number'     => WarehouseBatch::generateBatchNumber($product->sku ?: 'ADJ', now()->toDateString()),
                        'barcode'          => 'WB-' . strtoupper(base_convert((string) time(), 10, 36)) . '-' . strtoupper(Str::random(4)),
                        'received_date'    => now()->toDateString(),
                        'condition_status' => 'fresh',
                        'status'           => 'active',
                        'notes'            => $batchNote,
                    ]);
                }
            }
            $product->update(['quantity_in_stock' => $after]);

            $adjustment = ProductStockAdjustment::create([
                'owner_id'        => $product->owner_id,
                'product_id'      => $product->id,
                'change'          => $after - $before,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'reason'          => $reason,
                'note'            => $note,
                'actor_name'      => PurchaseOrderEvent::nameOf($actor),
            ]);

            return ['product' => $product->fresh(), 'adjustment' => $adjustment];
        });
    }

    /** Recent stock changes: manual adjustments and warehouse culls, newest first */
    public function history(int $ownerId, ?string $filter = null, ?int $productId = null, int $limit = 30): array
    {
        $adjustments = DB::table('product_stock_adjustments as a')
            ->leftJoin('products as p', 'p.id', '=', 'a.product_id')
            ->where('a.owner_id', $ownerId)
            ->when($filter === 'damaged', fn ($q) => $q->where('a.reason', 'damaged'))
            ->when($productId, fn ($q) => $q->where('a.product_id', $productId))
            ->orderByDesc('a.created_at')->orderByDesc('a.id')->limit($limit)
            ->get(['a.id', 'a.product_id', 'p.product_name', 'p.stock_unit', 'a.change', 'a.quantity_before', 'a.quantity_after', 'a.reason', 'a.note', 'a.actor_name', 'a.created_at'])
            ->map(fn ($a) => ['source' => 'adjustment', 'key' => "a{$a->id}", 'product_id' => $a->product_id, 'product' => $a->product_name, 'unit' => $a->stock_unit,
                'change' => (int) $a->change, 'before' => (int) $a->quantity_before, 'after' => (int) $a->quantity_after,
                'reason' => $a->reason, 'reason_label' => ProductStockAdjustment::REASONS[$a->reason] ?? $a->reason, 'note' => $a->note, 'by' => $a->actor_name, 'at' => $a->created_at]);

        $culls = DB::table('warehouse_batch_logs as l')
            ->join('warehouse_batches as b', 'b.id', '=', 'l.warehouse_batch_id')
            ->leftJoin('products as p', 'p.id', '=', 'b.product_id')
            ->where('b.owner_id', $ownerId)
            ->where('l.event_type', 'CULLED')
            ->where(fn ($q) => $q->whereNull('l.notes')->orWhere('l.notes', 'not like', 'Stock adjustment%'))
            ->when($productId, fn ($q) => $q->where('b.product_id', $productId))
            ->orderByDesc('l.created_at')->orderByDesc('l.id')->limit($limit)
            ->get(['l.id', 'b.product_id', 'p.product_name', 'p.stock_unit', 'l.qty_change', 'l.notes', 'l.created_at', 'b.batch_number'])
            ->map(fn ($c) => ['source' => 'cull', 'key' => "c{$c->id}", 'product_id' => $c->product_id, 'product' => $c->product_name, 'unit' => $c->stock_unit,
                'change' => (int) $c->qty_change, 'before' => null, 'after' => null, 'reason' => 'damaged', 'reason_label' => 'Thrown away in the warehouse',
                'note' => trim(($c->batch_number ? "Batch {$c->batch_number}" : '') . ($c->notes ? " · {$c->notes}" : ''), ' ·'), 'by' => null, 'at' => $c->created_at]);

        return $adjustments->concat($culls)->sortByDesc('at')->take($limit)->values()->all();
    }

    /** A material the shop uses but doesn't sell: a product customers never see */
    public function createMaterial(int $ownerId, array $data, $actor): Product
    {
        return DB::transaction(function () use ($ownerId, $data, $actor) {
            $product = new Product([
                'product_name'      => $data['name'],
                'description'       => $data['notes'] ?? '',
                'sku'               => $data['sku'] ?? ('MAT-' . strtoupper(Str::random(6))),
                'category'          => ucfirst($data['stock_kind']),
                'flower_type'       => '',
                'color'             => '',
                'purchase_price'    => $data['unit_cost'] ?? 0,
                'selling_price'     => 0,
                'quantity_in_stock' => 0,
                'min_stock_level'   => $data['min_stock_level'] ?? 0,
                'max_stock_level'   => $data['max_stock_level'] ?? null,
                'supplier_lead_time' => $data['supplier_lead_time'] ?? null,
                'stock_kind'        => $data['stock_kind'],
                'stock_unit'        => $data['stock_unit'] ?? null,
                'status'            => 'active',
            ]);
            $product->owner_id = $ownerId;
            $product->moderation_status = Product::MODERATION_INTERNAL;
            $product->save();

            if (! empty($data['on_hand'])) {
                $this->adjust($product, 'set', (int) $data['on_hand'], 'count', 'Starting stock', $actor);
            }

            return $product->fresh();
        });
    }
}
