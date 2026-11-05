@extends('emails.layouts.bloomcraft')

@section('title', 'Your verification code')
@section('preheader', "Your BloomCraft code is {$otp}. It expires in {$expiryMinutes} minutes.")
@section('eyebrow', 'Verification code')
@section('heading', 'Here is your code')

@section('content')
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        Enter this code on BloomCraft to continue.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">
        <tr>
            <td align="center" style="background:#f1f8f3;border:1px dashed #9fd4b2;border-radius:16px;padding:24px 12px;">
                <span style="font-family:'Poppins','Segoe UI',Helvetica,Arial,sans-serif;font-size:38px;font-weight:600;letter-spacing:12px;color:#363F4E;">{{ $otp }}</span>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:14.5px;line-height:1.7;">
        It expires in <strong style="color:#363F4E;">{{ $expiryMinutes }} minutes</strong>. Never share it with anyone, including BloomCraft staff.
    </p>
    <p style="margin:0;font-size:13.5px;line-height:1.7;color:#6b7380;">
        Didn't ask for a code? You can ignore this email. Your account stays safe.
    </p>
@endsection
