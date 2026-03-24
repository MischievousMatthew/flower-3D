<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly int    $expiryMinutes = 5,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your Bloomcraft verification code')
            ->view('emails.otp');
    }
}