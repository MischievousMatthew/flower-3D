<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EmployeeAuthController extends Controller
{
    /**
     * POST /auth/employee-login
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $employee = Employee::where('username', $request->username)
                ->orWhere('email', $request->username)
                ->where('status', 'Active')
                ->first();

            if (!$employee || !Hash::check($request->password, $employee->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials or account inactive',
                ], 401);
            }

            // Revoke old tokens
            $employee->tokens()->delete();

            // Create a clean Sanctum token — no stale role/department abilities
            $token = $employee->createToken('employee-token')->plainTextToken;

            // Load assignments to return on login
            $assignments = $employee->activeAssignments()
                ->with(['department', 'role.permissions'])
                ->get()
                ->map(fn ($a) => [
                    'id'          => $a->id,
                    'label'       => $a->label,
                    'is_primary'  => $a->is_primary,
                    'department'  => [
                        'id'   => $a->department->id,
                        'name' => $a->department->name,
                        'slug' => $a->department->slug,
                    ],
                    'role'        => [
                        'id'      => $a->role->id,
                        'name'    => $a->role->name,
                        'slug'    => $a->role->slug,
                        'level'   => $a->role->hierarchy_level,
                        'modules' => $a->role->accessible_modules,
                    ],
                    'permissions' => $a->role->permissions->pluck('slug'),
                ]);

            $primary = $assignments->firstWhere('is_primary', true);

            Log::info('Employee login successful', [
                'employee_id' => $employee->id,
                'username'    => $employee->username,
                'assignments' => $assignments->count(),
            ]);

            return response()->json([
                'success'           => true,
                'message'           => 'Login successful',
                'token'             => $token,
                'token_type'        => 'Bearer',
                'employee'          => [
                    'id'           => $employee->id,
                    'name'         => $employee->name,
                    'email'        => $employee->email,
                    'username'     => $employee->username,
                    'status'       => $employee->status,
                    'initials'     => $employee->initials,
                    'joining_date' => $employee->formatted_joining_date,
                    'assignments'  => $assignments,
                ],
                'primary_assignment' => $primary,
            ]);

        } catch (\Throwable $e) {
            Log::error('Employee login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }

    /**
     * POST /auth/employee-logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * GET /auth/employee-me
     */
    public function me(Request $request): JsonResponse
    {
        $employee = $request->user();

        if (!$employee instanceof Employee) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $assignments = $employee->activeAssignments()
            ->with(['department', 'role.permissions'])
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'label'       => $a->label,
                'is_primary'  => $a->is_primary,
                'department'  => [
                    'id'   => $a->department->id,
                    'name' => $a->department->name,
                    'slug' => $a->department->slug,
                ],
                'role'        => [
                    'id'      => $a->role->id,
                    'name'    => $a->role->name,
                    'slug'    => $a->role->slug,
                    'level'   => $a->role->hierarchy_level,
                    'modules' => $a->role->accessible_modules,
                ],
                'permissions' => $a->role->permissions->pluck('slug'),
            ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Employee data retrieved successfully',
            'employee' => [
                'id'           => $employee->id,
                'name'         => $employee->name,
                'email'        => $employee->email,
                'username'     => $employee->username,
                'status'       => $employee->status,
                'initials'     => $employee->initials,
                'joining_date' => $employee->formatted_joining_date,
                'assignments'  => $assignments,
            ],
        ]);
    }
}
