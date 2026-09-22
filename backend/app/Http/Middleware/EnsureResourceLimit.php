<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use App\Services\ResourceLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResourceLimit
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $actor = $request->user();
        $vendor = $actor instanceof Employee ? $actor->owner : $actor;

        if (! $vendor instanceof User || ! $vendor->isVendor()) {
            return $next($request);
        }

        $limits = app(ResourceLimitService::class);
        if (! $limits->canCreateResource($vendor, $resource)) {
            $summary = $limits->summary($vendor)[$resource];
            return response()->json([
                'success' => false,
                'code' => 'resource_limit_reached',
                'message' => "{$summary['label']} limit reached.",
                'detail' => $summary['message'],
                'resource' => $resource,
                'limit' => $summary['limit'],
                'usage' => $summary['usage'],
            ], 422);
        }

        return $next($request);
    }
}
