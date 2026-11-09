@extends('emails.layouts.bloomcraft')

@php
    $amount = (float) $payment->amount;
    $paidAt = $payment->created_at ?? now();
    $method = $payment->payment_channel
        ? $payment->payment_channel . ($viaPayMongo ? ' via PayMongo' : '')
        : ($viaPayMongo ? 'PayMongo' : 'Bank or e-wallet transfer');
    $rowL = 'padding:9px 0;border-bottom:1px solid #eef1ef;color:#6b7380;font-size:13px;';
    $rowR = 'padding:9px 0;border-bottom:1px solid #eef1ef;color:#363F4E;font-size:13.5px;font-weight:600;text-align:right;';
    $lines = $order->items->filter(fn ($i) => ((int) $i->quantity - (int) $i->cancelled_qty) > 0);
@endphp

@section('title', "Payment receipt {$payment->receipt_number}")
@section('preheader', "{$shopName} paid ₱" . number_format($amount, 2) . " for purchase order {$order->order_number}.")
@section('eyebrow', 'Payment receipt')
@section('heading', '₱' . number_format($amount, 2))

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
        Hello {{ $order->supplier?->contact_person ?: 'there' }},<br>
        {{ $shopName }} has paid for purchase order <strong>{{ $order->order_number }}</strong>. Here's your receipt.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;">
        <tr><td style="{{ $rowL }}">Receipt no.</td><td style="{{ $rowR }}">{{ $payment->receipt_number }}</td></tr>
        <tr><td style="{{ $rowL }}">Status</td><td style="{{ $rowR }}color:#2f8a52;">Paid</td></tr>
        <tr><td style="{{ $rowL }}">Date and time</td><td style="{{ $rowR }}">{{ $paidAt->timezone('Asia/Manila')->format('F j, Y · g:i A') }}</td></tr>
        <tr><td style="{{ $rowL }}">Paid through</td><td style="{{ $rowR }}">{{ $method }}</td></tr>
        <tr><td style="{{ $rowL }}">{{ $viaPayMongo ? 'PayMongo payment ID' : 'Reference no.' }}</td><td style="{{ $rowR }}font-family:Menlo,Consolas,monospace;font-size:12.5px;">{{ $payment->reference }}</td></tr>
        <tr><td style="{{ $rowL }}">From</td><td style="{{ $rowR }}">{{ $shopName }}</td></tr>
        <tr><td style="{{ $rowL }}">To</td><td style="{{ $rowR }}">{{ $order->supplier?->company_name }}</td></tr>
        @if ($account)
            <tr><td style="{{ $rowL }}">Your account</td><td style="{{ $rowR }}">{{ $account['provider'] }} {{ $account['account_number'] }}</td></tr>
        @endif
        <tr><td style="{{ $rowL }}">For</td><td style="{{ $rowR }}">{{ $order->order_number }}{{ $funding?->finance_request_id ? ' · ' . $funding->finance_request_id : '' }}</td></tr>
        <tr><td style="padding:12px 0 0;color:#363F4E;font-size:14px;font-weight:600;">Amount paid</td><td style="padding:12px 0 0;color:#363F4E;font-size:20px;font-weight:700;text-align:right;">&#8369;{{ number_format($amount, 2) }}</td></tr>
    </table>

    @if ($lines->isNotEmpty())
        @include('emails.partials.panel', [
            'tone' => 'plain',
            'label' => 'Items in the order',
            'html' => $lines->map(fn ($i) => e($i->product_name) . ' <span style="color:#6b7380;">&middot; ' . number_format((int) $i->quantity - (int) $i->cancelled_qty) . ' ' . e($i->unit ?? '') . ' &times; &#8369;' . number_format((float) $i->price, 2) . '</span>')->implode('<br>'),
        ])
    @endif

    <p style="margin:0;font-size:13px;line-height:1.6;color:#7a8290;">
        Keep this email as your receipt. Questions about the payment? Reply to {{ $shopName }} with the receipt number. You can also see it on the order page from the link in the earlier email.
    </p>
@endsection
