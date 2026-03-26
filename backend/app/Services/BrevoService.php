<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoService
{
    public function send($toEmail, $toName, $subject, $htmlContent)
    {
        return Http::withHeaders([
            'api-key' => config('services.brevo.key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'email' => config('mail.from.address'),
                'name' => config('mail.from.name'),
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name' => $toName,
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ]);
    }
}