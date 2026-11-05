@extends('emails.layouts.bloomcraft')

@section('title', 'We got your application')
@section('preheader', 'Thanks for applying to sell on BloomCraft. Here is what happens next.')
@section('eyebrow', 'Application received')
@section('heading', 'Thank you for applying')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.7;">
        Hi {{ \Illuminate\Support\Str::of($application->owner_name ?: 'there')->before(' ') }},
    </p>
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        We've received your application to open <strong style="color:#363F4E;">{{ $application->store_name ?: 'your shop' }}</strong> on BloomCraft.
        Our team will look through your details and documents. You don't need to do anything for now.
    </p>

    @include('emails.partials.panel', [
        'tone' => 'plain',
        'label' => 'What you sent',
        'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">'
            . '<tr><td style="padding:3px 0;color:#6b7380;width:110px;">Shop</td><td style="padding:3px 0;color:#363F4E;font-weight:600;">' . e($application->store_name ?: '-') . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#6b7380;">Owner</td><td style="padding:3px 0;color:#363F4E;">' . e($application->owner_name ?: '-') . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#6b7380;">Email</td><td style="padding:3px 0;color:#363F4E;">' . e($application->email) . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#6b7380;">Sent on</td><td style="padding:3px 0;color:#363F4E;">' . e(optional($application->submitted_at ?? $application->created_at ?? now())->format('F j, Y \a\t g:i A')) . '</td></tr>'
            . '</table>',
    ])

    <h2 style="margin:28px 0 14px;font-family:'Cormorant Garamond',Georgia,serif;font-size:24px;font-weight:600;color:#363F4E;">What happens next</h2>
    @include('emails.partials.steps', ['steps' => [
        ['We check your documents', 'Usually within two working days.'],
        ['You get an email with the decision', 'If something needs fixing, that email says exactly what to send again.'],
        ['Sign in and open your shop', 'Add your flowers and your storefront goes live.'],
    ]])

    <p style="margin:8px 0 0;font-size:13.5px;line-height:1.7;color:#6b7380;">
        Questions in the meantime? Just reply to this email and we'll help.
    </p>
@endsection

@section('footnote')
    You're getting this because this address was used to apply as a seller on BloomCraft.
@endsection
