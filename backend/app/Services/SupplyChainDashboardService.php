<?php

namespace App\Services;

use App\Helpers\CloudinaryHelper;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything the Supply Chain dashboard shows, in one call: purchase orders,
 * supplier deliveries on the way, stock that is low or gone, how each
 * supplier has done, and what had to be thrown away.
 */
class SupplyChainDashboardService
{
    /** Purchase orders that have not arrived yet */
    private const OPEN = ['pending', 'processing', 'shipped', 'partially_received'];

    /** Used when a product has no minimum stock level set */
    private const DEFAULT_MIN_STOCK = 10;

    public function build(int $ownerId, int $days = 30): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        return [
            'period_days' => $days,
            'orders'      => $this->orders($ownerId, $since, $days),
            'incoming'    => $this->incoming($ownerId),
            'stock'       => $this->stock($ownerId),
            'suppliers'   => $this->suppliers($ownerId),
            'wastage'     => $this->wastage($ownerId, $since, $days),
            'floor'       => $this->floor($ownerId),
        ];
    }

    private function orders(int $ownerId, CarbonInterface $since, int $days): array
    {
        $rows = DB::table('purchase_orders')
            ->where('owner_id', $ownerId)
            ->select('status', DB::raw('COUNT(*) as c'), DB::raw('COALESCE(SUM(total_amount), 0) as v'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $s) => (int) ($rows[$s]->c ?? 0);
        $value = fn (array $s) => round((float) collect($s)->sum(fn ($x) => (float) ($rows[$x]->v ?? 0)), 2);

        $inPeriod = fn ($from, $to) => DB::table('purchase_orders')
            ->where('owner_id', $ownerId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();

        $perDay = DB::table('purchase_orders')
            ->where('owner_id', $ownerId)
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        return [
            'total'      => (int) $rows->sum('c'),
            'total_value' => round((float) $rows->sum('v'), 2),
            'by_status'  => collect(['draft', 'pending_approval', 'pending', 'processing', 'shipped', 'partially_received', 'received', 'completed'])
                ->mapWithKeys(fn ($s) => [$s => $count($s)]),
            'open'       => collect(self::OPEN)->sum($count),
            'open_value' => $value(self::OPEN),
            'on_the_way' => $count('shipped'),
            'this_period'     => $inPeriod($since, now()->addSecond()),
            'previous_period' => $inPeriod($since->copy()->subDays($days), $since),
            'per_day'    => $this->series($since, $days, $perDay),
        ];
    }

    /** Orders the supplier is preparing or has sent, the ones already on the road first */
    private function incoming(int $ownerId): array
    {
        $orders = DB::table('purchase_orders as po')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('shipments as sh', 'sh.purchase_order_id', '=', 'po.id')
            ->where('po.owner_id', $ownerId)
            ->whereIn('po.status', ['processing', 'shipped', 'partially_received'])
            ->orderByRaw("CASE WHEN po.status = 'shipped' THEN 0 ELSE 1 END")
            ->orderBy('po.created_at')
            ->limit(8)
            ->get([
                'po.id', 'po.order_number', 'po.status', 'po.total_amount', 'po.created_at',
                's.id as supplier_id', 's.company_name', 's.logo',
                'sh.status as shipment_status', 'sh.carrier', 'sh.tracking_number', 'sh.shipped_date',
            ]);

        $items = DB::table('purchase_order_items')
            ->whereIn('purchase_order_id', $orders->pluck('id'))
            ->select('purchase_order_id', DB::raw('COUNT(*) as lines'), DB::raw('COALESCE(SUM(quantity), 0) as qty'))
            ->groupBy('purchase_order_id')
            ->get()
            ->keyBy('purchase_order_id');

        return $orders->map(fn ($o) => [
            'id'              => $o->id,
            'order_number'    => $o->order_number ?: 'PO-' . str_pad((string) $o->id, 5, '0', STR_PAD_LEFT),
            'status'          => $o->status,
            'total_amount'    => (float) $o->total_amount,
            'ordered_at'      => $o->created_at,
            'supplier'        => $o->company_name,
            'supplier_logo'   => $o->logo ? CloudinaryHelper::getUrl($o->logo) : null,
            'shipment_status' => $o->shipment_status,
            'carrier'         => $o->carrier,
            'tracking_number' => $o->tracking_number,
            'shipped_date'    => $o->shipped_date,
            'lines'           => (int) ($items[$o->id]->lines ?? 0),
            'quantity'        => (int) ($items[$o->id]->qty ?? 0),
        ])->values()->all();
    }

    /** Products at or below their minimum, and products with nothing left */
    private function stock(int $ownerId): array
    {
        $products = DB::table('products')
            ->where('owner_id', $ownerId)
            ->whereNull('deleted_at')
            ->get(['id', 'product_name', 'sku', 'category', 'quantity_in_stock', 'min_stock_level']);

        $short = $products
            ->map(function ($p) {
                $min = (int) $p->min_stock_level > 0 ? (int) $p->min_stock_level : self::DEFAULT_MIN_STOCK;
                $qty = (int) $p->quantity_in_stock;

                return [
                    'id'       => $p->id,
                    'name'     => $p->product_name ?: 'Unnamed product',
                    'sku'      => $p->sku,
                    'category' => $p->category,
                    'quantity' => $qty,
                    'minimum'  => $min,
                    'state'    => $qty <= 0 ? 'out' : ($qty <= $min ? 'low' : 'ok'),
                ];
            })
            ->filter(fn ($p) => $p['state'] !== 'ok')
            // Gone first, then the ones furthest below their minimum
            ->sortBy(fn ($p) => [$p['state'] === 'out' ? 0 : 1, $p['quantity'] / max(1, $p['minimum'])])
            ->values();

        return [
            'products'     => $products->count(),
            'out_of_stock' => $short->where('state', 'out')->count(),
            'low_stock'    => $short->where('state', 'low')->count(),
            'healthy'      => $products->count() - $short->count(),
            'items'        => $short->take(8)->values()->all(),
        ];
    }

    /** Each supplier's track record: how many orders, how many arrived, how long they took */
    private function suppliers(int $ownerId): array
    {
        $orders = DB::table('purchase_orders')
            ->where('owner_id', $ownerId)
            ->whereNotNull('supplier_id')
            ->select(
                'supplier_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status IN ('received', 'completed') THEN 1 ELSE 0 END) as arrived"),
                DB::raw("SUM(CASE WHEN status IN ('pending', 'processing', 'shipped', 'partially_received') THEN 1 ELSE 0 END) as open"),
                DB::raw('COALESCE(SUM(total_amount), 0) as value'),
            )
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        // Days from ordering to arrival, for shipments marked received
        $lead = DB::table('shipments as sh')
            ->join('purchase_orders as po', 'po.id', '=', 'sh.purchase_order_id')
            ->where('po.owner_id', $ownerId)
            ->whereNotNull('sh.received_date')
            ->get(['po.supplier_id', 'po.created_at', 'sh.received_date', 'sh.status'])
            ->groupBy('supplier_id');

        return DB::table('suppliers')
            ->where('owner_id', $ownerId)
            ->get(['id', 'company_name', 'logo', 'status'])
            ->map(function ($s) use ($orders, $lead) {
                $o = $orders[$s->id] ?? null;
                $total = (int) ($o->total ?? 0);
                $arrived = (int) ($o->arrived ?? 0);
                $trips = $lead[$s->id] ?? collect();
                $days = $trips->map(fn ($t) => max(0, Carbon::parse($t->created_at)->startOfDay()->diffInDays(Carbon::parse($t->received_date)->startOfDay())));
                $failed = $trips->whereIn('status', ['failed', 'returned'])->count();

                return [
                    'id'             => $s->id,
                    'name'           => $s->company_name,
                    'logo'           => $s->logo ? CloudinaryHelper::getUrl($s->logo) : null,
                    'status'         => $s->status,
                    'orders'         => $total,
                    'arrived'        => $arrived,
                    'open'           => (int) ($o->open ?? 0),
                    'value'          => round((float) ($o->value ?? 0), 2),
                    'fill_rate'      => $total > 0 ? round($arrived / $total * 100, 1) : null,
                    'avg_lead_days'  => $days->isNotEmpty() ? round($days->avg(), 1) : null,
                    'problems'       => $failed,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->take(6)
            ->all();
    }

    /** Stems thrown away (culled) in the period, per day and by product, with their cost */
    private function wastage(int $ownerId, CarbonInterface $since, int $days): array
    {
        $culls = DB::table('warehouse_batch_logs as l')
            ->join('warehouse_batches as b', 'b.id', '=', 'l.warehouse_batch_id')
            ->leftJoin('products as p', 'p.id', '=', 'b.product_id')
            ->where('b.owner_id', $ownerId)
            ->where('l.event_type', 'CULLED')
            ->where('l.created_at', '>=', $since->copy()->subDays($days))
            ->get(['l.qty_change', 'l.created_at', 'b.product_id', 'p.product_name', 'p.purchase_price']);

        [$now, $before] = $culls->partition(fn ($c) => Carbon::parse($c->created_at)->gte($since));
        $units = fn ($rows) => (int) $rows->sum(fn ($c) => abs((int) $c->qty_change));
        $cost = fn ($rows) => round((float) $rows->sum(fn ($c) => abs((int) $c->qty_change) * (float) $c->purchase_price), 2);

        $perDay = $now
            ->groupBy(fn ($c) => Carbon::parse($c->created_at)->toDateString())
            ->map(fn ($rows) => $units($rows));

        $top = $now
            ->groupBy('product_id')
            ->map(fn ($rows) => [
                'name'  => $rows->first()->product_name ?: 'Unnamed product',
                'units' => $units($rows),
                'cost'  => $cost($rows),
            ])
            ->sortByDesc('units')
            ->take(4)
            ->values()
            ->all();

        return [
            'units'          => $units($now),
            'cost'           => $cost($now),
            'previous_units' => $units($before),
            'per_day'        => $this->series($since, $days, $perDay),
            'top'            => $top,
        ];
    }

    /** Stock on the floor that is past its date or about to be */
    private function floor(int $ownerId): array
    {
        $today = now()->startOfDay();

        $batches = DB::table('warehouse_batches as b')
            ->leftJoin('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('warehouse_locations as loc', 'loc.id', '=', 'b.warehouse_location_id')
            ->where('b.owner_id', $ownerId)
            ->where('b.status', 'active')
            ->where('b.qty_remaining', '>', 0)
            ->whereNotNull('b.expiration_date')
            ->where('b.expiration_date', '<=', $today->copy()->addDay()->toDateString())
            ->orderBy('b.expiration_date')
            ->get(['b.id', 'b.batch_number', 'b.qty_remaining', 'b.expiration_date', 'b.storage_location', 'p.product_name', 'loc.name as location']);

        $rows = $batches->map(function ($b) use ($today) {
            $left = $today->diffInDays(Carbon::parse($b->expiration_date)->startOfDay(), false);

            return [
                'id'       => $b->id,
                'batch'    => $b->batch_number,
                'product'  => $b->product_name ?: 'Unnamed product',
                'units'    => (int) $b->qty_remaining,
                'where'    => $b->location ?: $b->storage_location,
                'state'    => $left < 0 ? 'spoiled' : 'expiring',
                'days'     => (int) $left,
            ];
        });

        return [
            'spoiled_units'  => (int) $rows->where('state', 'spoiled')->sum('units'),
            'expiring_units' => (int) $rows->where('state', 'expiring')->sum('units'),
            'batches'        => $rows->take(8)->values()->all(),
        ];
    }

    /** One value per day, oldest first, zero where nothing happened */
    private function series(CarbonInterface $since, int $days, $byDate): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $since->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $d, 'value' => (int) ($byDate[$d] ?? 0)];
        }

        return $out;
    }
}
