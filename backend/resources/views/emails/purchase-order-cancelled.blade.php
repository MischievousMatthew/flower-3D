@extends('emails.layouts.bloomcraft')

@section('title', "Cancelled: {$order->order_number}")
@section('preheader', "{$shopName} cancelled purchase order {$order->order_number}. Please don't send it.")
@section('eyebrow', 'Order cancelled')
@section('eyebrow_color', '#a8443a')
@section('heading', $order->order_number)

@section('content')
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        Hello {{ $order->supplier?->contact_person ?: 'there' }},<br>
        {{ $shopName }} has cancelled this order. Please don't prepare or send it.
    </p>

    @include('emails.partials.panel', [
        'tone' => 'plain',
        'label' => 'Items in the order',
        'html' => $order->items->map(fn ($i) => e($i->product_name) . ' <span style="color:#6b7380;">&middot; ' . number_format($i->quantity) . ' ' . e($i->unit ?? '') . '</span>')->implode('<br>'),
    ])

    @include('emails.partials.panel', ['tone' => 'rose', 'label' => 'Reason', 'body' => $reason])

    <p style="margin:0;font-size:13px;line-height:1.6;color:#7a8290;">
        Sent by {{ $shopName }} through BloomCraft. The link in the earlier email no longer works.
    </p>
@endsection
