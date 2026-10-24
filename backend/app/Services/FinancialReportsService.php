<?php

namespace App\Services;

use App\Models\VendorExpense;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Finance → Reports: sales, expenses, profit and loss, payments and refunds,
 * and tax summaries for a period, each built once as a plain structure that
 * the page shows and the CSV and PDF downloads print:
 *
 *   ['title', 'subtitle', 'notes' => [...], 'summary' => [[label, value, type, hint?]],
 *    'tables' => [['title', 'columns' => [[key, label, type]], 'rows' => [...], 'totals' => [...]|null]]]
 *
 * Column and summary types: text, int, money, percent, date.
 */
class FinancialReportsService
{
    public const TYPES = ['sales', 'expenses', 'profit_loss', 'payments_refunds', 'tax'];
    public const UNITS = ['day', 'week', 'month', 'year'];
    public const TAX_MODES = ['percentage', 'vat', 'income8'];

    /** Rows each unit can cover before the report gets too long to read */
    private const MAX_DAYS = ['day' => 93, 'week' => 742, 'month' => 1860, 'year' => 18300];

    public function __construct(
        private VendorBooksService $books,
        private VendorSalesService $sales,
        private VendorPaymentsService $payments,
        private RefundManagementService $refunds,
    ) {}

    /** A message when the unit would make too many rows for the period, else null */
    public function tooLong(CarbonInterface $from, CarbonInterface $to, string $unit): ?string
    {
        return (int) ceil($from->diffInDays($to)) > self::MAX_DAYS[$unit]
            ? ['day' => 'Daily reports cover up to 3 months. Pick a shorter period or weekly rows.', 'week' => 'Weekly reports cover up to 2 years.', 'month' => 'Monthly reports cover up to 5 years.', 'year' => 'Pick a shorter period.'][$unit]
            : null;
    }

    public function build(string $type, int $vendorId, CarbonInterface $from, CarbonInterface $to, array $options = []): array
    {
        $unit = $options['unit'] ?? 'day';

        return match ($type) {
            'sales' => $this->salesReport($vendorId, $from, $to, $unit),
            'expenses' => $this->expensesReport($vendorId, $from, $to),
            'profit_loss' => $this->profitLossReport($vendorId, $from, $to, $unit, $options['basis'] ?? 'sold'),
            'payments_refunds' => $this->paymentsRefundsReport($vendorId, $from, $to),
            'tax' => $this->taxReport($vendorId, $from, $to, $options['tax_mode'] ?? 'percentage'),
        };
    }

    private function count(int $n, string $word): string
    {
        return $n . ' ' . $word . ($n === 1 ? '' : 's');
    }

    private function unitLabel(string $unit): string
    {
        return ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'][$unit] ?? 'Daily';
    }

    private function salesReport(int $vendorId, CarbonInterface $from, CarbonInterface $to, string $unit): array
    {
        $sales = $this->sales->paidSales($vendorId, $from, $to);
        $refunds = $this->sales->refunds($vendorId, $from, $to);
        $t = $this->sales->totals($sales, $refunds);
        $rows = $this->sales->byDate($sales, $refunds, $from, $to, $unit)['rows'];

        return [
            'title' => $this->unitLabel($unit) . ' sales',
            'subtitle' => 'Paid orders, with discounts and refunds kept apart',
            'notes' => $t['discounts_partial'] ? ['Discounts are recorded for orders placed since they started being tracked; older orders count at the price paid.'] : [],
            'summary' => [
                ['Gross sales', $t['gross_sales'], 'money', $this->count($t['orders'], 'paid order')],
                ['Discounts', $t['discounts'], 'money'],
                ['Refunds', $t['refunds'], 'money', $this->count($t['refund_count'], 'refund')],
                ['Net sales', $t['net_sales'], 'money', 'average order ₱' . number_format($t['average_order'], 2)],
                ['Cash on delivery', $t['cod']['amount'], 'money', $this->count($t['cod']['orders'], 'order')],
                ['E-wallet', $t['ewallet']['amount'], 'money', $this->count($t['ewallet']['orders'], 'order')],
            ],
            'tables' => [
                [
                    'title' => 'Sales by ' . ($unit === 'day' ? 'day' : $unit),
                    'columns' => [['label', 'Period', 'text'], ['orders', 'Orders', 'int'], ['gross_sales', 'Gross sales', 'money'], ['discounts', 'Discounts', 'money'], ['refunds', 'Refunds', 'money'], ['net_sales', 'Net sales', 'money'], ['cod', 'Cash on delivery', 'money'], ['ewallet', 'E-wallet', 'money']],
                    'rows' => $rows,
                    'totals' => ['label' => 'Total', 'orders' => $t['orders'], 'gross_sales' => $t['gross_sales'], 'discounts' => $t['discounts'], 'refunds' => $t['refunds'], 'net_sales' => $t['net_sales'], 'cod' => $t['cod']['amount'], 'ewallet' => $t['ewallet']['amount']],
                ],
                [
                    'title' => 'Top products',
                    'columns' => [['name', 'Product', 'text'], ['quantity', 'Sold', 'int'], ['orders', 'Orders', 'int'], ['discounts', 'Discounts', 'money'], ['sales', 'Sales', 'money'], ['share', 'Share', 'percent']],
                    'rows' => $this->sales->byProduct($sales, 25),
                    'totals' => null,
                ],
            ],
        ];
    }

