@extends('emails.layouts.bloomcraft')

@section('title', 'About your BloomCraft application')
@section('preheader', 'We reviewed your application for ' . ($application->store_name ?: 'your shop') . '.')
@section('eyebrow', 'Application update')
@section('eyebrow_color', '#a8443a')
@section('heading', "We can't approve it yet")

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.7;">
        Hi {{ \Illuminate\Support\Str::of($application->owner_name ?: 'there')->before(' ') }},
    </p>
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        Thank you for applying to sell on BloomCraft. We looked at your application for
        <strong style="color:#363F4E;">{{ $application->store_name ?: 'your shop' }}</strong>, and we can't approve it right now.
    </p>

    @include('emails.partials.panel', ['tone' => 'rose', 'label' => 'Why', 'body' => $rejectionReason])

    <p style="margin:0 0 10px;font-size:15px;line-height:1.7;">
        You're welcome to apply again once the points above are sorted out.
    </p>
    <p style="margin:0;font-size:13.5px;line-height:1.7;color:#6b7380;">
        Questions? Write to us at <a href="mailto:{{ $supportEmail }}" style="color:#2f8f5b;">{{ $supportEmail }}</a>.
    </p>
@endsection
