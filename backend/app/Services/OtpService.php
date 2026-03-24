<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class OtpService
{
    private const OTP_LENGTH = 6;
    private const OTP_EXPIRY_MINUTES = 5;
    private const MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_MINUTES = 1;
    private const MAX_REQUESTS_PER_HOUR = 3;

    public static function send(string $number): string
    {
        Log::info("OtpService::send called for number: {$number}");
        
        $rateLimitKey = "otp_rate_limit:{$number}";
        
        if (Cache::has($rateLimitKey)) {
            Log::warning("Rate limit hit for {$number}");
            throw new Exception('Please wait before requesting another OTP');
        }

        $hourlyKey = "otp_hourly:{$number}";
        $hourlyCount = Cache::get($hourlyKey, 0);
        
        if ($hourlyCount >= self::MAX_REQUESTS_PER_HOUR) {
            Log::warning("Hourly limit exceeded for {$number}");
            throw new Exception('Too many OTP requests. Please try again later.');
        }

        $otp = self::generateOtp();
        
        Log::info("Generated OTP for {$number}: {$otp}");

        Otp::updateOrCreate(
            ['contact_number' => $number],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'attempts' => 0,
            ]
        );

        Cache::put($rateLimitKey, true, now()->addMinutes(self::RATE_LIMIT_MINUTES));
        Cache::put($hourlyKey, $hourlyCount + 1, now()->addHour());

        Log::channel('single')->info("📱 OTP for {$number}: {$otp}");
        
        return $otp;
    }

    public static function verify(string $number, string $otp): bool
    {
        Log::info("Verifying OTP for {$number}");
        
        $otpRecord = Otp::where('contact_number', $number)->first();

        if (!$otpRecord) {
            Log::warning("No OTP record found for {$number}");
            return false;
        }

        if ($otpRecord->attempts >= self::MAX_ATTEMPTS) {
            Log::warning("Max attempts exceeded for {$number}");
            throw new Exception('Maximum verification attempts exceeded. Please request a new OTP.');
        }

        $otpRecord->incrementAttempts();

        if ($otpRecord->isValid($otp)) {
            Log::info("OTP verified successfully for {$number}");
            $otpRecord->delete();
            return true;
        }

        Log::warning("Invalid OTP attempt for {$number}");
        return false;
    }

    private static function generateOtp(): string
    {
        return str_pad(
            (string) random_int(0, pow(10, self::OTP_LENGTH) - 1),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }
}