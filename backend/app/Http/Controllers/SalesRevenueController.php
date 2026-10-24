<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\VendorTransaction;
use App\Services\VendorSalesService;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Finance → Sales & revenue: what the shop sold, split into gross sales,
 * discounts and refunds, by date, product and order, with cash-on-delivery
 * and e-wallet sales apart and how placed orders turned out.
 *
 * Served at /vendor/books/sales* for the shop owner and /finance/sales* for
 * ERP Finance staff with the sales_revenue permission.
 */
class SalesRevenueController extends Controller
{
    use ResolvesOwner;

    public function __construct(private VendorSalesService $sales) {}

    /** GET …/sales */
    public function summary(Request $request): JsonResponse
    {
        $request->validate($this->rangeRules());
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);
        // The same length of time just before, for comparisons
        $days = max(1, (int) ceil($from->diffInDays($to)));
        $pTo = $from->copy()->subDay()->endOfDay();
        $pFrom = $pTo->copy()->subDays($days - 1)->startOfDay();

        $sales = $this->sales->paidSales($vendorId, $from, $to);
        $refunds = $this->sales->refunds($vendorId, $from, $to);

        $uncollected = Order::query()
            ->where('vendor_id', $vendorId)
            ->where('payment_method', 'cod')
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'failed', 'returned', 'refunded'])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as amount')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'previous_range' => $this->rangeInfo($pFrom, $pTo),
                'current' => $this->sales->totals($sales, $refunds),
                'previous' => $this->sales->totals($this->sales->paidSales($vendorId, $pFrom, $pTo), $this->sales->refunds($vendorId, $pFrom, $pTo)),
                'outcomes' => $this->sales->outcomes($vendorId, $from, $to),
                'previous_outcomes' => $this->sales->outcomes($vendorId, $pFrom, $pTo),
                'cod_uncollected' => ['amount' => round((float) $uncollected->amount, 2), 'orders' => (int) $uncollected->orders],
                'ewallet_methods' => $this->sales->ewalletMethods($sales),
                'by_date' => $this->sales->byDate($sales, $refunds, $from, $to),
                'by_product' => $this->sales->byProduct($sales),
            ],
        ]);
    }

    /** GET …/sales/orders: orders placed in the period */
    public function orders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(VendorSalesService::STATUS_GROUPS)],
            'payment' => 'nullable|in:cod,ewallet',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ] + $this->rangeRules());
        [$from, $to] = $this->range($request);
        $vendorId = $this->resolveOwnerId($request);

        $statuses = [
            'completed' => ['completed', 'complete', 'delivered'],
            'cancelled' => ['cancelled', 'failed'],
            'returned' => ['returned', 'refunded'],
        ];
        $query = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->when($data['status'] ?? null, function ($q, $group) use ($statuses) {
                $group === 'in_progress'
                    ? $q->whereNotIn('status', array_merge(...array_values($statuses)))
                    : $q->whereIn('status', $statuses[$group]);
            })
            ->when($data['payment'] ?? null, fn ($q, $p) => $p === 'cod'
                ? $q->where('payment_method', 'cod')
                : $q->where('payment_method', '!=', 'cod'))
            ->when(trim($data['search'] ?? ''), function ($q, $term) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
                $q->where(fn ($w) => $w->where('order_number', 'like', $like)
                    ->orWhere('delivery_contact_name', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('surname', 'like', $like)));
            });

        $page = $query->with([
            'user:id,name,surname',
            'items' => fn ($q) => $q->select(array_merge(
                ['id', 'order_id', 'product_name', 'quantity', 'unit_price'],
                $this->sales->listPriceReady() ? ['list_price'] : [],
            )),
        ])
            ->orderByDesc('created_at')
            ->paginate(15, ['id', 'order_number', 'user_id', 'delivery_contact_name', 'status', 'payment_status', 'payment_method', 'subtotal', 'delivery_fee', 'discount_amount', 'total_amount', 'paid_at', 'cancelled_at', 'delivered_at', 'created_at'], 'page', $data['page'] ?? 1);

        $refunded = VendorTransaction::forVendor($vendorId)
            ->where('category', 'refund')
            ->whereIn('order_id', $page->getCollection()->pluck('id'))
            ->selectRaw('order_id, SUM(amount) as total')
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        return response()->json([
            'success' => true,
            'data' => [
                'range' => $this->rangeInfo($from, $to),
                'orders' => $page->getCollection()->map(function (Order $o) use ($refunded) {
                    $items = $o->items;
                    $discount = $items->sum(fn ($i) => ($i->list_price ?? null) === null ? 0 : max(0, (float) $i->list_price - (float) $i->unit_price) * (int) $i->quantity)
                        + (float) $o->discount_amount;
                    $names = $items->pluck('product_name')->filter()->values();

                    return [
                        'id' => $o->id,
                        'order_number' => $o->order_number,
                        'customer' => $o->delivery_contact_name ?: (trim(($o->user?->name ?? '') . ' ' . ($o->user?->surname ?? '')) ?: 'Customer'),
                        'items' => $names->take(2)->implode(', ') . ($names->count() > 2 ? ' +' . ($names->count() - 2) . ' more' : ''),
                        'item_count' => (int) $items->sum('quantity'),
                        'status' => $o->status,
                        'status_group' => VendorSalesService::statusGroup($o->status),
                        'payment_method' => $o->payment_method,
                        'payment_status' => $o->payment_status,
                        'discount' => round($discount, 2),
                        'delivery_fee' => round((float) $o->delivery_fee, 2),
                        'total' => round((float) $o->total_amount, 2),
                        'refunded' => round((float) ($refunded[$o->id] ?? 0), 2),
                        'placed_at' => $o->created_at?->toIso8601String(),
                        'paid_at' => $o->paid_at?->toIso8601String(),
                    ];
                })->values(),
                'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ],
        ]);
    }

    private function rangeRules(): array
    {
        return ['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from'];
    }

    /** [from, to] from the request; this month to date by default. */
    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    private function rangeInfo(Carbon $from, Carbon $to): array
    {
        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('M j, Y') . ' – ' . $to->format('M j, Y')];
    }
}
