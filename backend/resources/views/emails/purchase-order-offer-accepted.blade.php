@extends('emails.layouts.bloomcraft')

@section('title', "Accepted: {$order->order_number}")
@section('preheader', "{$shopName} accepted the quantities you offered for {$order->order_number}. You can send it now.")
@section('eyebrow', 'Your numbers were accepted')
@section('eyebrow_color', '#2f8a57')
@section('heading', $order->order_number)

@section('content')
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        Hello {{ $order->supplier?->contact_person ?: 'there' }},<br>
        {{ $shopName }} accepted what you said you can supply. Please prepare these items, then mark the order shipped when it's on the way.
    </p>

    @include('emails.partials.panel', [
        'tone' => 'green',
        'label' => 'Agreed quantities',
        'html' => $order->items
            ->filter(fn ($i) => ((int) $i->quantity - (int) $i->cancelled_qty) > 0)
            ->map(fn ($i) => e($i->product_name) . ' <span style="color:#6b7380;">&middot; ' . number_format((int) $i->quantity - (int) $i->cancelled_qty) . ' ' . e($i->unit ?? '') . '</span>')
            ->implode('<br>'),
    ])

    @if ($note)
        @include('emails.partials.panel', ['tone' => 'plain', 'label' => 'Note from the shop', 'body' => $note])
    @endif

    @if ($respondUrl)
        @include('emails.partials.button', ['url' => $respondUrl, 'label' => 'Open the order to mark it shipped', 'margin' => '22px 0 8px'])
    @endif

    <p style="margin:0;font-size:13px;line-height:1.6;color:#7a8290;">
        Sent by {{ $shopName }} through BloomCraft.
    </p>
@endsection
