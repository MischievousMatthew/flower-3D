<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginAuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $query = LoginAuditLog::query()->orderByDesc('logged_in_at');

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($inner) use ($search) {
                $inner->where('actor_name', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('ip_address', 'like', $search)
                    ->orWhere('device_name', 'like', $search)
                    ->orWhere('browser', 'like', $search)
                    ->orWhere('platform', 'like', $search)
                    ->orWhere('location_label', 'like', $search);
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
