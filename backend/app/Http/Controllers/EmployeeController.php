<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Role;
use App\Models\EmployeeAssignment;
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
                ->with(['owner:id,name,email', 'activeAssignments.department', 'activeAssignments.role'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($employee) {
                    $employeeArray = $employee->toArray();
                    // Fallback helpers for the frontend table View
                    $primary = $employee->activeAssignments->where('is_primary', true)->first() 
                               ?? $employee->activeAssignments->first();
                    $employeeArray['department'] = $primary && $primary->department ? $primary->department->name : '-';
                    $employeeArray['role'] = $primary && $primary->role ? $primary->role->name : '-';
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
                'assignments'  => 'required|array|min:1',
                'assignments.*.department' => 'required|string',
                'assignments.*.role'       => 'required|string',
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
            ]);

            // Track IDs of processed assignments to verify success
            $assignedCount = 0;

            foreach ($request->assignments as $index => $assignmentData) {
                // Look up by name
                $department = Department::where('name', $assignmentData['department'])->first();
                if (!$department) continue;

                $role = Role::where('department_id', $department->id)
                            ->where('name', $assignmentData['role'])
                            ->first();
                if (!$role) continue;

                EmployeeAssignment::create([
                    'employee_id'   => $employee->id,
                    'department_id' => $department->id,
                    'role_id'       => $role->id,
                    'is_primary'    => ($index === 0), // First one is primary
                    'is_active'     => true,
                ]);

                $assignedCount++;
            }

            if ($assignedCount === 0) {
                // Rollback if no valid assignments were provided
                $employee->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employee: Invalid department or role'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $employee->load(['owner', 'activeAssignments.department', 'activeAssignments.role']),
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
                'assignments'  => 'nullable|array|min:1',
                'assignments.*.department' => 'required_with:assignments|string',
                'assignments.*.role'       => 'required_with:assignments|string',
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

            $employee->update($data);

            // Sync assignments if provided
            if ($request->has('assignments')) {
                // Clear existing active assignments
                $employee->activeAssignments()->delete();

                foreach ($request->assignments as $index => $assignmentData) {
                    $department = Department::where('name', $assignmentData['department'])->first();
                    if (!$department) continue;
    
                    $role = Role::where('department_id', $department->id)
                                ->where('name', $assignmentData['role'])
                                ->first();
                    if (!$role) continue;
    
                    EmployeeAssignment::create([
                        'employee_id'   => $employee->id,
                        'department_id' => $department->id,
                        'role_id'       => $role->id,
                        'is_primary'    => ($index === 0), // First one is primary
                        'is_active'     => true,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $employee->fresh()->load(['owner', 'activeAssignments.department', 'activeAssignments.role']),
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