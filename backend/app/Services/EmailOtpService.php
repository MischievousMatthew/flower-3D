<?php

namespace App\Services;

use App\Models\EmailOtp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class EmailOtpService
{
    private const OTP_LENGTH          = 6;
    private const OTP_EXPIRY_MINUTES  = 5;
    private const MAX_ATTEMPTS        = 5;
    private const COOLDOWN_SECONDS    = 60;
    private const MAX_PER_HOUR        = 3;
    private const MAX_PER_HOUR_IP     = 10;

    public static function send(string $email, string $ip): void
    {
        $otp = rand(100000, 999999);

        // Save OTP in DB or cache (your logic here)

        $apiKey = config('services.brevo.key');

        if (!$apiKey) {
            throw new \Exception('Brevo API key is missing');
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'name' => config('mail.from.name'),
                'email' => config('mail.from.address'),
            ],
            'to' => [
                ['email' => $email],
            ],
            'subject' => 'Your OTP Code',
            'htmlContent' => "<p>Your OTP is: <strong>{$otp}</strong></p>",
        ]);

        if (!$response->successful()) {
            Log::error('BREVO ERROR', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \Exception('Failed to send email: ' . $response->body());
        }
    }

    public static function verify(string $email, string $plain): bool
    {
        $record = EmailOtp::where('email', $email)->first();

        if (!$record) {
            Log::warning("OTP verify: no record found", ['email' => $email]);
            return false;
        }

        if ($record->is_locked) {
            throw new Exception('Too many failed attempts. Please request a new OTP.');
        }

        if ($record->isExpired()) {
            $record->delete();
            throw new Exception('OTP has expired. Please request a new one.');
        }

        $record->incrementAttempts(self::MAX_ATTEMPTS);

        if (!Hash::check($plain, $record->otp_hash)) {
            $remaining = self::MAX_ATTEMPTS - $record->fresh()->attempts;
            Log::warning("OTP verify: invalid code", ['email' => $email, 'attempts' => $record->attempts]);
            throw new Exception("Invalid OTP. {$remaining} attempt(s) remaining.");
        }

        $record->delete();   // single-use
        Log::info("OTP verified successfully", ['email' => $email]);
        return true;
    }
}