<?php

namespace App\Http\Controllers;

use App\Models\EmployeeInfo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Helpers\CloudinaryHelper;
use Illuminate\Support\Facades\Validator;
use App\Models\Employee;

use App\Traits\ScopesOwner;

class EmployeeInfoController extends Controller
{
    use ScopesOwner;
    /**
     * Display a listing of employee info records.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $ownerId = $this->getOwnerId();
            
            Log::info('Loading employees for user:', [
                'user_id' => $user->id,
                'owner_id' => $ownerId,
                'department' => $user->department ?? null,
            ]);

            $query = EmployeeInfo::forOwner($ownerId);

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('status')) {
                $query->byStatus($request->status);
            }

            if ($request->filled('department')) {
                $query->byDepartment($request->department);
            }

            if ($request->filled('location')) {
                $query->byLocation($request->location);
            }

            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->input('per_page', 15);
            $employees = $query->paginate($perPage);
            
            Log::info('Found employees:', ['count' => $employees->count()]);

            return response()->json([
                'success' => true,
                'data' => $employees->items(),
                'pagination' => [
                    'total' => $employees->total(),
                    'per_page' => $employees->perPage(),
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'from' => $employees->firstItem(),
                    'to' => $employees->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching employee info records: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee records',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created employee info record.
     */
    public function store(Request $request): JsonResponse
    {
        // ✅ UPDATED VALIDATION RULES
        $rules = [
            // Basic Information
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|in:Male,Female,Other',
            'date_of_birth' => 'required|date|before:today',
            'civil_status' => 'required|in:Single,Married,Divorced,Widowed',
            
            // Contact Information
            'personal_email' => 'required|email|max:150',
            'work_email' => 'required|email|max:150',
            'mobile_number' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            
            // Emergency Contact
            'emergency_contact_name' => 'required|string|max:150',
            'emergency_contact_number' => 'required|string|max:20',
            'emergency_relationship' => 'required|string|max:100',
            
            // Employment Details
            'employment_status' => 'required|in:Probationary,Regular,Contractual,Part-time,Active,Inactive',
            'position' => 'required|string|max:150',
            'department' => 'required|string|max:150',
            'employment_type' => 'required|in:Full-time,Part-time',
            'date_hired' => 'required|date',
            'work_location' => 'required|string|max:200',
            'reporting_manager' => 'required|string|max:150',
            
            // Work Schedule
            'work_schedule' => 'required|in:Fixed,Shifting',
            'shift_start' => ['required', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'shift_end' => ['required', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'rest_days' => 'required|string|max:100',
            
            // ✅ NEW: Payroll Configuration
            'standard_work_hours_per_day' => 'nullable|numeric|min:0.01|max:24',
            'working_days_per_week' => 'nullable|integer|min:1|max:7',
            'working_days_per_month' => 'nullable|integer|min:1|max:31',
            
            // ✅ FIXED: Payroll Information - lowercase enum values
            'basic_salary' => 'nullable|numeric|min:0|max:999999999.99',
            'salary_type' => 'nullable|in:daily,weekly,monthly', // ✅ Changed to lowercase
            'payment_method' => 'nullable|in:Bank,Cash',
            'bank_name' => 'nullable|string|max:150',
            'account_number' => 'nullable|string|max:50',
            'tax_status' => 'nullable|string|max:20',
            'allowance' => 'nullable|numeric|min:0|max:999999999.99',
        ];

        if ($request->hasFile('avatar')) {
            $rules['avatar'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';
        }

        $validator = Validator::make($request->all(), $rules);

        // ✅ ADDED: Conditional validation for salary type
        $validator->after(function ($validator) use ($request) {
            // Shift time validation
            if ($request->shift_start && $request->shift_end) {
                $startSeconds = strtotime($request->shift_start);
                $endSeconds = strtotime($request->shift_end);
                
                if ($endSeconds <= $startSeconds) {
                    $validator->errors()->add('shift_end', 'The shift end must be after shift start.');
                }
            }

            // Payroll configuration validation
            if ($request->filled('salary_type')) {
                if (!$request->filled('basic_salary')) {
                    $validator->errors()->add('basic_salary', 'Basic salary is required when salary type is specified.');
                }
                if (!$request->filled('standard_work_hours_per_day')) {
                    $validator->errors()->add('standard_work_hours_per_day', 'Standard work hours per day is required when salary type is specified.');
                }

                // Weekly salary requires working_days_per_week
                if ($request->salary_type === 'weekly' && !$request->filled('working_days_per_week')) {
                    $validator->errors()->add('working_days_per_week', 'Working days per week is required for weekly salary type.');
                }

                // Monthly salary requires working_days_per_month
                if ($request->salary_type === 'monthly' && !$request->filled('working_days_per_month')) {
                    $validator->errors()->add('working_days_per_month', 'Working days per month is required for monthly salary type.');
                }
            }
        });

        if ($validator->fails()) {
            Log::error('Validation Failed:', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except(['avatar']),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            $uploadedAvatarId = null;

            $user = $request->user();
            $ownerId = $this->getOwnerId();
            $creatorEmployeeId = $this->resolveCreatorEmployeeId($user, $ownerId);

            $userDepartment = strtolower(trim($user->department ?? ''));
            $allowedDepartments = ['hr', 'human resources'];
            
            $hasHRAccess = false;
            foreach ($allowedDepartments as $dept) {
                if (str_contains($userDepartment, $dept) || str_contains($dept, $userDepartment)) {
                    $hasHRAccess = true;
                    break;
                }
            }
            
            if (!$hasHRAccess) {
                Log::warning('HR Access Denied:', [
                    'user_department' => $user->department,
                    'allowed_departments' => $allowedDepartments,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Only HR department staff can create employee records'
                ], 403);
            }

            $avatarPath = null;
            if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
                $result = CloudinaryHelper::uploadImage(
                    $request->file('avatar'),
                    'avatars'
                );
                $avatarPath = $result['public_id'];
                $uploadedAvatarId = $avatarPath;
                Log::info('Avatar stored successfully to Cloudinary:', ['path' => $avatarPath]);
            }

            $employeeId = $this->generateEmployeeId($ownerId);

            $shiftStart = $request->shift_start;
            $shiftEnd = $request->shift_end;
            
            if ($shiftStart && substr_count($shiftStart, ':') === 1) {
                $shiftStart = $shiftStart . ':00';
            }
            
            if ($shiftEnd && substr_count($shiftEnd, ':') === 1) {
                $shiftEnd = $shiftEnd . ':00';
            }

            // ✅ UPDATED: Create with new fields
            $employeeInfoData = [
                'owner_id' => $ownerId,
                'employee_id' => $employeeId,
                
                // Basic Information
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'civil_status' => $request->civil_status,
                'avatar' => $avatarPath,
                
                // Contact Information
                'personal_email' => $request->personal_email,
                'work_email' => $request->work_email,
                'mobile_number' => $request->mobile_number,
                'address' => $request->address,
                
                // Emergency Contact
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_number' => $request->emergency_contact_number,
                'emergency_relationship' => $request->emergency_relationship,
                
                // Employment Details
                'employment_status' => $request->employment_status,
                'position' => $request->position,
                'department' => $request->department,
                'employment_type' => $request->employment_type,
                'date_hired' => $request->date_hired,
                'work_location' => $request->work_location,
                'reporting_manager' => $request->reporting_manager,
                
                // Work Schedule
                'work_schedule' => $request->work_schedule,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'rest_days' => $request->rest_days,
                
                // ✅ NEW: Payroll Configuration
                'standard_work_hours_per_day' => $request->standard_work_hours_per_day ?? 8.00,
                'working_days_per_week' => $request->working_days_per_week ?? 5,
                'working_days_per_month' => $request->working_days_per_month ?? 22,
                
                // Payroll Information
                'basic_salary' => $request->basic_salary,
                'salary_type' => $request->salary_type,
                'payment_method' => $request->payment_method,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'tax_status' => $request->tax_status,
                'allowance' => $request->allowance,
            ];

            if (Schema::hasColumn('employees_info', 'created_by_employee_id') && $creatorEmployeeId !== null) {
                $employeeInfoData['created_by_employee_id'] = $creatorEmployeeId;
            }

            $employeeInfoData = $this->filterExistingEmployeeInfoColumns($employeeInfoData);
            $employeeInfo = EmployeeInfo::create($employeeInfoData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee record created successfully',
                'data' => $employeeInfo
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            if (!empty($uploadedAvatarId)) {
                CloudinaryHelper::destroy($uploadedAvatarId);
            }
            
            Log::error('Error creating employee info: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee record',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified employee info record.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $ownerId = $this->getOwnerId();
            $employeeInfo = EmployeeInfo::forOwner($ownerId)->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $employeeInfo
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching employee info: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee record'
            ], 500);
        }
    }

    /**
     * Update the specified employee info record.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // ✅ SAME UPDATED VALIDATION RULES AS store()
        $rules = [
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|in:Male,Female,Other',
            'date_of_birth' => 'required|date|before:today',
            'civil_status' => 'required|in:Single,Married,Divorced,Widowed',
            'personal_email' => 'required|email|max:150',
            'work_email' => 'required|email|max:150',
            'mobile_number' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'emergency_contact_name' => 'required|string|max:150',
            'emergency_contact_number' => 'required|string|max:20',
            'emergency_relationship' => 'required|string|max:100',
            'employment_status' => 'required|in:Probationary,Regular,Contractual,Part-time,Active,Inactive',
            'position' => 'required|string|max:150',
            'department' => 'required|string|max:150',
            'employment_type' => 'required|in:Full-time,Part-time',
            'date_hired' => 'required|date',
            'work_location' => 'required|string|max:200',
            'reporting_manager' => 'required|string|max:150',
            'work_schedule' => 'required|in:Fixed,Shifting',
            'shift_start' => ['required', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'shift_end' => ['required', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'rest_days' => 'required|string|max:100',
            'standard_work_hours_per_day' => 'nullable|numeric|min:0.01|max:24',
            'working_days_per_week' => 'nullable|integer|min:1|max:7',
            'working_days_per_month' => 'nullable|integer|min:1|max:31',
            'basic_salary' => 'nullable|numeric|min:0|max:999999999.99',
            'salary_type' => 'nullable|in:daily,weekly,monthly', // ✅ lowercase
            'payment_method' => 'nullable|in:Bank,Cash',
            'bank_name' => 'nullable|string|max:150',
            'account_number' => 'nullable|string|max:50',
            'tax_status' => 'nullable|string|max:20',
            'allowance' => 'nullable|numeric|min:0|max:999999999.99',
        ];

        if ($request->hasFile('avatar')) {
            $rules['avatar'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            if ($request->shift_start && $request->shift_end) {
                $startSeconds = strtotime($request->shift_start);
                $endSeconds = strtotime($request->shift_end);
                
                if ($endSeconds <= $startSeconds) {
                    $validator->errors()->add('shift_end', 'The shift end must be after shift start.');
                }
            }

            if ($request->filled('salary_type')) {
                if (!$request->filled('basic_salary')) {
                    $validator->errors()->add('basic_salary', 'Basic salary is required when salary type is specified.');
                }
                if (!$request->filled('standard_work_hours_per_day')) {
                    $validator->errors()->add('standard_work_hours_per_day', 'Standard work hours per day is required when salary type is specified.');
                }

                if ($request->salary_type === 'weekly' && !$request->filled('working_days_per_week')) {
                    $validator->errors()->add('working_days_per_week', 'Working days per week is required for weekly salary type.');
                }

                if ($request->salary_type === 'monthly' && !$request->filled('working_days_per_month')) {
                    $validator->errors()->add('working_days_per_month', 'Working days per month is required for monthly salary type.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            $uploadedAvatarId = null;

            $ownerId = $this->getOwnerId();
            $user = $request->user();
            $creatorEmployeeId = $this->resolveCreatorEmployeeId($user, $ownerId);
            $userDepartment = strtolower(trim($user->department ?? ''));
            $allowedDepartments = ['hr', 'human resources'];
            
            $hasHRAccess = false;
            foreach ($allowedDepartments as $dept) {
                if (str_contains($userDepartment, $dept) || str_contains($dept, $userDepartment)) {
                    $hasHRAccess = true;
                    break;
                }
            }
            
            if (!$hasHRAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only HR department staff can update employee records'
                ], 403);
            }

            $employeeInfo = EmployeeInfo::forOwner($ownerId)->findOrFail($id);
            $previousAvatar = $employeeInfo->avatar;

            $avatarPath = null;
            if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
                $result = CloudinaryHelper::uploadImage(
                    $request->file('avatar'),
                    'avatars'
                );
                $avatarPath = $result['public_id'];
                $uploadedAvatarId = $avatarPath;
                Log::info('Avatar uploaded successfully to Cloudinary for update:', ['path' => $avatarPath]);
            }

            $shiftStart = $request->shift_start;
            $shiftEnd = $request->shift_end;
            
            if ($shiftStart && substr_count($shiftStart, ':') === 1) {
                $shiftStart = $shiftStart . ':00';
            }
            
            if ($shiftEnd && substr_count($shiftEnd, ':') === 1) {
                $shiftEnd = $shiftEnd . ':00';
            }

            // ✅ UPDATED: Include new fields
            $updateData = [
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'civil_status' => $request->civil_status,
                'personal_email' => $request->personal_email,
                'work_email' => $request->work_email,
                'mobile_number' => $request->mobile_number,
                'address' => $request->address,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_number' => $request->emergency_contact_number,
                'emergency_relationship' => $request->emergency_relationship,
                'employment_status' => $request->employment_status,
                'position' => $request->position,
                'department' => $request->department,
                'employment_type' => $request->employment_type,
                'date_hired' => $request->date_hired,
                'work_location' => $request->work_location,
                'reporting_manager' => $request->reporting_manager,
                'work_schedule' => $request->work_schedule,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'rest_days' => $request->rest_days,
                
                // ✅ NEW: Payroll Configuration
                'standard_work_hours_per_day' => $request->standard_work_hours_per_day,
                'working_days_per_week' => $request->working_days_per_week,
                'working_days_per_month' => $request->working_days_per_month,
                
                'basic_salary' => $request->basic_salary,
                'salary_type' => $request->salary_type,
                'payment_method' => $request->payment_method,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'tax_status' => $request->tax_status,
                'allowance' => $request->allowance,
            ];

            if ($avatarPath) {
                $updateData['avatar'] = $avatarPath;
            }

            if (Schema::hasColumn('employees_info', 'created_by_employee_id') && $creatorEmployeeId !== null) {
                $updateData['created_by_employee_id'] = $creatorEmployeeId;
            }

            $updateData = $this->filterExistingEmployeeInfoColumns($updateData);

            $employeeInfo->update($updateData);

            if (
                $avatarPath &&
                $previousAvatar &&
                !str_starts_with($previousAvatar, 'http') &&
                $previousAvatar !== $avatarPath
            ) {
                CloudinaryHelper::destroy($previousAvatar);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee record updated successfully',
                'data' => $employeeInfo
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            if (!empty($uploadedAvatarId)) {
                CloudinaryHelper::destroy($uploadedAvatarId);
            }
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            if (!empty($uploadedAvatarId)) {
                CloudinaryHelper::destroy($uploadedAvatarId);
            }
            
            Log::error('Error updating employee info: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee record',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update only the employment status of an employee.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employment_status' => 'required|in:Probationary,Regular,Contractual,Part-time,Active,Inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ownerId = $this->getOwnerId();

            $employeeInfo = EmployeeInfo::forOwner($ownerId)->findOrFail($id);
            $employeeInfo->employment_status = $request->employment_status;
            $employeeInfo->save();

            return response()->json([
                'success' => true,
                'message' => 'Employment status updated successfully',
                'data' => $employeeInfo
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating employment status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment status'
            ], 500);
        }
    }

    /**
     * Remove the specified employee info record (soft delete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $ownerId = $this->getOwnerId();

            $employeeInfo = EmployeeInfo::forOwner($ownerId)->findOrFail($id);
            $employeeInfo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee record deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting employee info: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee record'
            ], 500);
        }
    }

    /**
     * Generate a unique Employee ID for the given owner.
     */
    private function generateEmployeeId(int $ownerId): string
    {
        $lastEmployee = EmployeeInfo::forOwner($ownerId)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastEmployee && $lastEmployee->employee_id) {
            preg_match('/EMP-(\d+)/', $lastEmployee->employee_id, $matches);
            
            if (isset($matches[1])) {
                $lastNumber = (int) $matches[1];
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 10001;
            }
        } else {
            $newNumber = 10001;
        }

        return 'EMP-' . $newNumber;
    }

    private function filterExistingEmployeeInfoColumns(array $data): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = array_flip(Schema::getColumnListing('employees_info'));
        }

        return array_filter(
            $data,
            fn ($value, $key) => isset($columns[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function resolveCreatorEmployeeId($user, int $ownerId): ?int
    {
        if ($user instanceof Employee) {
            return $user->id;
        }

        if (!$user) {
            return null;
        }

        $employeeId = Employee::withoutGlobalScope('owner')
            ->where('owner_id', $ownerId)
            ->where(function ($query) use ($user) {
                $query->where('email', $user->email)
                    ->orWhere('username', $user->username ?? null);
            })
            ->value('id');

        return $employeeId ? (int) $employeeId : null;
    }

    /**
     * Get statistics for employee info records.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $ownerId = $this->getOwnerId();

            $total = EmployeeInfo::forOwner($ownerId)->count();
            $probationary = EmployeeInfo::forOwner($ownerId)->byStatus('Probationary')->count();
            $regular = EmployeeInfo::forOwner($ownerId)->byStatus('Regular')->count();
            $contractual = EmployeeInfo::forOwner($ownerId)->byStatus('Contractual')->count();
            $partTime = EmployeeInfo::forOwner($ownerId)->byStatus('Part-time')->count();

            $departments = EmployeeInfo::forOwner($ownerId)
                ->select('department', DB::raw('count(*) as count'))
                ->groupBy('department')
                ->get();

            $locations = EmployeeInfo::forOwner($ownerId)
                ->select('work_location', DB::raw('count(*) as count'))
                ->groupBy('work_location')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $total,
                    'by_status' => [
                        'probationary' => $probationary,
                        'regular' => $regular,
                        'contractual' => $contractual,
                        'part_time' => $partTime,
                    ],
                    'by_department' => $departments,
                    'by_location' => $locations,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching employee statistics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }
}