    private function expensesReport(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = ! $this->books->booksReady() ? collect() : VendorExpense::where('owner_id', $vendorId)
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->withCount('files')
            ->orderBy('spent_on')->orderBy('id')
            ->get();
        $total = round((float) $rows->sum('amount'), 2);
        $stock = round((float) $rows->filter->isStock()->sum('amount'), 2);
        $t = $this->books->totals($this->books->entries($vendorId, $from, $to));
        $missing = $rows->filter(fn ($x) => ! $x->receipt_path && ! $x->files_count)->count();

        return [
            'title' => 'Expense report',
            'subtitle' => 'Everything recorded in Expenses, by category',
            'notes' => $stock > 0 ? ['Flowers are counted as stock purchases: their cost reaches profit when they sell.'] : [],
            'summary' => [
                ['Total recorded', $total, 'money', $this->count($rows->count(), 'expense')],
                ['Running costs', round($total - $stock, 2), 'money', 'everything except stock'],
                ['Flowers bought', $stock, 'money', 'counted as stock'],
                ['Payroll', $t['payroll'], 'money', 'approved or paid salaries'],
                ['Payment fees', $t['payment_fees'], 'money', 'PayMongo processing fees'],
                ['Missing a receipt', $missing, 'int'],
            ],
            'tables' => [
                [
                    'title' => 'By category',
                    'columns' => [['label', 'Category', 'text'], ['count', 'Expenses', 'int'], ['amount', 'Amount', 'money'], ['share', 'Share', 'percent']],
                    'rows' => $rows->groupBy('category')
                        ->map(fn ($c, $cat) => ['label' => VendorExpense::CATEGORIES[$cat] ?? ucfirst($cat), 'count' => $c->count(), 'amount' => round((float) $c->sum('amount'), 2), 'share' => $total > 0 ? round($c->sum('amount') / $total * 100, 1) : 0])
                        ->sortByDesc('amount')->values()->all(),
                    'totals' => ['label' => 'Total', 'count' => $rows->count(), 'amount' => $total, 'share' => $total > 0 ? 100 : 0],
                ],
                [
                    'title' => 'Every expense',
                    'columns' => [['spent_on', 'Date', 'date'], ['category', 'Category', 'text'], ['description', 'What for', 'text'], ['payee', 'Paid to', 'text'], ['method', 'Paid with', 'text'], ['reference', 'Reference', 'text'], ['receipt', 'Receipt', 'text'], ['amount', 'Amount', 'money']],
                    'rows' => $rows->map(fn (VendorExpense $x) => [
                        'spent_on' => $x->spent_on->toDateString(),
                        'category' => VendorExpense::CATEGORIES[$x->category] ?? ucfirst($x->category),
                        'description' => $x->description,
                        'payee' => $x->payee,
                        'method' => VendorExpense::PAYMENT_METHODS[$x->payment_method] ?? null,
                        'reference' => $x->reference,
                        'receipt' => ($x->receipt_path || $x->files_count) ? 'Yes' : 'No',
                        'amount' => (float) $x->amount,
                    ])->values()->all(),
                    'totals' => ['spent_on' => 'Total', 'amount' => $total],
                ],
                [
                    'title' => 'Recorded by the books',
                    'columns' => [['label', 'Cost', 'text'], ['hint', 'From', 'text'], ['amount', 'Amount', 'money']],
                    'rows' => [
                        ['label' => 'Payroll', 'hint' => 'Approved or paid salaries', 'amount' => $t['payroll']],
                        ['label' => 'Payment fees', 'hint' => 'PayMongo processing fees', 'amount' => $t['payment_fees']],
                        ['label' => 'Stock purchases', 'hint' => 'Approved funding requests and flowers bought', 'amount' => $t['stock_purchases']],
                        ['label' => 'Refunds', 'hint' => 'Refunds customers confirmed', 'amount' => $t['refunds']],
                    ],
                    'totals' => null,
                ],
            ],
        ];
    }

