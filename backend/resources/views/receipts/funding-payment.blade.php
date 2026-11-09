@php
    $amount = (float) $payment->amount;
    $paidAt = ($payment->created_at ?? now())->timezone('Asia/Manila');
    $viaPayMongo = str_starts_with((string) $payment->note, 'Paid through PayMongo');
    $method = ($payment->payment_channel ?: 'Bank or e-wallet') . ($viaPayMongo ? ' via PayMongo' : '');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Payment receipt {{ $payment->receipt_number }}</title>
<style>
    @page { margin: 36px 40px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #363F4E; }
    .brand { font-size: 12px; font-weight: bold; letter-spacing: 1.5px; color: #4DB173; text-transform: uppercase; }
    h1 { margin: 4px 0 0; font-size: 20px; color: #363F4E; }
    .top { width: 100%; margin-bottom: 18px; }
    .top td { vertical-align: top; }
    .paid { display: inline-block; padding: 4px 12px; border-radius: 12px; background: #e3f5ea; color: #2f8a52; font-weight: bold; font-size: 11px; }
    .amount { margin: 0 0 18px; padding: 16px; border-radius: 10px; background: #f4f7f5; text-align: center; }
    .amount span { display: block; font-size: 10px; color: #6b7380; }
    .amount strong { display: block; margin-top: 2px; font-size: 26px; color: #363F4E; }
    table.rows { width: 100%; border-collapse: collapse; }
    table.rows td { padding: 8px 0; border-bottom: 1px dashed #dfe5e1; }
    table.rows td.k { color: #6b7380; width: 42%; }
    table.rows td.v { text-align: right; font-weight: bold; }
    .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 10px; }
    h2 { margin: 20px 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #6b7380; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items th { text-align: left; font-size: 9px; color: #6b7380; padding: 6px 0; border-bottom: 1px solid #dfe5e1; }
    table.items td { padding: 6px 0; border-bottom: 1px solid #eef1ef; }
    .num { text-align: right; }
    .foot { margin-top: 22px; font-size: 9px; color: #7a8290; line-height: 1.5; }
</style>
</head>
<body>
    <table class="top">
        <tr>
            <td>
                <div class="brand">BloomCraft</div>
                <h1>Payment receipt</h1>
                <div style="margin-top:2px;color:#6b7380;">{{ $shopName }}</div>
            </td>
            <td style="text-align:right;"><span class="paid">PAID</span></td>
        </tr>
    </table>

    <div class="amount">
        <span>Amount paid</span>
        <strong>PHP {{ number_format($amount, 2) }}</strong>
    </div>

    <table class="rows">
        <tr><td class="k">Receipt no.</td><td class="v mono">{{ $payment->receipt_number }}</td></tr>
        <tr><td class="k">Date and time</td><td class="v">{{ $paidAt->format('F j, Y · g:i A') }}</td></tr>
        <tr><td class="k">Paid through</td><td class="v">{{ $method }}</td></tr>
        <tr><td class="k">{{ $viaPayMongo ? 'PayMongo payment ID' : 'Reference no.' }}</td><td class="v mono">{{ $payment->reference ?: '—' }}</td></tr>
        @if ($payment->recorded_by_name)
            <tr><td class="k">Paid by</td><td class="v">{{ $payment->recorded_by_name }}</td></tr>
        @endif
        <tr><td class="k">From</td><td class="v">{{ $shopName }}</td></tr>
        <tr><td class="k">To</td><td class="v">{{ $order?->supplier?->company_name ?? 'Supplier' }}</td></tr>
        @if ($account)
            <tr><td class="k">Their account</td><td class="v">{{ $account['provider'] }} {{ $account['account_number'] }}</td></tr>
        @endif
        <tr><td class="k">For</td><td class="v">{{ collect([$order?->order_number, $funding->finance_request_id])->filter()->implode(' · ') }}</td></tr>
    </table>

    @if ($lines->isNotEmpty())
        <h2>Items in the order</h2>
        <table class="items">
            <tr><th>Item</th><th class="num">Quantity</th><th class="num">Price</th><th class="num">Amount</th></tr>
            @foreach ($lines as $i)
                @php $q = (int) $i->quantity - (int) $i->cancelled_qty; @endphp
                <tr>
                    <td>{{ $i->product_name }}</td>
                    <td class="num">{{ number_format($q) }} {{ $i->unit }}</td>
                    <td class="num">PHP {{ number_format((float) $i->price, 2) }}</td>
                    <td class="num">PHP {{ number_format($q * (float) $i->price, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p class="foot">
        Issued by BloomCraft for {{ $shopName }}'s records. {{ $viaPayMongo ? 'The payment was processed by PayMongo.' : 'Recorded by Finance from the bank or e-wallet confirmation.' }}
        Generated {{ now()->timezone('Asia/Manila')->format('F j, Y g:i A') }}.
    </p>
</body>
</html>
