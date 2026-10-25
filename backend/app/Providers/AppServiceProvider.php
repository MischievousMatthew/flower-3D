<?php

namespace App\Providers;

use App\Support\FriendlyErrors;
use App\Support\FriendlyValidator;
use App\Support\PlatformSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Sign-in and sign-up limits per IP address, each with its own count
     * (a plain "throttle:6,1" shares one count with every other plain throttle,
     * so typing a username used up the sign-up code limit).
     */
    private const AUTH_LIMITS = [
        // The login form makes two calls per try (staff, then customer)
        'auth-login' => [40, 1], // replaced by the admin setting, see maxFor()
        'auth-two-factor' => [10, 1],
        'auth-register' => [10, 1],
        'auth-signup-code' => [6, 1],
        'auth-availability' => [30, 1],
        'auth-forgot' => [5, 1],
        'auth-reset' => [10, 1],
        // Applications carry several files, so far fewer
        'vendor-apply' => [10, 60],
    ];

    public function boot()
    {
        // Error messages use plain field names ("employee", not "employee id")
        Validator::resolver(fn ($translator, $data, $rules, $messages, $attributes) => new FriendlyValidator($translator, $data, $rules, $messages, $attributes));

        foreach (self::AUTH_LIMITS as $name => [$max, $minutes]) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinutes($minutes, $this->maxFor($name, $max))
                ->by($name . '|' . $request->ip())
                ->response(fn ($request, array $headers) => response()->json([
                    'success' => false,
                    'code' => 'too_many_requests',
                    'message' => FriendlyErrors::forStatus(429),
                    'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                ], 429, $headers)));
        }
    }

    /** Sign-in tries a minute come from Security → Sign-in protection; the rest are fixed */
    private function maxFor(string $name, int $default): int
    {
        if ($name !== 'auth-login') {
            return $default;
        }

        // Each try is two calls (staff sign-in, then customer sign-in)
        return 2 * max(10, (int) PlatformSettings::get('login_ip_per_minute'));
    }
}
