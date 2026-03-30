<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // ── GLOBAL CLOUDINARY CONFIG FIX for Render ──────────────────────
        // Bypasses config caching issues by injecting values at runtime
        try {
            $cloudUrl = env('CLOUDINARY_URL');
            if ($cloudUrl) {
                $parsed = parse_url($cloudUrl);
                config([
                    'cloudinary.cloud' => [
                        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', $parsed['host'] ?? null),
                        'api_key'    => env('CLOUDINARY_API_KEY',    $parsed['user'] ?? null),
                        'api_secret' => env('CLOUDINARY_API_SECRET', $parsed['pass'] ?? null),
                    ],
                    'cloudinary.cloud_url' => $cloudUrl,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Global Cloudinary config injection failed: ' . $e->getMessage());
        }

        // Add CORS headers to all responses
        Response::macro('jsonWithCors', function ($data, $status = 200, array $headers = []) {
            return response()->json($data, $status, $headers)
                ->header('Access-Control-Allow-Origin', 'http://localhost:5173')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true');
        });
    }
}