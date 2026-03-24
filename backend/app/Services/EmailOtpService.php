<?php

namespace App\Services;

use App\Models\EmailOtp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
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

    public static function send(string $email, string $ip = null): void
    {
        // 1. Per-email cooldown (60 s)
        $cooldownKey = "otp_cooldown:email:{$email}";
        if (Cache::has($cooldownKey)) {
            throw new Exception('Please wait 60 seconds before requesting another OTP.');
        }

        // 2. Per-email hourly cap (3 / hr)
        $hourlyKey = "otp_hourly:email:{$email}";
        $hourlyCount = Cache::get($hourlyKey, 0);
        if ($hourlyCount >= self::MAX_PER_HOUR) {
            throw new Exception('Too many OTP requests for this email. Try again later.');
        }

        // 3. Per-IP hourly cap (10 / hr across all emails)
        if ($ip) {
            $ipKey = "otp_hourly:ip:{$ip}";
            $ipCount = Cache::get($ipKey, 0);
            if ($ipCount >= self::MAX_PER_HOUR_IP) {
                throw new Exception('Too many OTP requests from your connection. Try again later.');
            }
            Cache::put($ipKey, $ipCount + 1, now()->addHour());
        }

        // 4. Generate, hash, persist
        $plain = str_pad((string) random_int(0, 10 ** self::OTP_LENGTH - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        EmailOtp::updateOrCreate(
            ['email' => $email],
            [
                'otp_hash'   => Hash::make($plain),
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'attempts'   => 0,
                'is_locked'  => false,
                'ip_address' => $ip,
            ]
        );

        // 5. Set cooldown + increment hourly counter
        Cache::put($cooldownKey, true, now()->addSeconds(self::COOLDOWN_SECONDS));
        Cache::put($hourlyKey,   $hourlyCount + 1, now()->addHour());

        // 6. Send email (queued)
        Mail::to($email)->queue(new OtpMail($plain, self::OTP_EXPIRY_MINUTES));

        Log::info("Email OTP dispatched", ['email' => $email, 'ip' => $ip]);
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