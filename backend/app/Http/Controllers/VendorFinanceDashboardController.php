<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payroll;
use App\Models\VendorBalance;
use App\Models\VendorTransaction;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VendorFinanceDashboardController extends Controller
{
    use ResolvesOwner;

    /**
     * GET /api/vendor/finance/overview
     *
     * Returns KPI cards: balance, total revenue, net profit, inventory value.
     * Maps directly to the four kpi-cards in the Vue dashboard.
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $vendorId = $this->resolveVendorId($request);
            $period = $request->input('period', 'this_month');
            [$periodFrom, $periodTo] = $this->periodBounds($period);

            $balance = VendorBalance::forVendor($vendorId);

            $thisMonthRevenue = VendorTransaction::forVendor($vendorId)
                ->credits()
                ->where('category', 'order_revenue')
                ->whereBetween('created_at', [$periodFrom, $periodTo])
                ->sum('amount');

            [$comparisonFrom, $comparisonTo] = $this->comparisonBounds($periodFrom, $periodTo, $period);

            $comparisonRevenue = VendorTransaction::forVendor($vendorId)
                ->credits()
                ->where('category', 'order_revenue')
                ->whereBetween('created_at', [$comparisonFrom, $comparisonTo])
                ->sum('amount');

            $revenueChange = $comparisonRevenue > 0
                ? round((($thisMonthRevenue - $comparisonRevenue) / $comparisonRevenue) * 100, 1)
                : ($thisMonthRevenue > 0 ? 100 : 0);

            $completedThisPeriod = $this->countCompletedOrdersForPeriod(
                $vendorId,
                $periodFrom,
                $periodTo,
            );

            $completedComparisonPeriod = $this->countCompletedOrdersForPeriod(
                $vendorId,
                $comparisonFrom,
                $comparisonTo,
            );

            $ordersChange = $completedComparisonPeriod > 0
                ? round((($completedThisPeriod - $completedComparisonPeriod) / $completedComparisonPeriod) * 100, 1)
                : ($completedThisPeriod > 0 ? 100 : 0);

            $expensesByCategory = VendorTransaction::forVendor($vendorId)
                ->debits()
                ->whereIn('category', ['refund', 'procurement'])
                ->whereBetween('created_at', [$periodFrom, $periodTo])
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->pluck('total', 'category');

            $refundsThisMonth = (float) ($expensesByCategory['refund'] ?? 0);
            $procurementThisMonth = (float) ($expensesByCategory['procurement'] ?? 0);
            $payrollThisMonth = $this->sumPayrollExpensesForPeriod($vendorId, $periodFrom, $periodTo);
            $totalExpenses = $refundsThisMonth + $procurementThisMonth + $payrollThisMonth;
            $netProfit = $thisMonthRevenue - $totalExpenses;

            return response()->json([
                'success' => true,
                'data' => [
                    'cash_balance' => [
                        'amount' => number_format((float) $balance->balance, 2),
                        'raw' => (float) $balance->balance,
                        'change_pct' => null,
                        'label' => 'Current Balance',
                    ],
                    'total_revenue' => [
                        'amount' => number_format($thisMonthRevenue, 2),
                        'raw' => $thisMonthRevenue,
                        'change_pct' => $revenueChange,
                        'label' => 'Total Revenue',
                        'period' => $this->periodLabel($period),
                    ],
                    'net_profit' => [
                        'amount' => number_format($netProfit, 2),
                        'raw' => $netProfit,
                        'change_pct' => null,
                        'label' => 'Net Profit',
                        'period' => $this->periodLabel($period),
                    ],
                    'completed_orders' => [
                        'count' => $completedThisPeriod,
                        'change_pct' => $ordersChange,
                        'label' => 'Completed Orders',
                        'period' => $this->periodLabel($period),
                    ],
                    'lifetime' => [
                        'total_earned' => (float) $balance->total_earned,
                        'total_withdrawn' => (float) $balance->total_withdrawn,
                    ],
                    'expenses' => [
                        'refund' => $refundsThisMonth,
                        'procurement' => $procurementThisMonth,
                        'payroll' => $payrollThisMonth,
                        'total' => $totalExpenses,
                    ],
                    'selected_period' => $period,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('VendorFinanceDashboard::overview error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load financial overview',
            ], 500);
        }
    }

    /**
     * GET /api/vendor/finance/transactions
     *
     * Paginated transaction list for the "Recent Transactions" table.
     * Supports ?type=all|credit|debit&per_page=15
     */
    public function transactions(Request $request): JsonResponse
    {
        try {
            $vendorId = $this->resolveVendorId($request);

            $request->validate([
                'type' => 'nullable|in:all,credit,debit',
                'per_page' => 'nullable|integer|min:5|max:100',
                'page' => 'nullable|integer|min:1',
            ]);

            $type = $request->input('type', 'all');
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);

            $formatted = $this->combinedTransactions($vendorId, $type);
            $transactions = new LengthAwarePaginator(
                $formatted->forPage($page, $perPage)->values(),
                $formatted->count(),
                $perPage,
                $page
            );

            return response()->json([
                'success' => true,
                'data' => $transactions->items(),
                'meta' => [
                    'total' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('VendorFinanceDashboard::transactions error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load transactions',
            ], 500);
        }
    }

    /**
     * GET /api/vendor/finance/cashflow
     *
     * Daily income vs expense for the bar chart.
     * Supports ?period=this_month|last_month|this_quarter|this_year (default: this_month)
     */
    public function cashflow(Request $request): JsonResponse
    {
        try {
            $vendorId = $this->resolveVendorId($request);
            $period = $request->input('period', 'this_month');

            [$from, $to, $granularity] = $this->periodRange($period);
            $data = $this->buildCashflowData($vendorId, $from, $to, $granularity);

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'period' => $period,
                    'max_value' => $data->max(fn ($row) => max($row['incomeAmount'], $row['expenseAmount'])) ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('VendorFinanceDashboard::cashflow error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load cashflow data',
            ], 500);
        }
    }

    /**
     * Returns [from, to, MySQL DATE_FORMAT string] for a named period.
     */
    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                'day',
            ],
            'this_quarter' => [
                $now->copy()->startOfQuarter(),
                $now->copy()->endOfQuarter(),
                'week',
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                'month',
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'day',
            ],
        };
    }

    private function periodBounds(string $period): array
    {
        [$from, $to] = $this->periodRange($period);

        return [$from, $to];
    }

    private function comparisonBounds(Carbon $from, Carbon $to, string $period): array
    {
        return match ($period) {
            'last_month' => [
                $from->copy()->subMonth()->startOfMonth(),
                $to->copy()->subMonth()->endOfMonth(),
            ],
            'this_quarter' => [
                $from->copy()->subQuarter()->startOfQuarter(),
                $to->copy()->subQuarter()->endOfQuarter(),
            ],
            'this_year' => [
                $from->copy()->subYear()->startOfYear(),
                $to->copy()->subYear()->endOfYear(),
            ],
            default => [
                $from->copy()->subMonth()->startOfMonth(),
                $to->copy()->subMonth()->endOfMonth(),
            ],
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'last_month' => 'Last Month',
            'this_quarter' => 'This Quarter',
            'this_year' => 'This Year',
            default => 'This Month',
        };
    }

    private function resolveVendorId(Request $request): int
    {
        return $this->resolveOwnerId($request);
    }

    private function countCompletedOrdersForPeriod(int $vendorId, $from, $to): int
    {
        return Order::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('delivered_at', [$from, $to])
                    ->orWhere(function ($fallbackQuery) use ($from, $to) {
                        $fallbackQuery->whereNull('delivered_at')
                            ->whereBetween('updated_at', [$from, $to]);
                    });
            })
            ->count();
    }

    private function sumPayrollExpensesForPeriod(int $vendorId, Carbon $from, Carbon $to): float
    {
        return (float) $this->payrollExpenseQuery($vendorId)
            ->get()
            ->filter(function (Payroll $payroll) use ($from, $to) {
                $effectiveAt = $this->payrollEffectiveAt($payroll);

                return $effectiveAt !== null && $effectiveAt->between($from, $to);
            })
            ->sum('net_salary');
    }

    private function payrollExpenseQuery(int $vendorId)
    {
        return Payroll::with('employee')
            ->where('owner_id', $vendorId)
            ->whereIn('status', ['approved', 'paid']);
    }

    private function payrollEffectiveAt(Payroll $payroll): ?Carbon
    {
        if ($payroll->status === 'paid' && $payroll->paid_at) {
            return $payroll->paid_at->copy();
        }

        return $payroll->updated_at?->copy();
    }

    private function combinedTransactions(int $vendorId, string $type): Collection
    {
        $ledgerTransactions = VendorTransaction::forVendor($vendorId)
            ->with('order:id,order_number,status')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (VendorTransaction $transaction) {
                return [
                    'id' => 'ledger-' . $transaction->id,
                    'sort_at' => $transaction->created_at,
                    'date' => $transaction->created_at->format('d/m/Y'),
                    'serial_no' => $transaction->order?->order_number ?? 'ADJ-' . $transaction->id,
                    'organization' => 'Customer Order',
                    'type' => ucfirst(str_replace('_', ' ', $transaction->category)),
                    'type_label' => $transaction->type === 'credit' ? 'Revenue' : ucfirst(str_replace('_', ' ', $transaction->category)),
                    'amount' => number_format((float) $transaction->amount, 2),
                    'raw_amount' => (float) $transaction->amount,
                    'status' => ucfirst($transaction->status),
                    'category' => $transaction->type === 'credit' ? 'income' : 'expense',
                    'balance_after' => number_format((float) $transaction->balance_after, 2),
                    'description' => $transaction->description,
                    'metadata' => $transaction->metadata,
                ];
            });

        $payrollTransactions = $this->payrollExpenseQuery($vendorId)
            ->get()
            ->map(function (Payroll $payroll) {
                $effectiveAt = $this->payrollEffectiveAt($payroll) ?? $payroll->updated_at ?? now();
                $employeeName = $payroll->employee?->full_name
                    ?? $payroll->employee?->name
                    ?? 'Employee';

                return [
                    'id' => 'payroll-' . $payroll->id,
                    'sort_at' => $effectiveAt,
                    'date' => $effectiveAt->format('d/m/Y'),
                    'serial_no' => 'PAY-' . $payroll->id,
                    'organization' => 'Payroll',
                    'type' => 'Payroll',
                    'type_label' => 'Payroll',
                    'amount' => number_format((float) $payroll->net_salary, 2),
                    'raw_amount' => (float) $payroll->net_salary,
                    'status' => ucfirst($payroll->status),
                    'category' => 'expense',
                    'balance_after' => null,
                    'description' => "Payroll for {$employeeName}",
                    'metadata' => [
                        'source' => 'payroll',
                        'period_start' => optional($payroll->period_start)->toDateString(),
                        'period_end' => optional($payroll->period_end)->toDateString(),
                    ],
                ];
            });

        return $ledgerTransactions
            ->merge($payrollTransactions)
            ->filter(fn (array $transaction) => $type === 'all'
                ? true
                : ($type === 'credit'
                    ? $transaction['category'] === 'income'
                    : $transaction['category'] === 'expense'))
            ->sortByDesc('sort_at')
            ->values()
            ->map(function (array $transaction) {
                unset($transaction['sort_at']);

                return $transaction;
            });
    }

    private function buildCashflowData(int $vendorId, Carbon $from, Carbon $to, string $granularity): Collection
    {
        $buckets = collect($this->makeBuckets($from, $to, $granularity))
            ->mapWithKeys(fn (array $bucket) => [
                $bucket['key'] => [
                    'month' => $bucket['label'],
                    'income' => 0,
                    'expense' => 0,
                    'incomeAmount' => 0,
                    'expenseAmount' => 0,
                    'change' => 0,
                ],
            ]);

        $incomeRows = VendorTransaction::forVendor($vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'type', 'amount']);

        foreach ($incomeRows as $row) {
            $key = $this->cashflowBucketKey(Carbon::parse($row->created_at), $granularity, $from);

            if (!$buckets->has($key)) {
                continue;
            }

            $entry = $buckets[$key];
            if ($row->type === 'credit') {
                $entry['incomeAmount'] += (float) $row->amount;
            } else {
                $entry['expenseAmount'] += (float) $row->amount;
            }
            $buckets[$key] = $entry;
        }

        $payrollRows = $this->payrollExpenseQuery($vendorId)->get();
        foreach ($payrollRows as $payroll) {
            $effectiveAt = $this->payrollEffectiveAt($payroll);
            if (!$effectiveAt || !$effectiveAt->between($from, $to)) {
                continue;
            }

            $key = $this->cashflowBucketKey($effectiveAt, $granularity, $from);
            if (!$buckets->has($key)) {
                continue;
            }

            $entry = $buckets[$key];
            $entry['expenseAmount'] += (float) $payroll->net_salary;
            $buckets[$key] = $entry;
        }

        $maxValue = $buckets->max(fn (array $row) => max($row['incomeAmount'], $row['expenseAmount'])) ?: 0;

        return $buckets->values()->map(function (array $row) use ($maxValue) {
            $row['income'] = $maxValue > 0 ? round(($row['incomeAmount'] / $maxValue) * 100, 2) : 0;
            $row['expense'] = $maxValue > 0 ? round(($row['expenseAmount'] / $maxValue) * 100, 2) : 0;

            return $row;
        });
    }

    private function makeBuckets(Carbon $from, Carbon $to, string $granularity): array
    {
        if ($granularity === 'month') {
            $cursor = $from->copy()->startOfMonth();
            $buckets = [];
            while ($cursor->lte($to)) {
                $buckets[] = [
                    'key' => $cursor->format('Y-m'),
                    'label' => $cursor->format('M'),
                ];
                $cursor->addMonth();
            }

            return $buckets;
        }

        if ($granularity === 'week') {
            $period = CarbonPeriod::create($from->copy()->startOfWeek(), '1 week', $to->copy()->endOfWeek());
            $index = 1;
            $buckets = [];
            foreach ($period as $weekStart) {
                if ($weekStart->gt($to)) {
                    break;
                }

                $buckets[] = [
                    'key' => $weekStart->format('o-\WW'),
                    'label' => 'Week ' . $index,
                ];
                $index++;
            }

            return $buckets;
        }

        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay());

        return collect($period)->map(fn (Carbon $date) => [
            'key' => $date->format('Y-m-d'),
            'label' => $date->format('d'),
        ])->all();
    }

    private function cashflowBucketKey(Carbon $date, string $granularity, Carbon $rangeStart): string
    {
        return match ($granularity) {
            'month' => $date->copy()->startOfMonth()->format('Y-m'),
            'week' => $date->copy()->startOfWeek()->format('o-\WW'),
            default => $date->copy()->startOfDay()->format('Y-m-d'),
        };
    }
}
