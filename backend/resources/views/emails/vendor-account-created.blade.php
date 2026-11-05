@extends('emails.layouts.bloomcraft')

@section('title', $isExistingUser ? 'Your shop is approved' : 'Welcome to BloomCraft')
@section('preheader', 'Good news: ' . ($application->store_name ?: 'your shop') . ' is approved on BloomCraft.')
@section('eyebrow', 'Application approved')
@section('heading', $isExistingUser ? 'Your shop is approved' : 'Welcome to BloomCraft')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.7;">
        Hi {{ \Illuminate\Support\Str::of($application->owner_name ?: 'there')->before(' ') }},
    </p>
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        Congratulations! <strong style="color:#363F4E;">{{ $application->store_name ?: 'Your shop' }}</strong> is approved.
        @if ($isExistingUser)
            Your account can now open the shop dashboard. Sign in with your usual email and password.
        @else
            We've made your shop account. Use these details to sign in.
        @endif
    </p>

    @unless ($isExistingUser)
        @include('emails.partials.panel', [
            'tone' => 'green',
            'label' => 'Your sign-in details',
            'html' => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-size:14.5px;">'
                . '<tr><td style="padding:3px 16px 3px 0;color:#6b7380;">Email</td><td style="padding:3px 0;color:#363F4E;font-weight:600;">' . e($application->email) . '</td></tr>'
                . '<tr><td style="padding:3px 16px 3px 0;color:#6b7380;">Password</td><td style="padding:3px 0;"><span style="font-family:Consolas,Menlo,monospace;font-size:15px;font-weight:600;color:#363F4E;background:#ffffff;border:1px solid #d6e9dd;border-radius:6px;padding:3px 8px;">' . e($password) . '</span></td></tr>'
                . '</table>',
        ])
    @endunless

    @include('emails.partials.button', ['url' => $loginUrl, 'label' => 'Open my shop'])

    <h2 style="margin:30px 0 14px;font-family:'Cormorant Garamond',Georgia,serif;font-size:24px;font-weight:600;color:#363F4E;">Get your shop ready</h2>
    @include('emails.partials.steps', ['steps' => array_values(array_filter([
        $isExistingUser ? null : ['Change your password', 'Do this first, from your profile, right after you sign in.'],
        ['Set up your shop profile', 'Logo, cover photo, opening hours and where you deliver.'],
        ['Add your flowers', 'Photos, prices and stock, so customers can build bouquets.'],
        ['Go live', 'Your storefront opens to customers.'],
    ]))])

    <p style="margin:8px 0 0;font-size:13.5px;line-height:1.7;color:#6b7380;">
        Need a hand? Reply to this email and our team will help you set up.
    </p>
@endsection
