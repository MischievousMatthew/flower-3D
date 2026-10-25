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
        'AccountingFundingRequestController@approve' => ['Funding', 'Approved a funding request'],
        'AccountingFundingRequestController@reject' => ['Funding', 'Rejected a funding request'],

        // Account
        'EmployeeAuthController@logout' => ['Account', 'Signed out'],
        'EmployeeInfoController@updatePhoto' => ['Employees', 'Changed an employee\'s photo'],
        'EmployeeInfoController@removePhoto' => ['Employees', 'Removed an employee\'s photo'],

        // Finance
        'VendorBooksController@storeExpense' => ['Expenses', 'Recorded an expense'],
        'VendorBooksController@updateExpense' => ['Expenses', 'Edited an expense'],
        'VendorBooksController@destroyExpense' => ['Expenses', 'Deleted an expense'],
        'PaymentsController@collect' => ['Payments', 'Recorded a cash-on-delivery collection'],
        'PaymentsController@settle' => ['Payments', 'Settled cash-on-delivery collections'],
        'PaymentsController@undo' => ['Payments', 'Undid a cash-on-delivery collection'],
        'RefundsController@sent' => ['Refunds', 'Recorded a refund as sent'],

        // Customers
        'ChatController@sendMessage' => ['Chat', 'Messaged a customer'],
        'ChatController@startConversation' => ['Chat', 'Started a chat with a customer'],
        'ChatController@archiveConversation' => ['Chat', 'Archived a chat'],
        'ChatController@unarchiveConversation' => ['Chat', 'Moved a chat back to the inbox'],
        'ChatController@deleteConversation' => ['Chat', 'Deleted a chat'],
        'ChatShareController@orderUpdate' => ['Chat', 'Sent a customer an order update'],
        'ChatShareController@shareProduct' => ['Chat', 'Sent a customer a product'],
        'VendorCrmController@answer' => ['Customer questions', 'Answered a customer question'],
        'VendorCrmController@inquiryStatus' => ['Customer questions', 'Updated a customer question'],
        'VendorCrmController@reply' => ['Reviews', 'Replied to a review'],
        'CrmCustomersController@updatePreferences' => ['Customers', 'Updated a customer\'s preferences'],
        'CrmCustomersController@storeDate' => ['Customers', 'Added a customer\'s special day'],
        'CrmCustomersController@destroyDate' => ['Customers', 'Removed a customer\'s special day'],
        'CrmCustomersController@storeNote' => ['Customers', 'Added a note about a customer'],
        'CrmCustomersController@pinNote' => ['Customers', 'Pinned or unpinned a customer note'],
        'CrmCustomersController@destroyNote' => ['Customers', 'Deleted a customer note'],
        'CrmCasesController@store' => ['Support cases', 'Recorded a support case'],
        'CrmCasesController@reply' => ['Support cases', 'Replied to a support case'],
        'CrmCasesController@update' => ['Support cases', 'Updated a support case'],
        'CrmSegmentsController@send' => ['Offers', 'Sent an offer to a customer group'],
        'VendorCrmController@savePreferences' => ['Customers', 'Changed which customer alerts the shop gets'],
        'SupportController@store' => ['Support', 'Asked BloomCraft support for help'],
        'SupportController@reply' => ['Support', 'Replied to BloomCraft support'],
        'SupportController@resolve' => ['Support', 'Closed a support request'],

        // Staff and attendance set-up
        'EmployeeController@store' => ['Employees', 'Added an employee'],
        'EmployeeController@update' => ['Employees', "Edited an employee's details"],
        'EmployeeController@destroy' => ['Employees', 'Removed an employee'],
        'EmployeeController@updateStatus' => ['Employees', "Changed an employee's status"],
        'StaffController@storeEmployee' => ['Employees', 'Added an employee'],
        'StaffController@updateEmployee' => ['Employees', "Edited an employee's details"],
        'StaffController@storeRole' => ['Roles & permissions', 'Created a role'],
        'StaffController@updateRole' => ['Roles & permissions', "Changed a role's permissions"],
        'StaffController@destroyRole' => ['Roles & permissions', 'Deleted a role'],
        'StaffController@storeAttendance' => ['Attendance', 'Added an attendance record'],
        'StaffController@updateAttendance' => ['Attendance', 'Corrected an attendance record'],
        'StaffController@saveShift' => ['Schedules', 'Saved a shift'],
        'StaffController@copyWeek' => ['Schedules', "Copied last week's schedule"],
        'StaffController@destroyShift' => ['Schedules', 'Removed a shift'],
        'AssignmentController@store' => ['Employees', 'Gave an employee a job assignment'],
        'AssignmentController@destroy' => ['Employees', 'Removed a job assignment'],
        'AssignmentController@setPrimary' => ['Employees', "Changed an employee's main job"],
        'EmployeeDocumentController@store' => ['Employees', 'Uploaded an employee document'],
        'EmployeeDocumentController@destroy' => ['Employees', 'Deleted an employee document'],
        'EmployeeQRController@generate' => ['Attendance', "Made an employee's attendance QR code"],
        'EmployeeQRController@reissue' => ['Attendance', "Replaced an employee's attendance QR code"],
        'VendorAttendanceController@reviewCorrection' => ['Attendance', 'Reviewed an attendance correction'],
        'VendorAttendanceController@overtime' => ['Attendance', 'Approved or rejected overtime'],
        'VendorAttendanceController@review' => ['Attendance', 'Reviewed a flagged scan'],
        'VendorAttendanceController@saveSetup' => ['Attendance', 'Changed the attendance settings'],
        'VendorAttendanceController@approveDevice' => ['Attendance', 'Approved a scanner device'],
        'VendorAttendanceController@revokeDevice' => ['Attendance', 'Blocked a scanner device'],
        'VendorAttendanceController@renameDevice' => ['Attendance', 'Renamed a scanner device'],
        'VendorAttendanceController@deleteDevice' => ['Attendance', 'Removed a scanner device'],

        // Orders and the shop
        'VendorOrdersController@updateOrderStatus' => ['Orders', "Updated an order's status"],
        'VendorOrdersController@markDateAsClosed' => ['Orders', 'Closed a date for orders'],
        'VendorOrdersController@removeClosedDate' => ['Orders', 'Opened a closed date again'],
        'VendorCatalogController@bulk' => ['Products', 'Changed several products at once'],
        'VendorCatalogController@discount' => ['Products', "Changed a product's discount"],
        'VendorCatalogController@adjustStock' => ['Inventory', 'Changed stock'],
        'VendorCatalogController@stockLevels' => ['Inventory', 'Changed low-stock alerts'],
        'ProductController@deleteImage' => ['Products', "Removed a product's photo"],
        'ProductController@deleteModel' => ['Products', "Removed a product's 3D model"],
        'VendorCustomizationOptionController@store' => ['Products', 'Added a customization option'],
        'VendorCustomizationOptionController@update' => ['Products', 'Edited a customization option'],
        'VendorCustomizationOptionController@destroy' => ['Products', 'Deleted a customization option'],
        'VendorCustomizationOptionController@setActive' => ['Products', 'Turned a customization option on or off'],
        'VendorBouquetPresetController@update' => ['Products', 'Changed the bouquet presets'],
        'VendorPromotionController@store' => ['Promotions', 'Created a promotion'],
        'VendorPromotionController@update' => ['Promotions', 'Edited a promotion'],
        'VendorPromotionController@destroy' => ['Promotions', 'Deleted a promotion'],
        'VendorPromotionController@toggle' => ['Promotions', 'Turned a promotion on or off'],
        'VendorProfileController@updateGeneralInfo' => ['Shop profile', "Edited the shop's details"],
        'VendorProfileController@updateDeliveryDetails' => ['Shop profile', 'Changed the delivery settings'],
        'VendorProfileController@updatePaymentDetails' => ['Shop profile', 'Changed the payment details'],
        'VendorProfileController@updateProductDetails' => ['Shop profile', 'Changed the product settings'],
        'VendorProfileController@updateStoreLogo' => ['Shop profile', "Changed the shop's logo"],
        'VendorProfileController@deleteStoreLogo' => ['Shop profile', "Removed the shop's logo"],
        'VendorProfileController@updateStoreCover' => ['Shop profile', "Changed the shop's cover photo"],
        'VendorProfileController@deleteStoreCover' => ['Shop profile', "Removed the shop's cover photo"],
        'VendorProfileController@updateShop' => ['Shop profile', "Edited the shop's details"],
        'VendorAccountController@updateShopSettings' => ['Shop profile', 'Changed the shop settings'],

        // Procurement and supply chain
        'FundingRequestController@update' => ['Funding', 'Edited a funding request'],
        'FundingRequestController@destroy' => ['Funding', 'Deleted a funding request'],
        'FundingRequestController@submitToAccounting' => ['Funding', 'Sent a funding request to Finance'],
        'SupplierController@store' => ['Suppliers', 'Added a supplier'],
        'SupplierController@update' => ['Suppliers', 'Edited a supplier'],
        'SupplierController@destroy' => ['Suppliers', 'Deleted a supplier'],
        'SupplierController@activate' => ['Suppliers', 'Marked a supplier as active'],
        'SupplierController@deactivate' => ['Suppliers', 'Marked a supplier as inactive'],
        'SupplierController@blacklist' => ['Suppliers', 'Blocked a supplier'],
        'SupplierController@storeContact' => ['Suppliers', 'Added a supplier contact'],
        'SupplierController@updateContact' => ['Suppliers', 'Edited a supplier contact'],
        'SupplierController@destroyContact' => ['Suppliers', 'Deleted a supplier contact'],
        'OrderController@store' => ['Supplier orders', 'Created a supplier order'],
        'OrderController@createFromFunding' => ['Supplier orders', 'Created a supplier order from a funding request'],
        'OrderController@update' => ['Supplier orders', 'Edited a supplier order'],
        'OrderController@destroy' => ['Supplier orders', 'Deleted a supplier order'],
        'OrderController@attachItems' => ['Supplier orders', 'Added items to a supplier order'],
        'OrderController@removeItem' => ['Supplier orders', 'Removed an item from a supplier order'],
        'OrderController@recalculateTotals' => ['Supplier orders', "Recalculated a supplier order's total"],
        'OrderController@updateStatus' => ['Supplier orders', "Updated a supplier order's status"],
        'ShipmentController@store' => ['Shipments', 'Added a shipment'],
        'ShipmentController@update' => ['Shipments', 'Edited a shipment'],
        'ShipmentController@destroy' => ['Shipments', 'Deleted a shipment'],
        'ShipmentController@markShipped' => ['Shipments', 'Marked a shipment as sent'],
        'ShipmentController@markReceived' => ['Shipments', 'Marked a shipment as received'],
        'ShipmentController@updateStatus' => ['Shipments', "Updated a shipment's status"],
        'ShipmentController@updateTracking' => ['Shipments', "Updated a shipment's tracking"],
        'WarehouseController@store' => ['Warehouse', 'Added a warehouse'],
        'WarehouseController@update' => ['Warehouse', 'Edited a warehouse'],
        'WarehouseController@destroy' => ['Warehouse', 'Deleted a warehouse'],
        'WarehouseController@addItem' => ['Warehouse', 'Added an item to the warehouse'],
        'WarehouseController@updateItem' => ['Warehouse', 'Edited a warehouse item'],
        'WarehouseController@adjustStock' => ['Warehouse', 'Changed warehouse stock'],
        'WarehouseBatchController@receive' => ['Warehouse', 'Received a batch of flowers'],
        'WarehouseBatchController@updateCondition' => ['Warehouse', "Updated a batch's condition"],
        'WarehouseBatchController@cull' => ['Warehouse', 'Threw out damaged or wilted stems'],
        'WarehouseBatchController@transfer' => ['Warehouse', 'Moved a batch to another spot'],
        'WarehouseLocationController@store' => ['Warehouse', 'Added a storage spot'],
        'WarehouseLocationController@update' => ['Warehouse', 'Edited a storage spot'],
        'WarehouseLocationController@destroy' => ['Warehouse', 'Deleted a storage spot'],
        'WarehouseLocationController@toggle' => ['Warehouse', 'Turned a storage spot on or off'],
    ];

    /**
     * Requests that change nothing the owner cares about (previews, marking
     * notifications read, looking up a code). Logging them would only add noise.
     */
    private const IGNORED = [
        'PayrollController@preview',
        'PayrollController@batchPreview',
        'NotificationController@markRead',
        'NotificationController@markAllRead',
        'AttendanceKioskController@start',
        'AttendanceKioskController@status',
        'BarcodeController@scan',
        'BarcodeController@generate',
        'AttendanceController@retired',
    ];

    /** Plain area names for actions without their own wording, instead of internal code names */
    public const AREAS = [
        'EmployeeAuth' => 'Account',
        'Auth' => 'Account',
        'Profile' => 'Account',
        'VendorAccount' => 'Account',
        'VendorBooks' => 'Expenses',
        'AccountingFundingRequest' => 'Funding',
        'FundingRequest' => 'Funding',
        'Employee' => 'Employees',
        'EmployeeInfo' => 'Employees',
        'EmployeeDocument' => 'Employees',
        'EmployeeQR' => 'Attendance',
        'Assignment' => 'Employees',
        'EmployeeLeave' => 'Leave',
        'Staff' => 'Employees',
        'VendorAttendance' => 'Attendance',
        'AttendanceKiosk' => 'Attendance',
        'Schedule' => 'Schedules',
        'VendorCatalog' => 'Products',
        'Product' => 'Products',
        'VendorCustomizationOption' => 'Products',
        'VendorBouquetPreset' => 'Products',
        'VendorPromotion' => 'Promotions',
        'VendorProfile' => 'Shop profile',
        'VendorOrders' => 'Orders',
        'VendorCrm' => 'Customers',
        'ChatShare' => 'Chat',
        'Order' => 'Supplier orders',
        'Supplier' => 'Suppliers',
        'Shipment' => 'Shipments',
        'WarehouseBatch' => 'Warehouse',
        'WarehouseLocation' => 'Warehouse',
        'Delivery' => 'Deliveries',
        'CrmCustomers' => 'Customers',
        'CrmCases' => 'Support cases',
        'CrmSegments' => 'Offers',
        'Notification' => 'Notifications',
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
                && $request->route()
                && ! $this->ignored($request)) {
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

    /** Whether this request is one the log should skip */
    private function ignored(Request $request): bool
    {
        $full = $request->route()->getActionName();

        return in_array(class_basename(Str::before($full, '@')) . '@' . Str::after($full, '@'), self::IGNORED, true);
    }

    /** "VendorBooksController" reads as "Expenses"; anything unlisted reads as words, minus "Vendor" */
    public static function areaFor(string $controller): string
    {
        $base = Str::beforeLast($controller, 'Controller');

        return self::AREAS[$base] ?? Str::ucfirst(Str::lower(Str::headline(Str::after($base, 'Vendor') ?: $base)));
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
        $fallbackArea = self::areaFor($controller);
        [$area, $action] = self::ACTIONS[$key] ?? [
            $fallbackArea,
            ($request->method() === 'DELETE' ? 'Removed something in ' : 'Made a change in ') . Str::lower($fallbackArea),
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
