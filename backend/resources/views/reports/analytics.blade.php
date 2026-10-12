@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%';
    $change = function ($now, $before) {
        if ($before === null || (float) $before == 0.0) {
            return (float) $now > 0 ? 'new' : '—';
        }
        $c = ((float) $now - (float) $before) / (float) $before * 100;
        return ($c >= 0 ? '▲ ' : '▼ ') . number_format(abs($c), 1) . '%';
    };
    $s = $r['summary'];
    $maxSales = max(1, collect($r['series'])->max('sales'));
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>BloomCraft report</title>
<style>
    @page { margin: 28px 32px 40px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1e2739; }
    h1 { font-size: 20px; margin: 0 0 2px; color: #4c3fd0; }
    h2 { font-size: 13px; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #ede9fe; color: #1e2739; }
    .muted { color: #64748b; }
    .small { font-size: 8.5px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; color: #64748b; background: #f8fafc; padding: 5px 6px; }
    td { padding: 5px 6px; border-bottom: 1px solid #eef1f6; vertical-align: middle; }
    .num { text-align: right; }
    .kpis td { width: 25%; border: 1px solid #e9edf3; padding: 8px 10px; vertical-align: top; }
    .kpi-label { font-size: 8.5px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
    .kpi-value { font-size: 15px; font-weight: bold; margin: 3px 0; }
    .up { color: #15803d; } .down { color: #b91c1c; }
    .bar { height: 9px; background: #7c6ff7; }
    .bar-sub { height: 9px; background: #22c55e; }
    .bar-cell { width: 45%; }
    .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #94a3b8; text-align: center; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>
    <div class="footer">BloomCraft · Reports &amp; analytics · {{ $r['period']['label'] }} · generated {{ $generated->format('M j, Y g:i A') }}</div>

    <h1>Reports &amp; analytics</h1>
    <div class="muted">{{ $r['period']['label'] }} · grouped by {{ $r['period']['unit'] }} · compared with {{ \Carbon\Carbon::parse($r['period']['previous_from'])->format('M j, Y') }} – {{ \Carbon\Carbon::parse($r['period']['previous_to'])->format('M j, Y') }}</div>

    <h2>Summary</h2>
    <table class="kpis">
        <tr>
            <td><div class="kpi-label">Sales</div><div class="kpi-value">{{ $peso($s['sales']) }}</div><div class="small muted">{{ $change($s['sales'], $s['sales_previous']) }} vs {{ $peso($s['sales_previous']) }}</div></td>
            <td><div class="kpi-label">Paid orders</div><div class="kpi-value">{{ $s['paid_orders'] }}</div><div class="small muted">{{ $change($s['paid_orders'], $s['paid_orders_previous']) }} · avg {{ $peso($s['average_order']) }}</div></td>
            <td><div class="kpi-label">Cancellation / refund rate</div><div class="kpi-value">{{ $pct($s['cancellation_rate']) }} / {{ $pct($s['refund_rate']) }}</div><div class="small muted">was {{ $pct($s['cancellation_rate_previous']) }} / {{ $pct($s['refund_rate_previous']) }}</div></td>
            <td><div class="kpi-label">Subscription revenue</div><div class="kpi-value">{{ $peso($s['subscription_revenue']) }}</div><div class="small muted">{{ $change($s['subscription_revenue'], $s['subscription_revenue_previous']) }} · {{ $s['new_customers'] }} new customers</div></td>
        </tr>
    </table>

    @if (in_array('sales', $sections))
        <h2>Sales over time</h2>
        <table>
            <tr><th>{{ ucfirst($r['period']['unit']) }}</th><th class="bar-cell">Sales</th><th class="num">Sales</th><th class="num">Paid orders</th><th class="num">Avg order</th><th class="num">Placed</th><th class="num">Subscriptions</th></tr>
            @foreach ($r['series'] as $p)
                <tr>
                    <td>{{ $p['label'] }}</td>
                    <td class="bar-cell"><div class="bar" style="width: {{ max(1, round($p['sales'] / $maxSales * 100)) }}%"></div></td>
                    <td class="num">{{ $peso($p['sales']) }}</td>
                    <td class="num">{{ $p['paid_orders'] }}</td>
                    <td class="num">{{ $peso($p['average_order']) }}</td>
                    <td class="num">{{ $p['orders_placed'] }}</td>
                    <td class="num">{{ $peso($p['subscription_revenue']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (in_array('products', $sections))
        <h2>Best-selling flowers</h2>
        @if (empty($r['best_sellers']['products']))
            <p class="muted">No paid sales in this period.</p>
        @else
            <table>
                <tr><th>#</th><th>Product</th><th>Vendor</th><th>Category</th><th class="num">Sold</th><th class="num">Revenue</th><th class="num">Share</th></tr>
                @foreach ($r['best_sellers']['products'] as $i => $p)
                    <tr><td>{{ $i + 1 }}</td><td>{{ $p['name'] }}</td><td>{{ $p['vendor'] }}</td><td>{{ $p['category'] }}</td><td class="num">{{ $p['quantity'] }}</td><td class="num">{{ $peso($p['revenue']) }}</td><td class="num">{{ $pct($p['share']) }}</td></tr>
                @endforeach
            </table>
            <table style="margin-top: 10px">
                <tr>
                    @foreach (['categories' => 'Categories', 'flower_types' => 'Flower types', 'occasions' => 'Occasions'] as $key => $title)
                        <td style="width: 33%; vertical-align: top; border: none; padding: 0 6px 0 0">
                            <table>
                                <tr><th>{{ $title }}</th><th class="num">Sold</th><th class="num">Share</th></tr>
                                @forelse ($r['best_sellers'][$key] as $row)
                                    <tr><td>{{ $row['label'] }}</td><td class="num">{{ $row['quantity'] }}</td><td class="num">{{ $pct($row['share']) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="muted">—</td></tr>
                                @endforelse
                            </table>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
    @endif

    @if (in_array('vendors', $sections))
        <h2>Vendor performance</h2>
        @if (empty($r['vendors']))
            <p class="muted">No vendor activity in this period.</p>
        @else
            <table>
                <tr><th>Vendor</th><th class="num">Sales</th><th class="num">Share</th><th class="num">Growth</th><th class="num">Orders</th><th class="num">Avg order</th><th class="num">Completed</th><th class="num">Cancelled</th><th class="num">Refunded</th><th class="num">Fulfilment</th><th class="num">Rating</th></tr>
                @foreach ($r['vendors'] as $v)
                    <tr>
                        <td>{{ $v['vendor']['store_name'] }}</td>
                        <td class="num">{{ $peso($v['sales']) }}</td>
                        <td class="num">{{ $pct($v['share']) }}</td>
                        <td class="num {{ ($v['growth'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ $v['growth'] === null ? '—' : ($v['growth'] >= 0 ? '+' : '') . $v['growth'] . '%' }}</td>
                        <td class="num">{{ $v['paid_orders'] }}/{{ $v['orders_placed'] }}</td>
                        <td class="num">{{ $peso($v['average_order']) }}</td>
                        <td class="num">{{ $pct($v['completion_rate']) }}</td>
                        <td class="num">{{ $pct($v['cancellation_rate']) }}</td>
                        <td class="num">{{ $pct($v['refund_rate']) }}</td>
                        <td class="num">{{ $v['avg_fulfilment_hours'] === null ? '—' : $v['avg_fulfilment_hours'] . ' h' }}</td>
                        <td class="num">{{ $v['rating'] === null ? '—' : '★ ' . $v['rating'] }}</td>
                    </tr>
                @endforeach
            </table>
            <p class="small muted">Orders = paid / placed. Rates are out of finished orders.</p>
        @endif
    @endif

    @if (in_array('cancellations', $sections))
        @php $c = $r['cancellations']; @endphp
        <h2>Cancellations &amp; refunds</h2>
        <table>
            <tr><th>Finished orders</th><th class="num">Cancelled</th><th class="num">Refunded / returned</th><th class="num">Return requests</th><th class="num">Refund requests</th><th class="num">Approved</th><th class="num">Rejected</th><th class="num">Open</th></tr>
            <tr><td>{{ $c['finished'] }}</td><td class="num">{{ $c['cancelled'] }}</td><td class="num">{{ $c['refunded'] }}</td><td class="num">{{ $c['requests']['returns'] }}</td><td class="num">{{ $c['requests']['refunds'] }}</td><td class="num">{{ $c['requests']['approved'] }}</td><td class="num">{{ $c['requests']['rejected'] }}</td><td class="num">{{ $c['requests']['open'] }}</td></tr>
        </table>
        <table style="margin-top: 8px">
            <tr><th>Payment method</th><th class="num">Orders</th><th class="num">Cancelled</th><th class="num">Cancellation rate</th></tr>
            @foreach ($c['by_method'] as $row)
                <tr><td>{{ ucwords(str_replace('_', ' ', $row['method'])) }}</td><td class="num">{{ $row['orders'] }}</td><td class="num">{{ $row['cancelled'] }}</td><td class="num">{{ $pct($row['cancellation_rate']) }}</td></tr>
            @endforeach
        </table>
        <table style="margin-top: 8px">
            <tr><th>{{ ucfirst($r['period']['unit']) }}</th><th class="num">Placed</th><th class="num">Cancelled</th><th class="num">Refunded</th><th class="num">Cancellation rate</th><th class="num">Refund rate</th></tr>
            @foreach ($r['series'] as $p)
                @if ($p['orders_placed'])
                    <tr><td>{{ $p['label'] }}</td><td class="num">{{ $p['orders_placed'] }}</td><td class="num">{{ $p['cancelled'] }}</td><td class="num">{{ $p['refunded'] }}</td><td class="num">{{ $pct($p['cancellation_rate']) }}</td><td class="num">{{ $pct($p['refund_rate']) }}</td></tr>
                @endif
            @endforeach
        </table>
    @endif

    @if (in_array('subscriptions', $sections))
        @php $sub = $r['subscriptions']; $maxSub = max(1, collect($r['series'])->max('subscription_revenue')); @endphp
        <h2>Subscription revenue &amp; growth</h2>
        <table class="kpis">
            <tr>
                <td><div class="kpi-label">Revenue</div><div class="kpi-value">{{ $peso($sub['revenue']) }}</div><div class="small muted">{{ $sub['growth'] === null ? 'no earlier revenue' : (($sub['growth'] >= 0 ? '▲ ' : '▼ ') . abs($sub['growth']) . '% vs ' . $peso($sub['previous'])) }}</div></td>
                <td><div class="kpi-label">Monthly recurring</div><div class="kpi-value">{{ $peso($sub['mrr']) }}</div><div class="small muted">{{ $peso($sub['arr']) }} a year</div></td>
                <td><div class="kpi-label">Paying vendors</div><div class="kpi-value">{{ $sub['paying_vendors'] }}</div><div class="small muted">+{{ $sub['new_paying_vendors'] }} new this period</div></td>
                <td><div class="kpi-label">Payments</div><div class="kpi-value">{{ $sub['payments'] }}</div><div class="small muted">this period</div></td>
            </tr>
        </table>
        <table style="margin-top: 8px">
            <tr><th>{{ ucfirst($r['period']['unit']) }}</th><th class="bar-cell">Revenue</th><th class="num">Revenue</th><th class="num">New paying vendors</th></tr>
            @foreach ($r['series'] as $p)
                <tr><td>{{ $p['label'] }}</td><td class="bar-cell"><div class="bar-sub" style="width: {{ max(1, round($p['subscription_revenue'] / $maxSub * 100)) }}%"></div></td><td class="num">{{ $peso($p['subscription_revenue']) }}</td><td class="num">{{ $p['new_paying_vendors'] }}</td></tr>
            @endforeach
        </table>
        @if (!empty($sub['by_plan']))
            <table style="margin-top: 8px">
                <tr><th>Plan</th><th class="num">Payments</th><th class="num">Revenue</th></tr>
                @foreach ($sub['by_plan'] as $row)
                    <tr><td>{{ $row['plan'] }}</td><td class="num">{{ $row['payments'] }}</td><td class="num">{{ $peso($row['revenue']) }}</td></tr>
                @endforeach
            </table>
        @endif
    @endif
</body>
</html>
