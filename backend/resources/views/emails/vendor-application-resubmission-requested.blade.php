@extends('emails.layouts.bloomcraft')

@section('title', 'A few things to update')
@section('preheader', 'Your BloomCraft application is almost there. Please update a few details.')
@section('eyebrow', 'Action needed')
@section('eyebrow_color', '#8a6417')
@section('heading', 'A few things to update')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.7;">
        Hi {{ \Illuminate\Support\Str::of($application->owner_name ?: 'there')->before(' ') }},
    </p>
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        We reviewed your application for <strong style="color:#363F4E;">{{ $application->store_name ?: 'your shop' }}</strong>.
        It's almost there: some details or documents need updating before we can approve it.
    </p>

    @include('emails.partials.panel', [
        'tone' => 'amber',
        'label' => 'What to do',
        'body' => "Open the link below to see exactly what to change, then send it back. You only update what we ask for.",
    ])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Update my application', 'margin' => '6px 0 8px'])

    <p style="margin:18px 0 0;font-size:13.5px;line-height:1.7;color:#6b7380;">
        This link is just for you, so please don't share it.
    </p>
@endsection
