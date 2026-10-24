@php
    $peso = fn ($v) => ($v < 0 ? '-' : '') . '₱' . number_format(abs((float) $v), 2);
    $unitLabel = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'][$unit] ?? 'Daily';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $shop }} · financial report</title>
<style>
    @page { margin: 28px 32px 40px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1a202c; }
    h1 { font-size: 19px; margin: 0 0 2px; color: #2f855a; }
    h2 { font-size: 12.5px; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #c6f6d5; }
    .muted { color: #718096; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; color: #718096; background: #f7fdf9; padding: 6px; }
    td { padding: 5px 6px; border-bottom: 1px solid #edf2f7; }
    .num { text-align: right; }
    .neg { color: #c53030; }
    .total td { font-weight: bold; border-top: 2px solid #c6f6d5; background: #f7fdf9; }
    .kpis td { width: 25%; border: 1px solid #e2e8f0; padding: 8px 10px; vertical-align: top; }
    .kpi-label { font-size: 8.5px; color: #718096; text-transform: uppercase; letter-spacing: .4px; }
    .kpi-value { font-size: 15px; font-weight: bold; margin-top: 3px; }
    .pl td { padding: 5px 8px; }
    .pl .sub td { font-weight: bold; background: #f7fdf9; }
    .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #a0aec0; text-align: center; }
</style>
</head>
<body>
    <div class="footer">{{ $shop }} · BloomCraft financial report · {{ $range['label'] }} · generated {{ $generated->format('M j, Y g:i A') }}</div>

    <h1>{{ $shop }}</h1>
    <div class="muted">{{ $unitLabel }} financial report · {{ $range['label'] }}</div>

    <h2>Summary</h2>
    <table class="kpis">
        <tr>
            <td><div class="kpi-label">Net sales</div><div class="kpi-value">{{ $peso($t['net_sales']) }}</div><div class="muted">{{ $t['orders'] }} paid orders</div></td>
            <td><div class="kpi-label">Gross profit</div><div class="kpi-value">{{ $peso($t['gross_profit']) }}</div><div class="muted">{{ $t['gross_margin'] !== null ? $t['gross_margin'] . '% margin' : '—' }}</div></td>
            <td><div class="kpi-label">Expenses</div><div class="kpi-value">{{ $peso($t['operating_expenses']) }}</div><div class="muted">payroll + recorded expenses</div></td>
            <td><div class="kpi-label">Net profit</div><div class="kpi-value {{ $t['net_profit'] < 0 ? 'neg' : '' }}">{{ $peso($t['net_profit']) }}</div><div class="muted">{{ $t['net_margin'] !== null ? $t['net_margin'] . '% of net sales' : '—' }}</div></td>
        </tr>
    </table>

    <h2>Profit &amp; loss</h2>
    <table class="pl">
        <tr><td>Sales</td><td class="num">{{ $peso($t['sales']) }}</td></tr>
        <tr><td>Less refunds</td><td class="num">-{{ $peso($t['refunds']) }}</td></tr>
        <tr class="sub"><td>Net sales</td><td class="num">{{ $peso($t['net_sales']) }}</td></tr>
        <tr><td>Less cost of goods sold{{ $t['cogs_estimated'] ? ' (partly estimated from current product costs)' : '' }}</td><td class="num">-{{ $peso($t['cogs']) }}</td></tr>
        <tr class="sub"><td>Gross profit</td><td class="num">{{ $peso($t['gross_profit']) }}</td></tr>
        <tr><td>Less payroll</td><td class="num">-{{ $peso($t['payroll']) }}</td></tr>
        @if (($t['payment_fees'] ?? 0) > 0)
            <tr><td>Less payment fees</td><td class="num">-{{ $peso($t['payment_fees']) }}</td></tr>
        @endif
        @foreach ($t['expenses_by_category'] as $c)
            <tr><td>Less {{ strtolower($c['label']) }}</td><td class="num">-{{ $peso($c['amount']) }}</td></tr>
        @endforeach
        <tr class="sub"><td>Net profit</td><td class="num {{ $t['net_profit'] < 0 ? 'neg' : '' }}">{{ $peso($t['net_profit']) }}</td></tr>
    </table>
    <div class="muted" style="margin-top:6px">Stock purchases in this period: {{ $peso($t['stock_purchases']) }} (counted as cost of goods when the stock sells).</div>

    <h2>{{ $unitLabel }} breakdown</h2>
    <table>
        <tr><th>Period</th><th class="num">Orders</th><th class="num">Sales</th><th class="num">Refunds</th><th class="num">Net sales</th><th class="num">Cost of goods</th><th class="num">Expenses</th><th class="num">Net profit</th></tr>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r['label'] }}</td>
                <td class="num">{{ $r['orders'] }}</td>
                <td class="num">{{ $peso($r['sales']) }}</td>
                <td class="num">{{ $peso($r['refunds']) }}</td>
                <td class="num">{{ $peso($r['net_sales']) }}</td>
                <td class="num">{{ $peso($r['cogs']) }}</td>
                <td class="num">{{ $peso($r['expenses']) }}</td>
                <td class="num {{ $r['net_profit'] < 0 ? 'neg' : '' }}">{{ $peso($r['net_profit']) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="num">{{ $t['orders'] }}</td>
            <td class="num">{{ $peso($t['sales']) }}</td>
            <td class="num">{{ $peso($t['refunds']) }}</td>
            <td class="num">{{ $peso($t['net_sales']) }}</td>
            <td class="num">{{ $peso($t['cogs']) }}</td>
            <td class="num">{{ $peso($t['operating_expenses']) }}</td>
            <td class="num {{ $t['net_profit'] < 0 ? 'neg' : '' }}">{{ $peso($t['net_profit']) }}</td>
        </tr>
    </table>
</body>
</html>