    private function profitLossReport(int $vendorId, CarbonInterface $from, CarbonInterface $to, string $unit, string $basis): array
    {
        $entries = $this->books->entries($vendorId, $from, $to);
        $t = $this->books->totals($entries, $basis);
        $line = fn (string $label, float $amount, string $kind = 'line') => compact('label', 'amount', 'kind');
        $statement = array_merge(
            [$line('Sales', $t['sales']), $line('Less refunds', -$t['refunds']), $line('Net revenue', $t['net_sales'], 'sub'),
                $line($basis === 'bought' ? 'Less flowers and stock bought' : 'Less cost of flowers and items sold', -$t['cogs']), $line('Gross profit', $t['gross_profit'], 'sub'),
                $line('Less payroll', -$t['payroll']), $line('Less payment fees', -$t['payment_fees'])],
            collect($t['expenses_by_category'])->map(fn ($c) => $line('Less ' . mb_strtolower($c['label']), -$c['amount']))->all(),
            [$line('Total operating expenses', -$t['operating_expenses'], 'sub'), $line('Operating profit (estimated)', $t['operating_profit'], 'total')],
        );

        return [
            'title' => 'Profit and loss statement',
            'subtitle' => $basis === 'bought' ? 'Flower costs counted when bought' : 'Flower costs counted when sold',
            'notes' => array_values(array_filter([
                $t['uncosted_items'] ? "{$t['uncosted_items']} items sold had no cost price, so their flowers aren't in the cost of goods." : null,
                $t['cogs_estimated'] ? "Older orders didn't save their item cost; they use the products' current cost." : null,
            ])),
            'summary' => [
                ['Net revenue', $t['net_sales'], 'money', "after ₱" . number_format($t['refunds'], 2) . ' refunds'],
                ['Gross profit', $t['gross_profit'], 'money', $t['gross_margin'] === null ? null : "{$t['gross_margin']}% margin"],
                ['Operating expenses', $t['operating_expenses'], 'money'],
                ['Operating profit', $t['operating_profit'], 'money', $t['net_margin'] === null ? 'estimated' : "{$t['net_margin']}% · estimated"],
            ],
            'tables' => [
                [
                    'title' => 'Statement',
                    'columns' => [['label', 'Line', 'text'], ['amount', 'Amount', 'money']],
                    'rows' => $statement,
                    'totals' => null,
                ],
                [
                    'title' => $this->unitLabel($unit) . ' performance',
                    'columns' => [['label', 'Period', 'text'], ['net_sales', 'Net revenue', 'money'], ['cogs', 'Cost of goods', 'money'], ['gross_profit', 'Gross profit', 'money'], ['expenses', 'Operating expenses', 'money'], ['net_profit', 'Operating profit', 'money'], ['net_margin', 'Margin', 'percent']],
                    'rows' => $this->books->buckets($entries, $from, $to, $unit, $basis),
                    'totals' => ['label' => 'Total', 'net_sales' => $t['net_sales'], 'cogs' => $t['cogs'], 'gross_profit' => $t['gross_profit'], 'expenses' => $t['operating_expenses'], 'net_profit' => $t['net_profit'], 'net_margin' => $t['net_margin']],
                ],
            ],
        ];
    }

