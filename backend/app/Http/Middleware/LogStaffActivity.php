<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\StaffActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records what employees change (never what they only look at), in words
 * the shop owner understands: "Approved a payroll", "Edited a product: Roses".
 * No IDs or API paths are stored.
 */
class LogStaffActivity
{
    /** Controller@method => [area, action]. Anything else gets a sensible fallback. */
    private const ACTIONS = [
        'AttendanceKioskController@complete' => ['Attendance', 'Timed someone in or out at the shop scanner'],
        'AttendanceKioskController@enroll' => ['Attendance', 'Enrolled a face'],
        'AttendanceKioskController@requestDevice' => ['Attendance', 'Asked to approve a scanner device'],
        'AttendanceController@store' => ['Attendance', 'Asked to add a missing day'],
        'AttendanceController@update' => ['Attendance', 'Asked to correct attendance'],
        'AttendanceController@requestCorrection' => ['Attendance', 'Asked to correct attendance'],
        'AttendanceController@review' => ['Attendance', 'Reviewed a flagged scan'],
        'AttendanceController@reviewCorrection' => ['Attendance', 'Reviewed a correction'],
        'AttendanceController@overtime' => ['Attendance', 'Approved or rejected overtime'],
        'ScheduleController@assign' => ['Schedules', 'Changed someone\'s shift'],
        'ScheduleController@replace' => ['Schedules', 'Had someone cover a shift'],
        'ScheduleController@undoReplace' => ['Schedules', 'Undid a shift cover'],
        'ScheduleController@remove' => ['Schedules', 'Took someone off a shift'],
        'ScheduleController@link' => ['Schedules', 'Linked people to a shift'],
        'ScheduleController@copyWeek' => ['Schedules', 'Copied last week\'s schedule'],
        'ScheduleController@storeTemplate' => ['Schedules', 'Created a shift'],
        'ScheduleController@updateTemplate' => ['Schedules', 'Edited a shift'],
        'ScheduleController@destroyTemplate' => ['Schedules', 'Removed a shift'],
        'AttendanceController@destroy' => ['Attendance', 'Deleted an attendance record'],
        'PayrollController@store' => ['Payroll', 'Created a payroll'],
        'PayrollController@batch' => ['Payroll', 'Created payrolls for several people'],
        'PayrollController@revise' => ['Payroll', 'Revised a payroll'],
        'PayrollController@storeComponent' => ['Payroll', 'Added a recurring allowance or deduction'],
        'PayrollController@updateComponent' => ['Payroll', 'Changed a recurring allowance or deduction'],
        'PayrollController@destroyComponent' => ['Payroll', 'Removed a recurring allowance or deduction'],
        'PayrollController@financeApprove' => ['Payroll', 'Approved payroll for payment'],
        'PayrollController@financeReject' => ['Payroll', 'Rejected a payroll'],
        'PayrollController@markAsPaid' => ['Payroll', 'Marked payroll as paid'],
        'PayrollController@destroy' => ['Payroll', 'Deleted a payroll'],
        'EmployeeLeaveController@updateStatus' => ['Leave', 'Reviewed a leave request'],
        'EmployeeLeaveController@store' => ['Leave', 'Filed leave for someone'],
        'EmployeeLeaveController@cancel' => ['Leave', 'Cancelled a leave'],
        'EmployeeLeaveController@adjust' => ['Leave', 'Adjusted a leave balance'],
        'EmployeeLeaveController@savePolicies' => ['Leave', 'Changed the leave rules'],
        'EmployeeLeaveController@overtimeStore' => ['Leave', 'Filed overtime for someone'],
        'EmployeeLeaveController@overtimeDecide' => ['Leave', 'Reviewed an overtime request'],
        'EmployeeLeaveController@overtimeCancel' => ['Leave', 'Cancelled an overtime request'],
        'EmployeeLeaveController@destroy' => ['Leave', 'Deleted a leave request'],
        'EmployeeInfoController@store' => ['Employees', 'Added an employee profile'],
        'EmployeeInfoController@update' => ['Employees', 'Edited an employee profile'],
        'EmployeeInfoController@updateStatus' => ['Employees', 'Changed an employee\'s status'],
        'EmployeeInfoController@destroy' => ['Employees', 'Removed an employee profile'],
        'ProductController@store' => ['Products', 'Added a product'],
        'ProductController@update' => ['Products', 'Edited a product'],
        'ProductController@destroy' => ['Products', 'Deleted a product'],
        'ProductController@updateStock' => ['Inventory', 'Changed stock'],
        'ProductController@toggleStatus' => ['Products', 'Changed a product\'s visibility'],
        'ProductController@updateStatus' => ['Products', 'Changed a product\'s visibility'],
        'DeliveryController@scan' => ['Deliveries', 'Scanned an order'],
        'DeliveryController@updateStatus' => ['Deliveries', 'Updated a delivery\'s status'],
        'FundingRequestController@store' => ['Funding', 'Requested funding'],
    ];

    private const RETURN_ACTIONS = [
        'accept' => 'Accepted a return / refund request',
        'approve' => 'Accepted a return / refund request',
        'offer' => 'Made an offer on a return / refund request',
        'reject' => 'Declined a return / refund request',
        'message' => 'Messaged a customer about a return / refund',
        'item-received' => 'Received a returned item',
        'refund-sent' => 'Sent a refund',
        'replacement-sent' => 'Sent a replacement',
    ];

    /** Set when logging fails (e.g. before the migration has run) so it isn't retried on every request. */
    private static bool $disabled = false;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (self::$disabled) {
            return $response;
        }

        try {
            $user = $request->user();
            if ($user instanceof Employee
                && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                && $response->getStatusCode() < 400
                && $request->route()) {
                [$area, $action] = $this->describe($request);
                StaffActivityLog::create([
                    'owner_id' => $user->owner_id,
                    'employee_id' => $user->id,
                    'actor_name' => $user->name,
                    'action' => Str::limit($action, 157),
                    'area' => Str::limit($area, 37),
                    'method' => $request->method(),
                    'status' => $response->getStatusCode(),
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            // Logging must never break the request itself
            self::$disabled = true;
            Log::warning('Staff activity log failed: ' . $e->getMessage());
        }

        return $response;
    }

    private function describe(Request $request): array
    {
        $full = $request->route()->getActionName();
        $controller = class_basename(Str::before($full, '@'));
        $method = Str::after($full, '@');
        $key = "{$controller}@{$method}";

        if ($controller === 'OrderRequestController') {
            $verb = $request->route('action') ?? $method;

            return ['Returns & refunds', self::RETURN_ACTIONS[$verb] ?? 'Updated a return / refund request'];
        }

        $known = isset(self::ACTIONS[$key]);
        $fallbackArea = Str::headline(Str::beforeLast($controller, 'Controller'));
        [$area, $action] = self::ACTIONS[$key] ?? [
            $fallbackArea,
            match ($request->method()) {
                'POST' => 'Added to ',
                'DELETE' => 'Removed something from ',
                default => 'Updated ',
            } . Str::lower($fallbackArea),
        ];

        // Name what was changed (a product, a supplier…) when the request carries it; never an ID
        $name = $request->input('product_name') ?? $request->input('supplier_name') ?? $request->input('name');
        $named = ! $known || in_array($area, ['Products', 'Inventory'], true);
        if ($named && is_string($name) && trim($name) !== '' && ! str_contains($action, ':')) {
            $action .= ': ' . Str::limit(trim($name), 60);
        }

        return [$area, $action];
    }
}
