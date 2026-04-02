<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Role;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeModulePermission;
use App\Constants\ErpModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ScopesOwner;

class EmployeeController extends Controller
{
    use ScopesOwner;
    /**
     * Get all employees for the authenticated owner
     */
    public function index(Request $request)
    {
        try {
            $ownerId = $this->getOwnerId();

            $employees = Employee::byOwner($ownerId)
                ->with(['owner:id,name,email', 'modulePermissions'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($employee) {
                    $employeeArray = $employee->toArray();
                    // Flatten module_permissions for the frontend table
                    $employeeArray['module_permissions'] = $employee->modulePermissions->map(fn ($p) => [
                        'module' => $p->module,
                        'access' => $p->access,
                    ])->toArray();
                    return $employeeArray;
                });

            return response()->json([
                'success' => true,
                'data' => $employees,
                'message' => 'Employees retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching employees: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get employee statistics
     */
    public function statistics(Request $request)
    {
        try {
            $ownerId = $this->getOwnerId();

            $total = Employee::byOwner($ownerId)->count();
            $active = Employee::byOwner($ownerId)->active()->count();
            $onLeave = Employee::byOwner($ownerId)->onLeave()->count();
            $resigned = Employee::byOwner($ownerId)->resigned()->count();

            // Recent hires (last 30 days) - select all columns
            $recentHires = Employee::byOwner($ownerId)
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Recent resignations
            $resignations = Employee::byOwner($ownerId)
                ->resigned()
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            // Employees on leave
            $onLeaveList = Employee::byOwner($ownerId)
                ->onLeave()
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            // New joins this month
            $newJoins = Employee::byOwner($ownerId)
                ->whereMonth('joining_date', now()->month)
                ->whereYear('joining_date', now()->year)
                ->orderBy('joining_date', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $total,
                    'active_employees' => $active,
                    'on_leave' => $onLeave,
                    'resigned' => $resigned,
                    'recent_hires' => $recentHires,
                    'resignations' => $resignations,
                    'on_leave_list' => $onLeaveList,
                    'new_joins' => $newJoins,
                ],
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching employee statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a new employee
     */
    public function store(Request $request)
    {
        try {
            $ownerId = $this->getOwnerId();

            $validator = Validator::make($request->all(), [
                'name'         => 'required|string|max:255',
                'email'        => 'required|email|unique:employees,email',
                'username'     => 'required|string|unique:employees,username|max:50',
                'password'     => 'required|string|min:8',
                'joining_date' => 'required|date',
                'status'       => 'required|in:Active,On Leave,Resign',
                'phone'        => 'nullable|string|max:20',
                'address'      => 'nullable|string|max:500',
                'department'   => 'nullable|string|max:255',
                'role'         => 'nullable|string|max:255',
                'permissions'             => 'required|array|min:1',
                'permissions.*.module'    => 'required|string|' . ErpModule::validKeysRule(),
                'permissions.*.access'    => 'required|string|' . ErpModule::validAccessRule(),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors()
                ], 422);
            }

            $employee = Employee::create([
                'owner_id'     => $ownerId,
                'name'         => $request->name,
                'email'        => $request->email,
                'username'     => $request->username,
                'password'     => Hash::make($request->password),
                'joining_date' => $request->joining_date,
                'status'       => $request->status,
                'phone'        => $request->phone,
                'address'      => $request->address,
                'department'   => $request->department,
                'role'         => $request->role,
            ]);

            // Create module permissions
            $createdCount = 0;
            $seenModules = [];

            foreach ($request->permissions as $perm) {
                // Skip duplicates within the request
                if (in_array($perm['module'], $seenModules, true)) continue;
                $seenModules[] = $perm['module'];

                EmployeeModulePermission::create([
                    'employee_id' => $employee->id,
                    'module'      => $perm['module'],
                    'access'      => $perm['access'],
                ]);
                $createdCount++;
            }

            if ($createdCount === 0) {
                $employee->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employee: No valid permissions provided'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $employee->load(['owner', 'modulePermissions']),
                'message' => 'Employee added successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error adding employee: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add employee',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Show a single employee
     */
    public function show(Request $request, $id)
    {
        try {
            $ownerId = $this->getOwnerId();

            $employee = Employee::byOwner($ownerId)
                ->where('id', $id)
                ->with(['owner:id,name,email', 'activeAssignments.department', 'activeAssignments.role'])
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $employee,
                'message' => 'Employee retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching employee: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update an employee
     */
    public function update(Request $request, $id)
    {
        try {
            $ownerId = $this->getOwnerId();

            $employee = Employee::byOwner($ownerId)
                ->where('id', $id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name'         => 'nullable|string|max:255',
                'email'        => 'nullable|email|unique:employees,email,' . $id,
                'username'     => 'nullable|string|unique:employees,username,' . $id . '|max:50',
                'password'     => 'nullable|string|min:8',
                'joining_date' => 'nullable|date',
                'status'       => 'nullable|in:Active,On Leave,Resign',
                'phone'        => 'nullable|string|max:20',
                'address'      => 'nullable|string|max:500',
                'department'   => 'nullable|string|max:255',
                'role'         => 'nullable|string|max:255',
                'permissions'             => 'nullable|array|min:1',
                'permissions.*.module'    => 'required_with:permissions|string|' . ErpModule::validKeysRule(),
                'permissions.*.access'    => 'required_with:permissions|string|' . ErpModule::validAccessRule(),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            
            // Handle password update
            if (isset($data['password']) && $data['password']) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Remove permissions from $data so it doesn't get sent to Employee::update()
            unset($data['permissions']);

            $employee->update($data);

            // Sync module permissions if provided
            if ($request->has('permissions')) {
                // Clear old permissions
                $employee->modulePermissions()->delete();

                $seenModules = [];
                foreach ($request->permissions as $perm) {
                    if (in_array($perm['module'], $seenModules, true)) continue;
                    $seenModules[] = $perm['module'];

                    EmployeeModulePermission::create([
                        'employee_id' => $employee->id,
                        'module'      => $perm['module'],
                        'access'      => $perm['access'],
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $employee->fresh()->load(['owner', 'modulePermissions']),
                'message' => 'Employee updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating employee: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update employee status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $ownerId = $this->getOwnerId();

            $employee = Employee::byOwner($ownerId)
                ->where('id', $id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:Active,On Leave,Resign',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee->status = $request->status;
            $employee->save();

            return response()->json([
                'success' => true,
                'data' => $employee,
                'message' => 'Employee status updated to ' . $employee->status
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating employee status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete an employee
     */
    public function destroy(Request $request, $id)
    {
        try {
            $ownerId = $this->getOwnerId();

            $employee = Employee::byOwner($ownerId)
                ->where('id', $id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $employee->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting employee: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search employees
     */
    public function search(Request $request)
    {
        try {
            $owner = $request->user();

            $validator = Validator::make($request->all(), [
                'search'        => 'nullable|string|max:255',
                'status'        => 'nullable|in:Active,On Leave,Resign',
                'department_id' => 'nullable|integer|exists:departments,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors()
                ], 422);
            }

            $ownerId = $this->getOwnerId();

            $query = Employee::byOwner($ownerId);

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('department_id')) {
                $query->whereHas('activeAssignments', fn ($q) =>
                    $q->where('department_id', $request->department_id)
                );
            }

            $employees = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $employees,
                'message' => 'Search results retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching employees: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search employees',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
