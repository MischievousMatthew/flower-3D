<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LoginAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class LoginAuditService
{
    public function logUserLogin(User $user, Request $request): void
    {
        $context = $this->normalizeContext($request);

        LoginAuditLog::create([
            'user_id' => $user->id,
            'owner_id' => $this->resolveUserOwnerId($user),
            'actor_type' => $user->role === User::ROLE_VENDOR ? 'vendor' : ($user->role === User::ROLE_ADMIN ? 'admin' : 'customer'),
            'actor_name' => trim($user->name . ' ' . ($user->surname ?? '')) ?: $user->username,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_name' => $context['device_name'],
            'browser' => $context['browser'],
            'platform' => $context['platform'],
            'location_label' => $context['location_label'],
            'latitude' => $context['latitude'],
            'longitude' => $context['longitude'],
            'location_accuracy' => $context['location_accuracy'],
            'timezone' => $context['timezone'],
            'logged_in_at' => now(),
        ]);
    }

    public function logEmployeeLogin(Employee $employee, Request $request): void
    {
        $context = $this->normalizeContext($request);

        LoginAuditLog::create([
            'employee_id' => $employee->id,
            'owner_id' => $employee->owner_id,
            'actor_type' => 'employee',
            'actor_name' => $employee->name,
            'username' => $employee->username,
            'email' => $employee->email,
            'role' => $employee->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_name' => $context['device_name'],
            'browser' => $context['browser'],
            'platform' => $context['platform'],
            'location_label' => $context['location_label'],
            'latitude' => $context['latitude'],
            'longitude' => $context['longitude'],
            'location_accuracy' => $context['location_accuracy'],
            'timezone' => $context['timezone'],
            'logged_in_at' => now(),
        ]);
    }

    private function normalizeContext(Request $request): array
    {
        $context = (array) $request->input('login_context', []);

        $latitude = isset($context['latitude']) && is_numeric($context['latitude'])
            ? (float) $context['latitude']
            : null;
        $longitude = isset($context['longitude']) && is_numeric($context['longitude'])
            ? (float) $context['longitude']
            : null;

        $locationLabel = $context['location_label'] ?? null;
        if (!$locationLabel && $latitude !== null && $longitude !== null) {
            $locationLabel = sprintf('%.5f, %.5f', $latitude, $longitude);
        }

        return [
            'device_name' => $context['device_name'] ?? null,
            'browser' => $context['browser'] ?? null,
            'platform' => $context['platform'] ?? null,
            'location_label' => $locationLabel,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_accuracy' => isset($context['location_accuracy']) && is_numeric($context['location_accuracy'])
                ? (int) $context['location_accuracy']
                : null,
            'timezone' => $context['timezone'] ?? null,
        ];
    }

    private function resolveUserOwnerId(User $user): ?int
    {
        if (!empty($user->owner_id)) {
            return (int) $user->owner_id;
        }

        if ($user->role === User::ROLE_VENDOR) {
            return $user->id;
        }

        return null;
    }
}
