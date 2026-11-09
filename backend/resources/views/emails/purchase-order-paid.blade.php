@extends('emails.layouts.bloomcraft')

@section('title', "Payment sent: {$order->order_number}")
@section('preheader', "{$shopName} sent ₱" . number_format($amount, 2) . " for purchase order {$order->order_number}.")
@section('eyebrow', "Payment from {$shopName}")
@section('heading', '₱' . number_format($amount, 2))

@section('content')
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        Hello {{ $order->supplier?->contact_person ?: 'there' }},<br>
        {{ $shopName }} has paid for purchase order <strong>{{ $order->order_number }}</strong>.
    </p>

    @include('emails.partials.panel', [
        'tone' => 'green',
        'label' => 'Payment details',
        'html' => collect([
            'Amount: <strong>&#8369;' . number_format($amount, 2) . '</strong>',
            'Date: ' . e($paidOn->format('F j, Y')),
            'Reference: ' . e($reference),
            $channel ? 'Sent through: ' . e($channel) : null,
            $account ? 'Into: ' . e($account['provider']) . ' ' . e($account['account_number']) . ($account['account_name'] ? ' (' . e($account['account_name']) . ')' : '') : null,
        ])->filter()->implode('<br>'),
    ])

    <p style="margin:0;font-size:13px;line-height:1.6;color:#7a8290;">
        If it hasn't reached you in a day or two, reply to the shop with the reference number. You can also see it on the order page from the link in the earlier email.
    </p>
@endsection