    private function paymentsRefundsReport(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->payments->rows($vendorId, $from, $to);
        $s = $this->payments->summary($rows);
        $refundRows = $this->refunds->rows($vendorId, $this->refunds->cases($vendorId, $from, $to), $from);
        $r = $this->refunds->summary($refundRows, $vendorId, $from, $to);
        $statusLabels = ['paid' => 'Successful', 'pending' => 'Pending', 'failed' => 'Failed', 'expired' => 'Expired', 'refunded' => 'Refunded', 'cancelled' => 'Voided'];
        $stageLabels = ['awaiting_delivery' => 'Awaiting delivery', 'to_collect' => 'To collect', 'collected' => 'Cash received', 'settled' => 'Settled', 'void' => 'Voided'];
        $refundLabels = ['requested' => 'Requested', 'approved' => 'Approved', 'processing' => 'Processing (sent)', 'completed' => 'Completed', 'closed' => 'Declined or withdrawn'];
        $providers = ['gcash' => 'GCash', 'paymaya' => 'Maya', 'maya' => 'Maya', 'card' => 'Card', 'grab_pay' => 'GrabPay', 'ewallet' => 'E-wallet'];

        return [
            'title' => 'Payments and refunds',
            'subtitle' => 'Payments on orders placed in the period, and refund cases',
            'notes' => $s['paymongo']['paid_count'] > $s['paymongo']['fees_known'] ? ["PayMongo's fee is on file for {$s['paymongo']['fees_known']} of {$s['paymongo']['paid_count']} paid online payments."] : [],
            'summary' => [
                ['Successful payments', $s['all']['paid']['amount'], 'money', $this->count($s['all']['paid']['count'], 'payment')],
                ['Pending', $s['all']['pending']['amount'], 'money', $this->count($s['all']['pending']['count'], 'payment')],
                ['Failed or expired', $s['all']['failed']['count'] + $s['all']['expired']['count'], 'int'],
                ['PayMongo fees', $s['paymongo']['fees'], 'money', 'net ₱' . number_format($s['paymongo']['net_received'], 2)],
                ['Refunded', $r['refunded'], 'money', $r['refund_rate'] === null ? null : "{$r['refund_rate']}% of sales"],
                ['Owed to customers', $r['owed'], 'money', 'agreed, not yet confirmed'],
            ],
            'tables' => [
                [
                    'title' => 'Payments by status',
                    'columns' => [['label', 'Status', 'text'], ['pm_count', 'PayMongo', 'int'], ['pm_amount', 'PayMongo amount', 'money'], ['cod_count', 'Cash on delivery', 'int'], ['cod_amount', 'COD amount', 'money']],
                    'rows' => collect($statusLabels)->map(fn ($label, $k) => [
                        'label' => $label,
                        'pm_count' => $s['paymongo']['statuses'][$k]['count'], 'pm_amount' => $s['paymongo']['statuses'][$k]['amount'],
                        'cod_count' => $s['cod']['statuses'][$k]['count'], 'cod_amount' => $s['cod']['statuses'][$k]['amount'],
                    ])->values()->all(),
                    'totals' => null,
                ],
                [
                    'title' => 'PayMongo by provider',
                    'columns' => [['label', 'Provider', 'text'], ['count', 'Payments', 'int'], ['amount', 'Amount', 'money'], ['fees', 'Fees', 'money']],
                    'rows' => collect($s['paymongo']['providers'])->map(fn ($p) => ['label' => $providers[$p['provider']] ?? $p['provider']] + $p)->all(),
                    'totals' => ['label' => 'Total', 'count' => $s['paymongo']['paid_count'], 'amount' => $s['paymongo']['statuses']['paid']['amount'], 'fees' => $s['paymongo']['fees']],
                ],
                [
                    'title' => 'Cash on delivery',
                    'columns' => [['label', 'Stage', 'text'], ['count', 'Orders', 'int'], ['amount', 'Amount', 'money']],
                    'rows' => collect($stageLabels)->map(fn ($label, $k) => ['label' => $label] + $s['cod']['stages'][$k])->values()->all(),
                    'totals' => null,
                ],
                [
                    'title' => 'Refunds by status',
                    'columns' => [['label', 'Status', 'text'], ['count', 'Cases', 'int'], ['amount', 'Amount', 'money']],
                    'rows' => collect($refundLabels)->map(fn ($label, $k) => ['label' => $label] + $r['statuses'][$k])->values()->all(),
                    'totals' => null,
                ],
                [
                    'title' => 'Completed refunds',
                    'columns' => [['completed_at', 'Confirmed', 'date'], ['order_number', 'Order', 'text'], ['customer', 'Customer', 'text'], ['reason', 'Reason', 'text'], ['method', 'Sent by', 'text'], ['reference', 'Reference', 'text'], ['amount', 'Amount', 'money']],
                    'rows' => $refundRows->where('status', 'completed')->map(fn ($x) => [
                        'completed_at' => $x['completed_at'] ? Carbon::parse($x['completed_at'])->toDateString() : null,
                        'order_number' => $x['order']['order_number'], 'customer' => $x['customer'], 'reason' => $x['reason_label'],
                        'method' => $x['refund_method_label'], 'reference' => $x['refund_reference'], 'amount' => $x['amount'],
                    ])->values()->all(),
                    'totals' => ['completed_at' => 'Total', 'amount' => round((float) $refundRows->where('status', 'completed')->sum('amount'), 2)],
                ],
            ],
        ];
    }

