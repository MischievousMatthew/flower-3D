<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase order {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f4;font-family:Arial,sans-serif;color:#1f2430;">
    <div style="max-width:680px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border-radius:18px;padding:32px;border:1px solid #e3e8e5;">
            <p style="margin:0 0 6px;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#2f8f5b;font-weight:700;">
                Purchase order from {{ $shopName }}
            </p>
            <h1 style="margin:0 0 4px;font-size:26px;line-height:1.25;color:#1f2430;">{{ $order->order_number }}</h1>
            <p style="margin:0 0 24px;font-size:14px;color:#5b6472;">
                Ordered {{ optional($order->order_date)->format('F j, Y') ?? now()->format('F j, Y') }}
                @if ($order->expected_date)
                    &middot; Please deliver by <strong style="color:#1f2430;">{{ $order->expected_date->format('l, F j, Y') }}</strong>
                @endif
            </p>

            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#3b4250;">
                Hello {{ $order->supplier?->contact_person ?: 'there' }},<br>
                {{ $shopName }} would like to order the following from {{ $order->supplier?->company_name }}.
                Please reply to this email to confirm, or if anything is unavailable.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:0 0 18px;">
                <thead>
                    <tr>
                        <th align="left" style="padding:10px 8px;border-bottom:2px solid #e3e8e5;color:#5b6472;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">Item</th>
                        <th align="right" style="padding:10px 8px;border-bottom:2px solid #e3e8e5;color:#5b6472;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">Quantity</th>
                        <th align="right" style="padding:10px 8px;border-bottom:2px solid #e3e8e5;color:#5b6472;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">Agreed price</th>
                        <th align="right" style="padding:10px 8px;border-bottom:2px solid #e3e8e5;color:#5b6472;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td style="padding:10px 8px;border-bottom:1px solid #eef1ef;color:#1f2430;">{{ $item->product_name }}</td>
                            <td align="right" style="padding:10px 8px;border-bottom:1px solid #eef1ef;color:#1f2430;">{{ number_format($item->quantity) }} {{ $item->unit ?: '' }}</td>
                            <td align="right" style="padding:10px 8px;border-bottom:1px solid #eef1ef;color:#1f2430;">&#8369;{{ number_format((float) $item->price, 2) }}{{ $item->unit ? ' / ' . $item->unit : '' }}</td>
                            <td align="right" style="padding:10px 8px;border-bottom:1px solid #eef1ef;color:#1f2430;">&#8369;{{ number_format($item->quantity * (float) $item->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" align="right" style="padding:14px 8px 0;font-weight:700;color:#1f2430;">Total</td>
                        <td align="right" style="padding:14px 8px 0;font-weight:700;font-size:16px;color:#1f2430;">&#8369;{{ number_format((float) $order->total_amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            @if ($order->notes)
                <div style="margin:0 0 18px;padding:14px 16px;border-radius:12px;background:#f3f7f5;border-left:3px solid #4DB173;">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2f8f5b;">Notes</p>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#3b4250;white-space:pre-line;">{{ $order->notes }}</p>
                </div>
            @endif

            <p style="margin:0;font-size:13px;line-height:1.6;color:#7a8290;">
                Sent by {{ $sentBy ?: $shopName }} through BloomCraft.
            </p>
        </div>
    </div>
</body>
</html>
