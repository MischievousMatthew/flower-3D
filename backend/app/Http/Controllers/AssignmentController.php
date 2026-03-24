<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * GET /vendor/employees/{id}/assignments
     * List all active department-role assignments for an employee.
     */
    public function index(Request $request, int $employeeId): JsonResponse
    {
        $owner    = $request->user();
        $employee = Employee::byOwner($owner->id)->findOrFail($employeeId);

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

        return response()->json(['success' => true, 'data' => $assignments]);
    }

    /**
     * POST /vendor/employees/{id}/assignments
     * Attach a new department-role to an employee.
     */
    public function store(Request $request, int $employeeId): JsonResponse
    {
        $owner    = $request->user();
        $employee = Employee::byOwner($owner->id)->findOrFail($employeeId);

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'role_id'       => 'required|exists:roles,id',
            'is_primary'    => 'boolean',
        ]);

        // Verify the role belongs to the department
        $role = Role::where('id', $validated['role_id'])
                    ->where('department_id', $validated['department_id'])
                    ->firstOrFail();

        // If marking as primary, clear previous primary
        if ($validated['is_primary'] ?? false) {
            $employee->assignments()->update(['is_primary' => false]);
        }

        // If this is the first assignment, make it primary automatically
        $isFirst   = $employee->activeAssignments()->count() === 0;
        $isPrimary = ($validated['is_primary'] ?? false) || $isFirst;

        $assignment = EmployeeAssignment::create([
            'employee_id'   => $employee->id,
            'department_id' => $validated['department_id'],
            'role_id'       => $validated['role_id'],
            'is_primary'    => $isPrimary,
            'is_active'     => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $assignment->load(['department', 'role.permissions']),
            'message' => 'Assignment created',
        ], 201);
    }

    /**
     * PATCH /vendor/employees/{id}/assignments/{assignmentId}/set-primary
     * Promote a specific assignment to primary.
     */
    public function setPrimary(Request $request, int $employeeId, int $assignmentId): JsonResponse
    {
        $owner    = $request->user();
        $employee = Employee::byOwner($owner->id)->findOrFail($employeeId);
        $assignment = $employee->activeAssignments()->findOrFail($assignmentId);

        // Clear all existing primary flags first
        $employee->assignments()->update(['is_primary' => false]);
        $assignment->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Primary assignment updated',
            'data'    => $assignment->fresh()->load(['department', 'role']),
        ]);
    }

    /**
     * DELETE /vendor/employees/{id}/assignments/{assignmentId}
     */
    public function destroy(Request $request, int $employeeId, int $assignmentId): JsonResponse
    {
        $owner      = $request->user();
        $employee   = Employee::byOwner($owner->id)->findOrFail($employeeId);
        $assignment = $employee->assignments()->findOrFail($assignmentId);

        if ($employee->activeAssignments()->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the last assignment',
            ], 422);
        }

        $wasPrimary = $assignment->is_primary;
        $assignment->delete();

        // Auto-promote another assignment to primary if we removed the primary
        if ($wasPrimary) {
            $employee->activeAssignments()->first()?->update(['is_primary' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Assignment removed']);
    }

    /**
     * GET /vendor/departments
     * List all active departments with their roles (for dropdowns).
     */
    public function departments(Request $request): JsonResponse
    {
        $departments = \App\Models\Department::where('is_active', true)
            ->with('roles')
            ->get()
            ->map(fn ($d) => [
                'id'    => $d->id,
                'name'  => $d->name,
                'slug'  => $d->slug,
                'roles' => $d->roles->map(fn ($r) => [
                    'id'      => $r->id,
                    'name'    => $r->name,
                    'slug'    => $r->slug,
                    'level'   => $r->hierarchy_level,
                    'modules' => $r->accessible_modules,
                ]),
            ]);

        return response()->json(['success' => true, 'data' => $departments]);
    }
}