    private function taxReport(int $vendorId, CarbonInterface $from, CarbonInterface $to, string $mode): array
    {
        $sales = $this->sales->paidSales($vendorId, $from, $to);
        $refunds = $this->sales->refunds($vendorId, $from, $to);
        $t = $this->sales->totals($sales, $refunds);
        // Quarters for long periods, months otherwise
        $byQuarter = (int) ceil($from->diffInDays($to)) > 92;
        $taxFor = fn (float $base) => round(match ($mode) {
            'vat' => $base * 12 / 112,
            'income8' => $base * 0.08,
            default => $base * 0.03,
        }, 2);
        $modeLabel = ['percentage' => 'Percentage tax (3%, non-VAT)', 'vat' => 'Output VAT (12%, prices include VAT)', 'income8' => '8% income tax option'][$mode];

        $periods = [];
        $cursor = Carbon::instance($from)->copy()->startOf($byQuarter ? 'quarter' : 'month');
        while ($cursor->lte($to)) {
            $start = $cursor->copy()->max($from);
            $end = $cursor->copy()->endOf($byQuarter ? 'quarter' : 'month')->min($to);
            $p = $this->sales->totals($sales->filter(fn ($s) => $s['at']->between($start, $end)), $refunds->filter(fn ($x) => $x['at']->between($start, $end)));
            $periods[] = [
                'label' => $byQuarter ? 'Q' . $start->quarter . ' ' . $start->year : $start->format('F Y'),
                'gross_receipts' => $p['collected'],
                'refunds' => $p['refunds'],
                'net' => $p['net_sales'],
                'tax' => $taxFor($p['net_sales']),
            ];
            $cursor->add(1, $byQuarter ? 'quarter' : 'month');
        }
        $taxesPaid = ! $this->books->booksReady() ? 0.0 : round((float) VendorExpense::where('owner_id', $vendorId)->where('category', 'taxes')
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->sum('amount'), 2);
        $estimate = $taxFor($t['net_sales']);

        return [
            'title' => 'Tax summary',
            'subtitle' => $modeLabel,
            'notes' => array_values(array_filter([
                'An estimate to help with your filing, not tax advice. Confirm the amounts with your accountant before filing with the BIR.',
                $mode === 'percentage' ? 'Non-VAT shops file percentage tax quarterly on BIR Form 2551Q.' : null,
                $mode === 'vat' ? 'VAT-registered shops file quarterly on BIR Form 2550Q. Input VAT on purchases is not deducted here.' : null,
                $mode === 'income8' ? 'The 8% option applies to yearly sales above ₱250,000; that exemption is taken on the annual return and is not deducted here.' : null,
            ])),
            'summary' => [
                ['Gross receipts', $t['collected'], 'money', $this->count($t['orders'], 'paid order')],
                ['Less refunds', $t['refunds'], 'money'],
                ['Net taxable sales', $t['net_sales'], 'money'],
                ['Estimated tax', $estimate, 'money', $modeLabel],
                ['Tax charged at checkout', $t['tax'], 'money'],
                ['Taxes & permits paid', $taxesPaid, 'money', 'from Expenses'],
            ],
            'tables' => [
                [
                    'title' => $byQuarter ? 'By quarter' : 'By month',
                    'columns' => [['label', 'Period', 'text'], ['gross_receipts', 'Gross receipts', 'money'], ['refunds', 'Refunds', 'money'], ['net', 'Net taxable sales', 'money'], ['tax', 'Estimated tax', 'money']],
                    'rows' => $periods,
                    'totals' => ['label' => 'Total', 'gross_receipts' => $t['collected'], 'refunds' => $t['refunds'], 'net' => $t['net_sales'], 'tax' => $estimate],
                ],
            ],
        ];
    }
}
