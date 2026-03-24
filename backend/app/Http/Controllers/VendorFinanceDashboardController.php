<?php

namespace App\Http\Controllers;

use App\Models\VendorBalance;
use App\Models\VendorTransaction;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VendorFinanceDashboardController extends Controller
{
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

            $balance = VendorBalance::forVendor($vendorId);

            // Revenue this month vs last month
            $thisMonthRevenue = VendorTransaction::forVendor($vendorId)
                ->credits()
                ->where('category', 'order_revenue')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            $lastMonthRevenue = VendorTransaction::forVendor($vendorId)
                ->credits()
                ->where('category', 'order_revenue')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount');

            $revenueChange = $lastMonthRevenue > 0
                ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                : ($thisMonthRevenue > 0 ? 100 : 0);

            // Completed orders this month
            $completedThisMonth = Order::where('vendor_id', $vendorId)
                ->where('status', 'completed')
                ->whereMonth('delivered_at', now()->month)
                ->whereYear('delivered_at', now()->year)
                ->count();

            $completedLastMonth = Order::where('vendor_id', $vendorId)
                ->where('status', 'completed')
                ->whereMonth('delivered_at', now()->subMonth()->month)
                ->whereYear('delivered_at', now()->subMonth()->year)
                ->count();

            $ordersChange = $completedLastMonth > 0
                ? round((($completedThisMonth - $completedLastMonth) / $completedLastMonth) * 100, 1)
                : ($completedThisMonth > 0 ? 100 : 0);

            // Total refunds this month
            $expensesByCategory = VendorTransaction::forVendor($vendorId)
                ->debits()
                ->whereIn('category', ['refund', 'procurement', 'payroll'])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->pluck('total', 'category');

            $refundsThisMonth      = (float) ($expensesByCategory['refund']      ?? 0);
            $procurementThisMonth  = (float) ($expensesByCategory['procurement']  ?? 0);
            $payrollThisMonth      = (float) ($expensesByCategory['payroll']      ?? 0);
            $totalExpenses = $refundsThisMonth + $procurementThisMonth + $payrollThisMonth;

            $netProfit = $thisMonthRevenue - $totalExpenses;

            return response()->json([
                'success' => true,
                'data'    => [
                    'cash_balance'   => [
                        'amount'     => number_format((float) $balance->balance, 2),
                        'raw'        => (float) $balance->balance,
                        'change_pct' => null,     // balance doesn't compare month-to-month
                        'label'      => 'Current Balance',
                    ],
                    'total_revenue'  => [
                        'amount'     => number_format($thisMonthRevenue, 2),
                        'raw'        => $thisMonthRevenue,
                        'change_pct' => $revenueChange,
                        'label'      => 'Total Revenue',
                        'period'     => 'This Month',
                    ],
                    'net_profit'     => [
                        'amount'     => number_format($netProfit, 2),
                        'raw'        => $netProfit,
                        'change_pct' => null,
                        'label'      => 'Net Profit',
                        'period'     => 'This Month',
                    ],
                    'completed_orders' => [
                        'count'      => $completedThisMonth,
                        'change_pct' => $ordersChange,
                        'label'      => 'Completed Orders',
                        'period'     => 'This Month',
                    ],
                    'lifetime'       => [
                        'total_earned'    => (float) $balance->total_earned,
                        'total_withdrawn' => (float) $balance->total_withdrawn,
                    ],
                    'expenses' => [
                        'refund'      => $refundsThisMonth,
                        'procurement' => $procurementThisMonth,
                        'payroll'     => $payrollThisMonth,
                        'total'       => $totalExpenses,
                    ],
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
                'type'     => 'nullable|in:all,credit,debit',
                'per_page' => 'nullable|integer|min:5|max:100',
            ]);

            $query = VendorTransaction::forVendor($vendorId)
                ->with('order:id,order_number,status')
                ->orderByDesc('created_at');

            $type = $request->input('type', 'all');
            if ($type !== 'all') {
                $query->where('type', $type);
            }

            $transactions = $query->paginate($request->input('per_page', 15));

            $formatted = $transactions->map(function (VendorTransaction $t) {
                return [
                    'id'             => $t->id,
                    'date'           => $t->created_at->format('d/m/Y'),
                    'serial_no'      => $t->order?->order_number ?? 'ADJ-' . $t->id,
                    'organization'   => 'Customer Order',     // extend if you store buyer name in metadata
                    'type'           => ucfirst(str_replace('_', ' ', $t->category)),
                    'amount'         => number_format((float) $t->amount, 2),
                    'raw_amount'     => (float) $t->amount,
                    'status'         => ucfirst($t->status),
                    'category'       => $t->type === 'credit' ? 'income' : 'expense',
                    'balance_after'  => number_format((float) $t->balance_after, 2),
                    'description'    => $t->description,
                    'metadata'       => $t->metadata,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $formatted,
                'meta'    => [
                    'total'        => $transactions->total(),
                    'per_page'     => $transactions->perPage(),
                    'current_page' => $transactions->currentPage(),
                    'last_page'    => $transactions->lastPage(),
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
            $period   = $request->input('period', 'this_month');

            [$from, $to, $groupFormat] = $this->periodRange($period);

            $rows = VendorTransaction::forVendor($vendorId)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw("
                    DATE_FORMAT(created_at, '{$groupFormat}') as period_label,
                    SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) as expense
                ")
                ->groupByRaw("DATE_FORMAT(created_at, '{$groupFormat}')")
                ->orderByRaw("MIN(created_at)")
                ->get();

            $maxIncome  = $rows->max('income')  ?: 1;
            $maxExpense = $rows->max('expense') ?: 1;
            $maxVal     = max($maxIncome, $maxExpense);

            $data = $rows->map(function ($row) use ($maxVal) {
                $income  = (float) $row->income;
                $expense = (float) $row->expense;

                return [
                    'month'         => $row->period_label,
                    'income'        => $maxVal > 0 ? round(($income  / $maxVal) * 100) : 0,
                    'expense'       => $maxVal > 0 ? round(($expense / $maxVal) * 100) : 0,
                    'incomeAmount'  => $income,
                    'expenseAmount' => $expense,
                    'change'        => 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('VendorFinanceDashboard::cashflow error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load cashflow data',
            ], 500);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Returns [from, to, MySQL DATE_FORMAT string] for a named period.
     */
    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'last_month'    => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                '%d',   // day of month
            ],
            'this_quarter'  => [
                $now->copy()->startOfQuarter(),
                $now->copy()->endOfQuarter(),
                '%u',   // ISO week number
            ],
            'this_year'     => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                '%b',   // abbreviated month name
            ],
            default         => [  // this_month
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                '%d',
            ],
        };
    }

    private function resolveVendorId(Request $request): int
    {
        $user = $request->user();

        // Employee model instance — get vendor via owner_id
        if ($user instanceof \App\Models\Employee) {
            return $user->owner_id;
        }

        // Regular User (vendor logging in directly)
        return $user->id;
    }
}