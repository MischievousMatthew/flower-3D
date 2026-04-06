<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Constants\ErpModule;
use App\Services\LoginAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeeAuthController extends Controller
{
    public function __construct(private readonly LoginAuditService $loginAuditService)
    {
    }

    /**
     * POST /auth/employee-login
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'login_context' => 'nullable|array',
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

            $token = Str::random(80);
            $employee->forceFill([
                'api_token' => hash('sha256', $token),
            ])->save();
            $this->loginAuditService->logEmployeeLogin($employee, $request);

            // Load module permissions
            $modulePermissions = $employee->modulePermissions()
                ->get()
                ->map(fn ($p) => [
                    'module' => $p->module,
                    'access' => $p->access,
                ]);

            // Compute default route from first module permission
            $defaultRoute = '/erp/dashboard';
            if ($modulePermissions->isNotEmpty()) {
                $firstModule = $modulePermissions->first()['module'];
                // Use a simple mapping — the frontend has the full ERP_MODULES constant
                $defaultRoute = $this->getModuleRoute($firstModule);
            }

            Log::info('Employee login successful', [
                'employee_id' => $employee->id,
                'username'    => $employee->username,
                'modules'     => $modulePermissions->count(),
            ]);

            return response()->json([
                'success'           => true,
                'message'           => 'Login successful',
                'token'             => $token,
                'token_type'        => 'Bearer',
                'employee'          => [
                    'id'                 => $employee->id,
                    'name'               => $employee->name,
                    'email'              => $employee->email,
                    'username'           => $employee->username,
                    'status'             => $employee->status,
                    'initials'           => $employee->initials,
                    'joining_date'       => $employee->formatted_joining_date,
                    'module_permissions' => $modulePermissions,
                    'default_route'      => $defaultRoute,
                ],
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

        if ($user instanceof Employee) {
            $user->forceFill([
                'api_token' => null,
            ])->save();
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

        $modulePermissions = $employee->modulePermissions()
            ->get()
            ->map(fn ($p) => [
                'module' => $p->module,
                'access' => $p->access,
            ]);

        $defaultRoute = '/erp/dashboard';
        if ($modulePermissions->isNotEmpty()) {
            $defaultRoute = $this->getModuleRoute($modulePermissions->first()['module']);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Employee data retrieved successfully',
            'employee' => [
                'id'                 => $employee->id,
                'name'               => $employee->name,
                'email'              => $employee->email,
                'username'           => $employee->username,
                'status'             => $employee->status,
                'initials'           => $employee->initials,
                'joining_date'       => $employee->formatted_joining_date,
                'module_permissions' => $modulePermissions,
                'default_route'      => $defaultRoute,
            ],
        ]);
    }

    /**
     * Get the default landing route for a module key.
     */
    private function getModuleRoute(string $moduleKey): string
    {
        $routes = [
            'hr_dashboard'       => '/erp/hr',
            'employees'          => '/erp/hr/employees/directory',
            'attendance'         => '/erp/hr/attendance/logs',
            'payroll'            => '/erp/hr/payroll/list',
            'leave'              => '/erp/hr/leave/management-requests',
            'leave_request'      => '/erp/hr/leave/employee-request',
            'leave_management'   => '/erp/hr/leave/management-requests',
            'finance_dashboard'  => '/erp/finance/dashboard',
            'funding_requests'   => '/erp/finance/funding-requests',
            'payroll_requests'   => '/erp/finance/payroll-requests',
            'crm'                => '/erp/crm/chat',
            'inventory_products' => '/erp/procurement/inventory/products',
            'inventory_funding'  => '/erp/procurement/inventory/funding-request',
            'sc_dashboard'       => '/erp/procurement/supply-chain/dashboard',
            'suppliers'          => '/erp/procurement/supply-chain/suppliers',
            'warehouse'          => '/erp/procurement/supply-chain/warehouse',
            'sc_orders'          => '/erp/procurement/supply-chain/orders',
            'deliveries'         => '/erp/procurement/supply-chain/deliveries',
            'order_scan'         => '/erp/procurement/supply-chain/scan/process',
        ];

        return $routes[$moduleKey] ?? '/erp/dashboard';
    }
}
