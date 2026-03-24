<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Home route after authentication
     */
    public const HOME = '/home';

    /**
     * API prefix versioning
     */
    public const API_PREFIX = 'api/v1';

    /**
     * Boot routes and rate limiting
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {

            /*
            |--------------------------------------------------------------------------
            | API Routes
            |--------------------------------------------------------------------------
            */
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix(self::API_PREFIX)
                ->group(base_path('routes/api.php'));

            /*
            |--------------------------------------------------------------------------
            | Web Routes
            |--------------------------------------------------------------------------
            */
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure API Rate Limits
     */
    protected function configureRateLimiting(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Default API Rate Limit
        |--------------------------------------------------------------------------
        | Authenticated Users: 120 requests/minute
        | Guests:              30 requests/minute
        */
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        /*
        |--------------------------------------------------------------------------
        | Barcode Scanning Endpoints
        |--------------------------------------------------------------------------
        | Higher frequency operations
        */
        RateLimiter::for('barcode', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?? $request->ip()
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Analytics Endpoints
        |--------------------------------------------------------------------------
        | Heavy queries → lower limit
        */
        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(20)->by(
                $request->user()?->id ?? $request->ip()
            );
        });
    }
}