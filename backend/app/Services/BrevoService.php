<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    public function send($toEmail, $toName, $subject, $htmlContent)
    {
        $response = Http::timeout(15)->withHeaders([
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

        // Brevo answers with why it didn't send (bad key, unverified sender, ...)
        if ($response->failed()) {
            Log::warning('Brevo did not send an email.', ['subject' => $subject, 'status' => $response->status(), 'body' => $response->body()]);
        }

        return $response;
    }
}
