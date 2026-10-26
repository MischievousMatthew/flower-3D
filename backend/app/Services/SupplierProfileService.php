<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One supplier's profile: details and contacts, catalog and prices, delivery
 * schedule, the shop's ratings, and how its orders have actually gone.
 */
class SupplierProfileService
{
    public function profile(Supplier $supplier): array
    {
        $supplier->load(['contacts', 'products' => fn ($q) => $q->orderBy('category')->orderBy('name')]);

        $ratings = $supplier->ratings()->latest()->get();

        return [
            'supplier'    => $supplier,
            'catalog'     => $supplier->products,
            'ratings'     => [
                'count'         => $ratings->count(),
                'score'         => $ratings->isNotEmpty() ? round($ratings->avg('score'), 1) : null,
                'quality'       => $ratings->isNotEmpty() ? round($ratings->avg('quality'), 1) : null,
                'timeliness'    => $ratings->isNotEmpty() ? round($ratings->avg('timeliness'), 1) : null,
                'communication' => $ratings->isNotEmpty() ? round($ratings->avg('communication'), 1) : null,
                'recent'        => $ratings->take(20)->values(),
            ],
            'performance' => $this->performance($supplier),
        ];
    }

    /** Orders by month for the last 6 months, plus overall arrival and lead-time figures */
    private function performance(Supplier $supplier): array
    {
        $orders = DB::table('purchase_orders as po')
            ->leftJoin('shipments as sh', 'sh.purchase_order_id', '=', 'po.id')
            ->where('po.supplier_id', $supplier->id)
            ->where('po.owner_id', $supplier->owner_id)
            ->orderByDesc('po.created_at')
            ->get(['po.id', 'po.order_number', 'po.status', 'po.total_amount', 'po.created_at', 'sh.received_date', 'sh.status as shipment_status']);

        $promised = $supplier->lead_time_days;
        $trips = $orders->filter(fn ($o) => $o->received_date)->map(function ($o) use ($promised) {
            $days = max(0, Carbon::parse($o->created_at)->startOfDay()->diffInDays(Carbon::parse($o->received_date)->startOfDay()));

            return ['days' => $days, 'on_time' => $promised === null ? null : $days <= $promised];
        });
        $judged = $trips->whereNotNull('on_time');
        $total = $orders->count();
        $arrived = $orders->whereIn('status', ['received', 'completed'])->count();

        $months = collect(range(5, 0))->map(function ($ago) use ($orders) {
            $start = now()->startOfMonth()->subMonths($ago);
            $inMonth = $orders->filter(fn ($o) => Carbon::parse($o->created_at)->isSameMonth($start));

            return [
                'month'   => $start->format('Y-m'),
                'orders'  => $inMonth->count(),
                'value'   => round((float) $inMonth->sum('total_amount'), 2),
                'arrived' => $inMonth->whereIn('status', ['received', 'completed'])->count(),
            ];
        })->values();

        return [
            'orders'         => $total,
            'arrived'        => $arrived,
            'open'           => $orders->whereIn('status', ['pending', 'processing', 'shipped', 'partially_received'])->count(),
            'value'          => round((float) $orders->sum('total_amount'), 2),
            'fill_rate'      => $total ? round($arrived / $total * 100, 1) : null,
            'avg_lead_days'  => $trips->isNotEmpty() ? round($trips->avg('days'), 1) : null,
            'on_time_rate'   => $judged->isNotEmpty() ? round($judged->where('on_time', true)->count() / $judged->count() * 100, 1) : null,
            'problems'       => $orders->whereIn('shipment_status', ['failed', 'returned'])->count(),
            'last_order_at'  => $orders->first()?->created_at,
            'months'         => $months,
            'recent_orders'  => $orders->take(8)->map(fn ($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number ?: 'PO-' . str_pad((string) $o->id, 5, '0', STR_PAD_LEFT),
                'status'       => $o->status,
                'total_amount' => (float) $o->total_amount,
                'ordered_at'   => $o->created_at,
                'received_at'  => $o->received_date,
            ])->values(),
        ];
    }

    public function rate(Supplier $supplier, array $data, $actor): SupplierRating
    {
        return $supplier->ratings()->create($data + [
            'owner_id'      => $supplier->owner_id,
            'rated_by_type' => $actor ? class_basename($actor) : null,
            'rated_by_id'   => $actor?->id,
            'rated_by_name' => $actor ? trim(($actor->name ?? '') . ' ' . ($actor->surname ?? '')) ?: null : null,
        ]);
    }
}
