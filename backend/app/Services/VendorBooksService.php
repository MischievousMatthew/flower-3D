<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\VendorExpense;
use App\Models\VendorTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * A vendor's books for a period, built from three sources:
 *  - the ledger (vendor_transactions): customer payments, refunds, stock purchases, adjustments;
 *  - payroll that was approved or paid;
 *  - operating expenses the vendor recorded (vendor_expenses).
 *
 * Profit & loss:  sales − refunds = net sales; − cost of goods sold = gross profit;
 *                 − operating expenses (payroll + recorded expenses) = net profit.
 * Stock purchases are money spent on inventory; that stock is counted as a cost
 * when it sells (cost of goods sold), so it isn't subtracted twice.
 */
class VendorBooksService
{
    public const BUCKETS = ['day', 'week', 'month', 'year'];

    private static ?bool $booksReady = null;

    /**
     * Whether the expenses table and item-cost column exist yet. Until the
     * migration has run, the books simply show no recorded expenses and
     * estimate item costs instead of failing.
     */
    public function booksReady(): bool
    {
        return self::$booksReady ??= Schema::hasTable('vendor_expenses') && Schema::hasColumn('order_items', 'unit_cost');
    }

    /** Every money movement in the period, newest first, each saying where it came from or went. */
    public function entries(int $vendorId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ledger = VendorTransaction::forVendor($vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->with([
                'order:id,order_number,user_id,delivery_contact_name,payment_method',
                'order.user:id,name,surname',
                'order.items:id,order_id,product_id,product_name,quantity' . ($this->booksReady() ? ',unit_cost' : ''),
                'order.items.product:id,purchase_price',
            ])
            ->get()
            ->map(fn (VendorTransaction $t) => $this->fromLedger($t));

        $payroll = Payroll::with('employee')
            ->where('owner_id', $vendorId)
            ->whereIn('status', ['approved', 'paid'])
            ->get()
            ->map(function (Payroll $p) {
                $at = ($p->status === 'paid' && $p->paid_at) ? $p->paid_at : $p->updated_at;
                $name = $p->employee?->full_name ?? $p->employee?->name ?? 'an employee';
                $period = $p->period_start && $p->period_end ? $p->period_start->format('M j') . '–' . $p->period_end->format('M j') : null;

                return [
                    'key' => 'pay-' . md5('pay' . $p->id),
                    'kind' => 'payroll',
                    'direction' => 'out',
                    'title' => 'Salary',
                    'party' => "To {$name}" . ($period ? " · {$period}" : ''),
                    'category' => 'payroll',
                    'category_label' => 'Payroll',
                    'amount' => round((float) $p->net_salary, 2),
                    'at' => $at,
                    'order_number' => null,
                    'source' => 'payroll',
                ];
            })
            ->filter(fn ($e) => $e['amount'] > 0 && $e['at'] && $e['at']->between($from, $to));

        $expenses = (! $this->booksReady() ? collect() : VendorExpense::where('owner_id', $vendorId)
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->get())
            ->map(fn (VendorExpense $x) => [
                'key' => 'exp-' . md5('exp' . $x->id),
                'kind' => 'expense',
                'direction' => 'out',
                'title' => $x->description,
                'party' => $x->payee ? "To {$x->payee}" : (VendorExpense::CATEGORIES[$x->category] ?? 'Expense'),
                'category' => $x->category,
                'category_label' => VendorExpense::CATEGORIES[$x->category] ?? ucfirst($x->category),
                'amount' => round((float) $x->amount, 2),
                // Recorded by date only: count it at noon so it lands on the right day
                'at' => Carbon::parse($x->spent_on->toDateString() . ' 12:00:00'),
                'order_number' => null,
                'source' => 'expense',
                'expense_id' => $x->id,
            ]);

        return $ledger->concat($payroll)->concat($expenses)
            ->sortByDesc(fn ($e) => $e['at']->getTimestamp())
            ->values();
    }

    /** Totals for the period, as a profit & loss statement. */
    public function totals(Collection $entries): array
    {
        $sum = fn (string $kind) => round((float) $entries->where('kind', $kind)->sum('amount'), 2);

        $sales = $sum('sale');
        $refunds = $sum('refund');
        $cogs = round((float) $entries->where('kind', 'sale')->sum('cost'), 2);
        $payroll = $sum('payroll');
        $byCategory = $entries->where('kind', 'expense')->groupBy('category')
            ->map(fn ($rows, $cat) => [
                'category' => $cat,
                'label' => VendorExpense::CATEGORIES[$cat] ?? ucfirst($cat),
                'amount' => round((float) $rows->sum('amount'), 2),
                'count' => $rows->count(),
            ])
            ->sortByDesc('amount')
            ->values();
        $recorded = round((float) $byCategory->sum('amount'), 2);
        $netSales = round($sales - $refunds, 2);
        $gross = round($netSales - $cogs, 2);
        $operating = round($payroll + $recorded, 2);
        $net = round($gross - $operating, 2);

        return [
            'sales' => $sales,
            'orders' => $entries->where('kind', 'sale')->count(),
            'refunds' => $refunds,
            'net_sales' => $netSales,
            'cogs' => $cogs,
            'cogs_estimated' => $entries->where('kind', 'sale')->contains(fn ($e) => $e['cost_estimated'] ?? false),
            'gross_profit' => $gross,
            'payroll' => $payroll,
            'expenses' => $recorded,
            'expenses_by_category' => $byCategory,
            'operating_expenses' => $operating,
            'net_profit' => $net,
            'gross_margin' => $netSales > 0 ? round($gross / $netSales * 100, 1) : null,
            'net_margin' => $netSales > 0 ? round($net / $netSales * 100, 1) : null,
            'stock_purchases' => $sum('stock'),
            'withdrawals' => $sum('withdrawal'),
            'adjustments_in' => round((float) $entries->where('kind', 'adjustment')->where('direction', 'in')->sum('amount'), 2),
            'adjustments_out' => round((float) $entries->where('kind', 'adjustment')->where('direction', 'out')->sum('amount'), 2),
        ];
    }

    /** The period split into days, weeks, months or years, each with its own totals. */
    public function buckets(Collection $entries, CarbonInterface $from, CarbonInterface $to, string $unit): array
    {
        $rows = [];
        $cursor = Carbon::instance($from)->copy()->startOf($unit === 'week' ? 'week' : $unit);
        while ($cursor->lte($to)) {
            $start = $cursor->copy();
            $end = $cursor->copy()->endOf($unit === 'week' ? 'week' : $unit);
            $in = $entries->filter(fn ($e) => $e['at']->between($start->copy()->max($from), $end->copy()->min($to)));
            $t = $this->totals($in);
            $rows[] = [
                'key' => $start->toDateString(),
                'label' => $this->bucketLabel($start->copy()->max($from), $end->copy()->min($to), $unit),
                'from' => $start->copy()->max($from)->toDateString(),
                'to' => $end->copy()->min($to)->toDateString(),
                'orders' => $t['orders'],
                'sales' => $t['sales'],
                'refunds' => $t['refunds'],
                'net_sales' => $t['net_sales'],
                'cogs' => $t['cogs'],
                'expenses' => $t['operating_expenses'],
                'net_profit' => $t['net_profit'],
            ];
            $cursor->add(1, $unit);
        }

        return $rows;
    }

    public function bucketLabel(CarbonInterface $start, CarbonInterface $end, string $unit): string
    {
        return match ($unit) {
            'day' => $start->format('D, M j'),
            'week' => $start->format('M j') . ' – ' . $end->format('M j'),
            'month' => $start->format('F Y'),
            default => $start->format('Y'),
        };
    }

    /** The same length of time just before [from, to], for comparisons. */
    public function previousPeriod(CarbonInterface $from, CarbonInterface $to): array
    {
        // [from 00:00, to 23:59:59] spans a whole number of days
        $days = max(1, (int) ceil($from->diffInDays($to)));
        $prevTo = Carbon::instance($from)->copy()->subDay()->endOfDay();

        return [$prevTo->copy()->subDays($days - 1)->startOfDay(), $prevTo];
    }

    private function fromLedger(VendorTransaction $t): array
    {
        $meta = (array) ($t->metadata ?? []);
        $order = $t->order;
        $customer = $order?->delivery_contact_name
            ?: trim(($order?->user?->name ?? '') . ' ' . ($order?->user?->surname ?? '')) ?: 'a customer';
        $amount = round((float) $t->amount, 2);
        $out = $t->type === 'debit';

        $base = [
            'key' => 'txn-' . md5('txn' . $t->id),
            'direction' => $out ? 'out' : 'in',
            'category' => $t->category,
            'amount' => $amount,
            'at' => $t->created_at,
            'order_number' => $order?->order_number ?? ($meta['order_number'] ?? null),
            'source' => 'ledger',
        ];

        return $base + match (true) {
            $t->category === 'order_revenue' => (function () use ($order, $customer) {
                // Cost of what was sold: the cost saved at checkout, or today's product cost for older orders
                $estimated = false;
                $cost = 0.0;
                foreach ($order?->items ?? [] as $item) {
                    $unit = $item->unit_cost;
                    if ($unit === null) {
                        $estimated = true;
                        $unit = $item->product?->getRawOriginal('purchase_price') ?? 0;
                    }
                    $cost += (float) $unit * (int) $item->quantity;
                }

                return [
                    'kind' => 'sale',
                    'title' => 'Customer payment',
                    'party' => "From {$customer}",
                    'category_label' => 'Sales',
                    'payment_method' => $order?->payment_method,
                    'cost' => round($cost, 2),
                    'cost_estimated' => $estimated,
                ];
            })(),
            $t->category === 'refund' => [
                'kind' => 'refund',
                'title' => ! empty($meta['partial']) ? 'Partial refund' : 'Refund',
                'party' => "To {$customer}",
                'category_label' => 'Refunds',
                'refund_method' => $meta['refund_method'] ?? null,
                'order_request_id' => $meta['order_request_id'] ?? null,
            ],
            $t->category === 'procurement' || ($t->category === 'adjustment' && isset($meta['funding_request_id'])) => [
                'kind' => 'stock',
                'title' => 'Stock purchase',
                'party' => 'For ' . ($meta['product_name'] ?? ($meta['supplier_name'] ?? 'inventory'))
                    . (isset($meta['approved_quantity']) ? " ({$meta['approved_quantity']})" : ''),
                'category_label' => 'Stock purchases',
            ],
            $t->category === 'withdrawal' => [
                'kind' => 'withdrawal',
                'title' => 'Withdrawal',
                'party' => 'To your payout account',
                'category_label' => 'Withdrawals',
            ],
            default => [
                'kind' => 'adjustment',
                'title' => 'Balance adjustment',
                'party' => $t->description ?: 'Manual adjustment',
                'category_label' => 'Adjustments',
            ],
        };
    }
}
