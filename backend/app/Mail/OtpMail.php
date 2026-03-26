<?php

namespace App\Mail;

use App\Services\BrevoService;

class OtpMail
{
    protected $email;
    protected $otp;
    protected $expiryMinutes;

    public function __construct($email, $otp, $expiryMinutes = 5)
    {
        $this->email = $email;
        $this->otp = $otp;
        $this->expiryMinutes = $expiryMinutes;
    }

    public function send()
    {
        $brevo = new BrevoService();

        $html = view('emails.otp', [
            'otp' => $this->otp,
            'expiryMinutes' => $this->expiryMinutes,
        ])->render();

        return $brevo->send(
            $this->email,
            'User',
            'Your verification code',
            $html
        );
    }
}