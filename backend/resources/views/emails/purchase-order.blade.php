@extends('emails.layouts.bloomcraft')

@php
    $th = 'padding:10px 8px;border-bottom:2px solid #e3e8e5;color:#6b7380;font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;';
    $td = 'padding:11px 8px;border-bottom:1px solid #eef1ef;color:#363F4E;font-size:14px;';
@endphp

@section('title', "Purchase order {$order->order_number}")
@section('preheader', "{$shopName} sent you purchase order {$order->order_number}.")
@section('eyebrow', "Purchase order from {$shopName}")
@section('heading', $order->order_number)

@section('content')
    <p style="margin:-8px 0 22px;font-size:13.5px;color:#6b7380;">
        Ordered {{ optional($order->order_date)->format('F j, Y') ?? now()->format('F j, Y') }}
        @if ($order->expected_date)
            &nbsp;&middot;&nbsp; Please deliver by <strong style="color:#363F4E;">{{ $order->expected_date->format('l, F j, Y') }}</strong>
        @endif
    </p>

    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        Hello {{ $order->supplier?->contact_person ?: 'there' }},<br>
        {{ $shopName }} would like to order the following from {{ $order->supplier?->company_name }}.
        @if ($respondUrl)
            Open the order to confirm it, tell us if anything is unavailable, and mark it shipped when it's on the way.
        @else
            Please reply to this email to confirm, or to tell us if anything is unavailable.
        @endif
    </p>

    @if ($respondUrl)
        @include('emails.partials.button', ['url' => $respondUrl, 'label' => 'View and confirm the order', 'margin' => '22px 0 8px'])
        <p style="margin:0 0 26px;font-size:12.5px;color:#7a8290;">
            No account needed. This link is only for you{{ $linkExpires ? ' and works until ' . \Carbon\Carbon::parse($linkExpires)->format('F j, Y') : '' }}.
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:8px 0 18px;font-family:'Poppins','Segoe UI',Helvetica,Arial,sans-serif;">
        <thead>
            <tr>
                <th align="left" style="{{ $th }}">Item</th>
                <th align="right" style="{{ $th }}">Qty</th>
                <th align="right" style="{{ $th }}">Price</th>
                <th align="right" style="{{ $th }}">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td style="{{ $td }}">{{ $item->product_name }}</td>
                    <td align="right" style="{{ $td }}white-space:nowrap;">{{ number_format($item->quantity) }} {{ $item->unit ?: '' }}</td>
                    <td align="right" style="{{ $td }}white-space:nowrap;">&#8369;{{ number_format((float) $item->price, 2) }}{{ $item->unit ? ' / ' . $item->unit : '' }}</td>
                    <td align="right" style="{{ $td }}white-space:nowrap;">&#8369;{{ number_format($item->quantity * (float) $item->price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" align="right" style="padding:16px 8px 0;font-size:14px;font-weight:600;color:#363F4E;">Total</td>
                <td align="right" style="padding:16px 8px 0;font-size:18px;font-weight:600;color:#2f8f5b;white-space:nowrap;">&#8369;{{ number_format((float) $order->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($order->notes)
        @include('emails.partials.panel', ['tone' => 'green', 'label' => 'Notes', 'body' => $order->notes])
    @endif

    <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#7a8290;">
        Sent by {{ $sentBy ?: $shopName }} through BloomCraft.
    </p>
@endsection
