<?php

namespace App\Services;

use App\Models\EmailOtp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Six-digit codes sent by email, for signing up and for resetting a password.
 * One live code per email address: a signed-up email can't sign up again, so
 * the two purposes never compete for the same address.
 */
class EmailOtpService
{
    public const PURPOSE_SIGNUP = 'signup';
    public const PURPOSE_RESET = 'reset';
    public const PURPOSE_SIGNIN = 'signin';
    public const PURPOSE_VERIFY = 'verify';

    private const OTP_EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const COOLDOWN_SECONDS = 60;
    private const MAX_PER_HOUR = 5;

    /**
     * @throws OtpProblem when the person has to wait, or the email couldn't be sent
     */
    public static function send(string $email, string $ip, string $purpose = self::PURPOSE_SIGNUP): void
    {
        $email = strtolower(trim($email));
        $key = hash('sha256', $email);
        $cooldownKey = "email_otp:cooldown:{$key}";
        $hourlyKey = "email_otp:hourly:{$key}";

        if ($until = Cache::get($cooldownKey)) {
            $wait = max(1, (int) $until - now()->timestamp);
            throw new OtpProblem("We just sent a code. You can ask for another in {$wait} seconds.", 429, $wait);
        }
        if (Cache::get($hourlyKey, 0) >= self::MAX_PER_HOUR) {
            throw new OtpProblem('Too many codes were requested for this email. Please try again in an hour.', 429, 3600);
        }

        $otp = (string) random_int(100000, 999999);
        self::deliver($email, $otp, $purpose);

        EmailOtp::updateOrCreate(
            ['email' => $email],
            [
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'attempts' => 0,
                'is_locked' => false,
                'ip_address' => $ip,
            ],
        );

        Cache::put($cooldownKey, now()->addSeconds(self::COOLDOWN_SECONDS)->timestamp, now()->addSeconds(self::COOLDOWN_SECONDS));
        Cache::put($hourlyKey, Cache::get($hourlyKey, 0) + 1, now()->addHour());
    }

    /**
     * Checks a code and uses it up.
     *
     * @throws OtpProblem when the code is missing, wrong, expired or locked
     */
    public static function verify(string $email, string $plain): bool
    {
        $email = strtolower(trim($email));
        $record = EmailOtp::where('email', $email)->first();

        if (! $record) {
            throw new OtpProblem('That code has expired or was never sent. Ask for a new one.');
        }
        if ($record->is_locked) {
            throw new OtpProblem('Too many wrong codes. Ask for a new one.');
        }
        if ($record->isExpired()) {
            $record->delete();
            throw new OtpProblem('That code has expired. Ask for a new one.');
        }

        $record->incrementAttempts(self::MAX_ATTEMPTS);

        if (! Hash::check($plain, $record->otp_hash)) {
            $remaining = max(0, self::MAX_ATTEMPTS - $record->fresh()->attempts);
            throw new OtpProblem($remaining > 0
                ? "That code isn't right. {$remaining} " . ($remaining === 1 ? 'try' : 'tries') . ' left.'
                : 'Too many wrong codes. Ask for a new one.');
        }

        $record->delete(); // single use

        return true;
    }

    private static function deliver(string $email, string $otp, string $purpose): void
    {
        $apiKey = config('services.brevo.key');
        if (! $apiKey) {
            Log::error('Email codes: the Brevo API key is missing');
            throw new OtpProblem("We couldn't send the email right now. Please try again in a few minutes.", 503);
        }

        [$subject, $lead] = match ($purpose) {
            self::PURPOSE_RESET => ["Reset your BloomCraft password", "Use this code to choose a new password. If you didn't ask for it, you can ignore this email: your password stays the same."],
            self::PURPOSE_SIGNIN => ["Your BloomCraft sign-in code", "Use this code to finish signing in. If it wasn't you, change your password right away."],
            self::PURPOSE_VERIFY => ["Confirm your BloomCraft security change", "Use this code to confirm the change to your account's security settings."],
            default => ["Your BloomCraft sign-up code", "Use this code to finish creating your BloomCraft account."],
        };

        $response = Http::withHeaders(['api-key' => $apiKey, 'Content-Type' => 'application/json'])
            ->timeout(20)
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => ['name' => config('mail.from.name'), 'email' => config('mail.from.address')],
                'to' => [['email' => $email]],
                'subject' => $subject,
                'htmlContent' => self::html($otp, $lead),
                'textContent' => "{$lead}\n\nYour code: {$otp}\n\nIt expires in " . self::OTP_EXPIRY_MINUTES . ' minutes.',
            ]);

        if (! $response->successful()) {
            Log::error('Email codes: Brevo refused the email', ['status' => $response->status(), 'body' => $response->body()]);
            throw new OtpProblem("We couldn't send the email right now. Please try again in a few minutes.", 503);
        }
    }

    private static function html(string $otp, string $lead): string
    {
        $minutes = self::OTP_EXPIRY_MINUTES;
        $digits = implode('', array_map(fn ($d) => "<span style=\"display:inline-block;width:40px;margin:0 3px;padding:12px 0;border-radius:10px;background:#f4f1ec;font:600 26px/1 Arial,sans-serif;color:#1e1b18\">{$d}</span>", str_split($otp)));

        return <<<HTML
<div style="background:#f4f1ec;padding:32px 12px;font-family:Arial,sans-serif">
  <div style="max-width:460px;margin:0 auto;background:#ffffff;border-radius:18px;padding:32px 28px;text-align:center">
    <p style="margin:0 0 6px;font:600 12px/1 Arial,sans-serif;letter-spacing:.2em;text-transform:uppercase;color:#8e443a">BloomCraft</p>
    <h1 style="margin:0 0 14px;font:500 26px/1.2 Georgia,serif;color:#1e1b18">Your code</h1>
    <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#6e6761">{$lead}</p>
    <div style="margin:0 0 24px">{$digits}</div>
    <p style="margin:0;font-size:12px;color:#6e6761">It expires in {$minutes} minutes. Never share it with anyone — BloomCraft will never ask for it.</p>
  </div>
</div>
HTML;
    }
}
