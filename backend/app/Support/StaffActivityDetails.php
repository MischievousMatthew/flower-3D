<?php

namespace App\Support;

use App\Models\LeavePolicy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The specific version of a staff activity entry: what exactly was done and
 * to whom. "Took Badet off their Thu, Aug 7 shift (8:00 AM – 5:00 PM)"
 * instead of "Took someone off a shift".
 *
 * It runs before the action, so a record about to be deleted can still be
 * named. Names come from the database or the request; never IDs.
 */
class StaffActivityDetails
{
    private const REPLACE_REASONS = ['no_show' => "didn't show up", 'sick' => 'is sick', 'swap' => 'swapped shifts', 'other' => 'other reason'];

    /** @return array{0: string, 1: ?string}|null [sentence, who or what was affected] */
    public static function describe(string $key, Request $r): ?array
    {
        $d = new self($r);
        $id = $r->route('id');

        return match ($key) {
            // ── Schedules ──
            'ScheduleController@assign', 'StaffController@saveShift' => $d->assignShift(),
            'ScheduleController@remove', 'StaffController@destroyShift' => $d->shift($id, fn ($s, $who, $when) => $s->is_off
                ? ["Removed {$who}'s day off on {$when}", $who]
                : ["Took {$who} off their {$when} shift" . $d->hours($s->start_time, $s->end_time), $who]),
            'ScheduleController@replace' => $d->shift($id, function ($s, $who, $when) use ($d, $r) {
                $cover = $d->person($r->input('employee_id'));
                $why = self::REPLACE_REASONS[$r->input('reason')] ?? null;

                return ["Had {$cover} cover {$who}'s {$when} shift" . ($why ? " ({$who} {$why})" : ''), $who];
            }),
            'ScheduleController@undoReplace' => $d->shift($id, fn ($s, $who, $when) => ["Undid the cover on {$who}'s {$when} shift; {$who} works it again", $who]),
            'ScheduleController@copyWeek', 'StaffController@copyWeek' => ['Copied the schedule of the week of ' . $d->day($r->input('week')) . ' into the next week', null],
            'ScheduleController@storeTemplate' => ['Created the shift "' . $d->text($r->input('name')) . '"' . $d->hours($r->input('start_time'), $r->input('end_time')), null],
            'ScheduleController@updateTemplate' => $d->named('shift_templates', $id, 'name', fn ($n) => ["Edited the shift \"{$n}\"" . $d->hours($r->input('start_time'), $r->input('end_time')), null]),
            'ScheduleController@destroyTemplate' => $d->named('shift_templates', $id, 'name', fn ($n) => ["Removed the shift \"{$n}\"", null]),
            'ScheduleController@link' => $d->named('shift_templates', $id, 'name', fn ($n) => [
                'Put ' . $d->people($r->input('employee_ids', [])) . " on the \"{$n}\" shift from " . $d->day($r->input('from')) . ' to ' . $d->day($r->input('to')),
                $d->people($r->input('employee_ids', [])),
            ]),

            // ── Attendance ──
            'AttendanceController@store', 'StaffController@storeAttendance' => $d->withPerson($r->input('employee_id'), fn ($who) => [
                "Added {$who}'s attendance for " . $d->day($r->input('date')) . ': in ' . $d->time($r->input('time_in')) . ($r->filled('time_out') ? ', out ' . $d->time($r->input('time_out')) : ''),
                $who,
            ]),
            'AttendanceController@update', 'StaffController@updateAttendance' => $d->attendance($id, fn ($a, $who, $when) => [
                "Corrected {$who}'s attendance on {$when}: in " . $d->time($r->input('time_in')) . ($r->filled('time_out') ? ', out ' . $d->time($r->input('time_out')) : '')
                    . ' (was ' . $d->time($a->time_in) . ($a->time_out ? '–' . $d->time($a->time_out) : '') . ')',
                $who,
            ]),
            'AttendanceController@destroy' => $d->attendance($id, fn ($a, $who, $when) => ["Deleted {$who}'s attendance on {$when}", $who]),
            'AttendanceController@review', 'VendorAttendanceController@review' => $d->attendance($id, fn ($a, $who, $when) => [
                ($r->input('decision') === 'reject' ? 'Rejected' : 'Accepted') . " {$who}'s flagged scan on {$when}", $who,
            ]),
            'AttendanceController@overtime', 'VendorAttendanceController@overtime' => $d->attendance($id, fn ($a, $who, $when) => $r->input('decision') === 'reject'
                ? ["Rejected {$who}'s overtime on {$when}", $who]
                : ['Approved ' . $d->minutes($r->input('minutes') ?? $a->overtime_minutes) . " of overtime for {$who} on {$when}", $who]),
            'AttendanceController@requestCorrection' => $d->withPerson($r->input('employee_id') ?? $r->input('employee_info_id'), fn ($who) => [
                "Asked to correct {$who}'s attendance on " . $d->day($r->input('date')), $who,
            ]),
            'AttendanceController@reviewCorrection', 'VendorAttendanceController@reviewCorrection' => $d->row('attendance_corrections', $id, fn ($c) => $d->withPerson($c->employee_info_id, fn ($who) => [
                ($r->input('decision') === 'reject' ? 'Rejected' : 'Approved') . " the correction to {$who}'s attendance on " . $d->day($c->date)
                    . ($c->time_in ? ' (' . $d->time($c->time_in) . ($c->time_out ? '–' . $d->time($c->time_out) : '') . ')' : ''),
                $who,
            ])),
            'EmployeeQRController@generate' => $d->withPerson($r->route('employeeId'), fn ($who) => ["Made {$who}'s attendance QR code", $who]),
            'EmployeeQRController@reissue' => $d->withPerson($r->route('employeeId'), fn ($who) => ["Replaced {$who}'s attendance QR code (the old one stops working)", $who]),
            'AttendanceKioskController@complete', 'AttendanceKioskController@enroll' => $d->row('attendance_sessions', $id, fn ($s) => $d->withPerson($s->employee_info_id, fn ($who) => [
                $key === 'AttendanceKioskController@enroll' ? "Enrolled {$who}'s face at the shop scanner" : "Timed {$who} " . ($s->purpose === 'time_out' ? 'out' : 'in') . ' at the shop scanner',
                $who,
            ])),
            'VendorAttendanceController@approveDevice' => $d->named('attendance_devices', $id, 'name', fn ($n) => ["Approved the scanner device \"{$n}\"", null]),
            'VendorAttendanceController@revokeDevice' => $d->named('attendance_devices', $id, 'name', fn ($n) => ["Blocked the scanner device \"{$n}\"", null]),
            'VendorAttendanceController@deleteDevice' => $d->named('attendance_devices', $id, 'name', fn ($n) => ["Removed the scanner device \"{$n}\"", null]),
            'VendorAttendanceController@renameDevice' => $d->named('attendance_devices', $id, 'name', fn ($n) => ["Renamed the scanner device \"{$n}\" to \"" . $d->text($r->input('name')) . '"', null]),

            // ── Leave and overtime requests ──
            'EmployeeLeaveController@store' => $d->withPerson($r->input('employee_id'), fn ($who) => [
                'Filed ' . $d->leaveType($r->input('leave_type')) . " for {$who}, " . $d->span($r->input('start_date'), $r->input('end_date')), $who,
            ]),
            'EmployeeLeaveController@updateStatus' => $d->leave($id, fn ($l, $who) => [
                ($r->input('status') === 'rejected' ? 'Rejected' : 'Approved') . " {$who}'s " . $d->leaveType($l->leave_type) . ', ' . $d->span($l->start_date, $l->end_date), $who,
            ]),
            'EmployeeLeaveController@cancel' => $d->leave($id, fn ($l, $who) => ["Cancelled {$who}'s " . $d->leaveType($l->leave_type) . ', ' . $d->span($l->start_date, $l->end_date), $who]),
            'EmployeeLeaveController@destroy' => $d->leave($id, fn ($l, $who) => ["Deleted {$who}'s " . $d->leaveType($l->leave_type) . ' request, ' . $d->span($l->start_date, $l->end_date), $who]),
            'EmployeeLeaveController@adjust' => $d->withPerson($r->input('employee_id'), function ($who) use ($d, $r) {
                $days = (float) $r->input('days');

                return [($days > 0 ? 'Added ' : 'Took away ') . $d->count(abs($days), 'day') . ' of ' . $d->leaveType($r->input('leave_type')) . ($days > 0 ? ' to ' : ' from ') . "{$who}'s {$r->input('year')} balance"
                    . ($r->filled('note') ? ' ("' . $d->text($r->input('note'), 60) . '")' : ''), $who];
            }),
            'EmployeeLeaveController@overtimeStore' => $d->withPerson($r->input('employee_id'), fn ($who) => [
                'Filed ' . $d->minutes($r->input('minutes')) . " of overtime for {$who} on " . $d->day($r->input('date')), $who,
            ]),
            'EmployeeLeaveController@overtimeDecide', 'EmployeeLeaveController@overtimeCancel' => $d->row('overtime_requests', $id, fn ($o) => $d->withPerson($o->employee_info_id, fn ($who) => [
                ($key === 'EmployeeLeaveController@overtimeCancel' ? 'Cancelled' : ($r->input('status') === 'rejected' ? 'Rejected' : 'Approved'))
                    . " {$who}'s " . $d->minutes($o->minutes) . ' overtime request for ' . $d->day($o->date), $who,
            ])),
            'EmployeeLeaveController@savePolicies' => ['Changed the leave rules (yearly days per leave type)', null],

            // ── Payroll ──
            'PayrollController@store' => $d->withPerson($r->input('employee_id'), fn ($who) => [
                "Created {$who}'s payroll for " . $d->span($r->input('period_start'), $r->input('period_end')), $who,
            ]),
            'PayrollController@batch' => [
                'Created payrolls for ' . $d->people($r->input('employee_ids', [])) . ', ' . $d->span($r->input('period_start'), $r->input('period_end')),
                $d->people($r->input('employee_ids', [])),
            ],
            'PayrollController@revise' => $d->payrolls([$id], fn ($list, $who) => ["Revised {$list}", $who]),
            'PayrollController@destroy' => $d->payrolls([$id], fn ($list, $who) => ["Deleted {$list}", $who]),
            'PayrollController@financeApprove' => $d->payrolls($r->input('ids', []), fn ($list, $who) => ["Approved for payment: {$list}", $who]),
            'PayrollController@financeReject' => $d->payrolls($r->input('ids', []), fn ($list, $who) => ["Rejected {$list}" . ($r->filled('finance_notes') ? ' ("' . $d->text($r->input('finance_notes'), 60) . '")' : ''), $who]),
            'PayrollController@markAsPaid' => $d->payrolls($r->input('ids', []), fn ($list, $who) => ["Marked as paid: {$list}", $who]),
            'PayrollController@storeComponent' => $d->withPerson($r->input('employee_info_id'), fn ($who) => [
                'Gave ' . $who . ' a recurring ' . ($r->input('kind') === 'deduction' ? 'deduction' : 'allowance') . ' "' . $d->text($r->input('label')) . '" of ' . $d->money($r->input('amount'))
                    . ($r->input('basis') === 'per_day' ? ' a day' : ' each payroll'), $who,
            ]),
            'PayrollController@updateComponent', 'PayrollController@destroyComponent' => $d->row('pay_components', $id, fn ($c) => $d->withPerson($c->employee_info_id, fn ($who) => [
                ($key === 'PayrollController@destroyComponent' ? 'Removed' : 'Changed') . " {$who}'s recurring {$c->kind} \"{$c->label}\""
                    . ($key === 'PayrollController@updateComponent' && $r->filled('amount') ? ' to ' . $d->money($r->input('amount')) : ' (' . $d->money($c->amount) . ')'), $who,
            ])),

            // ── Employees, accounts and roles ──
            'EmployeeInfoController@store' => ['Added an HR profile for ' . $d->text(trim($r->input('first_name') . ' ' . $r->input('last_name'))) . $d->suffix($r->input('position'), ', '), $d->text(trim($r->input('first_name') . ' ' . $r->input('last_name')))],
            'EmployeeInfoController@update' => $d->withPerson($id, fn ($who) => ["Edited {$who}'s HR profile", $who]),
            'EmployeeInfoController@updateStatus' => $d->withPerson($id, fn ($who) => ["Changed {$who}'s employment status to " . $d->text($r->input('employment_status') ?? $r->input('status')), $who]),
            'EmployeeInfoController@destroy' => $d->withPerson($id, fn ($who) => ["Removed {$who}'s HR profile", $who]),
            'EmployeeInfoController@updatePhoto' => $d->withPerson($id, fn ($who) => ["Changed {$who}'s photo", $who]),
            'EmployeeInfoController@removePhoto' => $d->withPerson($id, fn ($who) => ["Removed {$who}'s photo", $who]),
            'EmployeeDocumentController@store' => $d->withPerson($id, fn ($who) => ['Uploaded "' . $d->text($r->input('title') ?? $r->input('category')) . "\" to {$who}'s documents", $who]),
            'EmployeeDocumentController@destroy' => $d->withPerson($id, fn ($who) => $d->named('employee_documents', $r->route('documentId'), 'title', fn ($t) => ["Deleted \"{$t}\" from {$who}'s documents", $who]) ?? ["Deleted a document from {$who}'s documents", $who]),
            'StaffController@storeEmployee', 'EmployeeController@store' => ['Added a team account for ' . $d->text($r->input('name')) . $d->suffix($r->input('email'), ' (', ')'), $d->text($r->input('name'))],
            'StaffController@updateEmployee', 'EmployeeController@update' => $d->withAccount($id, fn ($who) => [
                "Edited {$who}'s team account" . ($r->filled('password') ? ' and set a new password' : '') . ($r->input('status') === 'Resign' ? ' (marked as left the shop)' : ''), $who,
            ]),
            'EmployeeController@destroy' => $d->withAccount($id, fn ($who) => ["Removed {$who}'s team account", $who]),
            'EmployeeController@updateStatus' => $d->withAccount($id, fn ($who) => ["Changed {$who}'s account status to " . $d->text($r->input('status')), $who]),
            'AssignmentController@store', 'AssignmentController@destroy', 'AssignmentController@setPrimary' => $d->withAccount($id, fn ($who) => [
                match ($key) { 'AssignmentController@store' => "Gave {$who} a job assignment", 'AssignmentController@destroy' => "Removed one of {$who}'s job assignments", default => "Changed {$who}'s main job" }, $who,
            ]),
            'StaffController@storeRole' => ['Created the role "' . $d->text($r->input('name')) . '"', null],
            'StaffController@updateRole' => $d->named('staff_roles', $id, 'name', fn ($n) => ["Changed what the \"{$n}\" role can do", null]),
            'StaffController@destroyRole' => $d->named('staff_roles', $id, 'name', fn ($n) => ["Deleted the role \"{$n}\"", null]),

            // ── Products and stock ──
            'ProductController@store' => ['Added the product "' . $d->text($r->input('product_name')) . '"', $d->text($r->input('product_name'))],
            'ProductController@update' => $d->product($id, fn ($p) => ["Edited the product \"{$p}\"", $p]),
            'ProductController@destroy' => $d->product($id, fn ($p) => ["Deleted the product \"{$p}\"", $p]),
            'ProductController@updateStock', 'VendorCatalogController@adjustStock' => $d->product($id, fn ($p) => ["Changed the stock of \"{$p}\"" . $d->stockChange($r), $p]),
            'ProductController@toggleStatus', 'ProductController@updateStatus' => $d->product($id, fn ($p) => ["Changed whether \"{$p}\" is shown" . $d->suffix($r->input('status'), ' (now ', ')'), $p]),
            'ProductController@deleteImage' => $d->product($r->route('productId'), fn ($p) => ["Removed a photo of \"{$p}\"", $p]),
            'ProductController@deleteModel' => $d->product($r->route('productId'), fn ($p) => ["Removed the 3D model of \"{$p}\"", $p]),
            'VendorCatalogController@discount' => $d->product($id, fn ($p) => $r->filled('discount_price')
                ? ["Put \"{$p}\" on sale for " . $d->money($r->input('discount_price')), $p]
                : ["Changed the discount on \"{$p}\"", $p]),
            'VendorCatalogController@stockLevels' => $d->product($id, fn ($p) => ["Changed the low-stock alert for \"{$p}\"", $p]),
            'VendorCatalogController@bulk' => ['Changed ' . $d->count(count((array) $r->input('ids', [])), 'product') . ' at once' . $d->suffix($r->input('action'), ' (', ')'), null],
            'VendorCustomizationOptionController@store' => ['Added the customization option "' . $d->text($r->input('name')) . '"', null],
            'VendorCustomizationOptionController@update' => $d->named('bouquet_customization_options', $id, 'name', fn ($n) => ["Edited the customization option \"{$n}\"", null]),
            'VendorCustomizationOptionController@destroy' => $d->named('bouquet_customization_options', $id, 'name', fn ($n) => ["Deleted the customization option \"{$n}\"", null]),
            'VendorCustomizationOptionController@setActive' => $d->named('bouquet_customization_options', $id, 'name', fn ($n) => [($r->boolean('is_active') || $r->boolean('active') ? 'Turned on' : 'Turned off') . " the customization option \"{$n}\"", null]),

            // ── Promotions ──
            'VendorPromotionController@store' => ['Created the promotion "' . $d->text($r->input('name')) . '"', null],
            'VendorPromotionController@update' => $d->named('vendor_promotions', $id, 'name', fn ($n) => ["Edited the promotion \"{$n}\"", null]),
            'VendorPromotionController@destroy' => $d->named('vendor_promotions', $id, 'name', fn ($n) => ["Deleted the promotion \"{$n}\"", null]),
            'VendorPromotionController@toggle' => $d->named('vendor_promotions', $id, 'name', fn ($n) => ["Turned the promotion \"{$n}\" on or off", null]),

            // ── Orders, deliveries, payments ──
            'VendorOrdersController@updateOrderStatus' => $d->order($r->route('orderId'), fn ($o, $customer) => [
                "Changed order {$o->order_number}" . $d->suffix($customer, ' for ') . ' from ' . $d->status($o->status) . ' to ' . $d->status($r->input('status')), $customer,
            ]),
            'VendorOrdersController@markDateAsClosed' => ['Closed ' . $d->day($r->input('date') ?? $r->input('closed_date')) . ' for orders' . $d->suffix($r->input('reason'), ' ("', '")'), null],
            'VendorOrdersController@removeClosedDate' => $d->named('vendor_closed_dates', $id, 'closed_date', fn ($day) => ['Opened ' . $d->day($day) . ' for orders again', null]),
            'DeliveryController@updateStatus' => $d->row('deliveries', $id, fn ($del) => $d->order($del->order_id, fn ($o, $customer) => [
                "Changed the delivery of order {$o->order_number}" . $d->suffix($customer, ' for ') . ' to ' . $d->status($r->input('status')), $customer,
            ])),
            'PaymentsController@collect' => $d->order($r->route('orderId'), fn ($o, $customer) => ["Recorded the cash collected for order {$o->order_number}" . $d->suffix($customer, ' from '), $customer]),
            'PaymentsController@undo' => $d->order($r->route('orderId'), fn ($o, $customer) => ["Undid the cash collection for order {$o->order_number}" . $d->suffix($customer, ' from '), $customer]),
            'PaymentsController@settle' => ['Settled ' . $d->count(count((array) $r->input('order_ids', $r->input('ids', []))), 'cash-on-delivery collection'), null],
            'RefundsController@sent' => $d->row('order_requests', $id, fn ($req) => $d->order($req->order_id, fn ($o, $customer) => [
                "Recorded the refund for order {$o->order_number}" . $d->suffix($customer, ' to ') . ' as sent', $customer,
            ])),

            // ── Expenses and funding ──
            'VendorBooksController@storeExpense' => ['Recorded the expense "' . $d->text($r->input('description')) . '" for ' . $d->money($r->input('amount')), null],
            'VendorBooksController@updateExpense' => $d->named('vendor_expenses', $id, 'description', fn ($n) => ["Edited the expense \"{$n}\"" . ($r->filled('amount') ? ' (' . $d->money($r->input('amount')) . ')' : ''), null]),
            'VendorBooksController@destroyExpense' => $d->named('vendor_expenses', $id, 'description', fn ($n) => ["Deleted the expense \"{$n}\"", null]),
            'FundingRequestController@store' => ['Requested funding for ' . $d->text($r->input('required_quantity')) . ' × "' . $d->text($r->input('product_name')) . '"', null],
            'FundingRequestController@update', 'FundingRequestController@destroy', 'FundingRequestController@submitToAccounting',
            'AccountingFundingRequestController@approve', 'AccountingFundingRequestController@reject' => $d->named('funding_requests', $id, 'product_name', fn ($n) => [match ($key) {
                'FundingRequestController@update' => "Edited the funding request for \"{$n}\"",
                'FundingRequestController@destroy' => "Deleted the funding request for \"{$n}\"",
                'FundingRequestController@submitToAccounting' => "Sent the funding request for \"{$n}\" to Finance",
                'AccountingFundingRequestController@approve' => "Approved the funding request for \"{$n}\"",
                default => "Rejected the funding request for \"{$n}\"",
            }, null]),

            // ── Suppliers and supply chain ──
            'SupplierController@store' => ['Added the supplier "' . $d->text($r->input('company_name')) . '"', null],
            'SupplierController@update', 'SupplierController@destroy', 'SupplierController@activate', 'SupplierController@deactivate', 'SupplierController@blacklist' =>
                $d->named('suppliers', $id, 'company_name', fn ($n) => [match ($key) {
                    'SupplierController@update' => "Edited the supplier \"{$n}\"",
                    'SupplierController@destroy' => "Deleted the supplier \"{$n}\"",
                    'SupplierController@activate' => "Marked the supplier \"{$n}\" as active",
                    'SupplierController@deactivate' => "Marked the supplier \"{$n}\" as inactive",
                    default => "Blocked the supplier \"{$n}\"",
                }, null]),
            'SupplierController@storeContact', 'SupplierController@updateContact', 'SupplierController@destroyContact' =>
                $d->named('suppliers', $r->route('supplierId'), 'company_name', fn ($n) => [match ($key) {
                    'SupplierController@storeContact' => 'Added ' . $d->text($r->input('contact_person') ?? $r->input('name')) . " as a contact for \"{$n}\"",
                    'SupplierController@updateContact' => "Edited a contact of \"{$n}\"",
                    default => "Removed a contact of \"{$n}\"",
                }, null]),
            'OrderController@store', 'OrderController@createFromFunding' => ['Created a supplier order' . $d->supplierFor($r->input('supplier_id')), null],
            'OrderController@update', 'OrderController@destroy', 'OrderController@attachItems', 'OrderController@removeItem', 'OrderController@recalculateTotals', 'OrderController@updateStatus' =>
                $d->named('purchase_orders', $id, 'order_number', fn ($n) => [match ($key) {
                    'OrderController@update' => "Edited supplier order {$n}",
                    'OrderController@destroy' => "Deleted supplier order {$n}",
                    'OrderController@attachItems' => "Added items to supplier order {$n}",
                    'OrderController@removeItem' => "Removed an item from supplier order {$n}",
                    'OrderController@recalculateTotals' => "Recalculated the total of supplier order {$n}",
                    default => "Changed supplier order {$n} to " . $d->status($r->input('status')),
                }, null]),
            'ShipmentController@store' => ['Added a shipment' . $d->suffix($r->input('tracking_number'), ' (tracking ', ')'), null],
            'ShipmentController@update', 'ShipmentController@destroy', 'ShipmentController@markShipped', 'ShipmentController@markReceived', 'ShipmentController@updateStatus', 'ShipmentController@updateTracking' =>
                $d->row('shipments', $id, function ($s) use ($d, $key, $r) {
                    $what = 'the shipment' . ($s->tracking_number ? " {$s->tracking_number}" : '') . $d->suffix(DB::table('purchase_orders')->where('id', $s->purchase_order_id)->value('order_number'), ' for supplier order ');

                    return [match ($key) {
                        'ShipmentController@update' => "Edited {$what}",
                        'ShipmentController@destroy' => "Deleted {$what}",
                        'ShipmentController@markShipped' => "Marked {$what} as sent",
                        'ShipmentController@markReceived' => "Marked {$what} as received",
                        'ShipmentController@updateTracking' => "Updated the tracking of {$what}" . $d->suffix($r->input('tracking_number'), ' to '),
                        default => "Changed {$what} to " . $d->status($r->input('status')),
                    }, null];
                }),
            'WarehouseController@store' => ['Added the warehouse "' . $d->text($r->input('name')) . '"', null],
            'WarehouseController@update' => $d->named('warehouses', $id, 'name', fn ($n) => ["Edited the warehouse \"{$n}\"", null]),
            'WarehouseController@destroy' => $d->named('warehouses', $id, 'name', fn ($n) => ["Deleted the warehouse \"{$n}\"", null]),
            'WarehouseController@addItem' => $d->named('warehouses', $r->route('warehouseId'), 'name', fn ($n) => ['Added "' . $d->text($r->input('product_name')) . "\" to the warehouse \"{$n}\"", null]),
            'WarehouseController@updateItem' => $d->named('warehouse_items', $id, 'product_name', fn ($n) => ["Edited the warehouse item \"{$n}\"", null]),
            'WarehouseController@adjustStock' => $d->named('warehouse_items', $id, 'product_name', fn ($n) => ["Changed the warehouse stock of \"{$n}\"" . $d->stockChange($r), null]),
            'WarehouseBatchController@receive' => ['Received a batch of ' . $d->text($r->input('quantity')) . ' × ' . $d->productName($r->input('product_id')), null],
            'WarehouseBatchController@updateCondition', 'WarehouseBatchController@cull', 'WarehouseBatchController@transfer' => $d->row('warehouse_batches', $id, function ($b) use ($d, $key, $r) {
                $batch = 'batch ' . ($b->batch_number ?: $b->lot_number) . ' of ' . $d->productName($b->product_id);

                return [match ($key) {
                    'WarehouseBatchController@updateCondition' => "Marked {$batch} as " . $d->status($r->input('condition_status') ?? $r->input('condition')),
                    'WarehouseBatchController@cull' => 'Threw out ' . $d->text($r->input('quantity')) . " damaged or wilted stems from {$batch}",
                    default => "Moved {$batch} to another spot",
                }, null];
            }),
            'WarehouseLocationController@store' => ['Added the storage spot "' . $d->text($r->input('name') ?? $r->input('code')) . '"', null],
            'WarehouseLocationController@update', 'WarehouseLocationController@destroy', 'WarehouseLocationController@toggle' => $d->named('warehouse_locations', $id, 'name', fn ($n) => [match ($key) {
                'WarehouseLocationController@update' => "Edited the storage spot \"{$n}\"",
                'WarehouseLocationController@destroy' => "Deleted the storage spot \"{$n}\"",
                default => "Turned the storage spot \"{$n}\" on or off",
            }, null]),

            // ── Customers ──
            'ChatController@sendMessage' => $d->conversation($r->input('conversation_id'), fn ($c) => ["Messaged {$c} in chat", $c]),
            'ChatController@startConversation' => $d->withCustomer($r->input('customer_id') ?? $r->input('user_id'), fn ($c) => ["Started a chat with {$c}", $c]),
            'ChatController@archiveConversation' => $d->conversation($id, fn ($c) => ["Archived the chat with {$c}", $c]),
            'ChatController@unarchiveConversation' => $d->conversation($id, fn ($c) => ["Moved the chat with {$c} back to the inbox", $c]),
            'ChatController@deleteConversation' => $d->conversation($id, fn ($c) => ["Deleted the chat with {$c}", $c]),
            'ChatShareController@orderUpdate' => $d->conversation($id, fn ($c) => ["Sent {$c} an order update in chat", $c]),
            'ChatShareController@shareProduct' => $d->conversation($id, fn ($c) => ["Sent {$c} the product " . $d->productName($r->input('product_id')) . ' in chat', $c]),
            'VendorCrmController@answer', 'VendorCrmController@inquiryStatus' => $d->row('customer_inquiries', $id, fn ($q) => $d->withCustomer($q->customer_id, fn ($c) => [
                ($key === 'VendorCrmController@answer' ? "Answered {$c}'s question" : "Marked {$c}'s question as " . $d->status($r->input('status'))) . ($q->product_id ? ' about ' . $d->productName($q->product_id) : ''), $c,
            ])),
            'VendorCrmController@reply' => $d->row('product_reviews', $id, fn ($rv) => $d->withCustomer($rv->user_id, fn ($c) => ["Replied to {$c}'s review of " . $d->productName($rv->product_id), $c])),
            'CrmCustomersController@updatePreferences' => $d->withCustomer($id, fn ($c) => ["Updated {$c}'s preferences", $c]),
            'CrmCustomersController@storeDate' => $d->withCustomer($id, fn ($c) => ['Added ' . $d->text($r->input('label') ?? $r->input('kind')) . " to {$c}'s special days", $c]),
            'CrmCustomersController@destroyDate' => $d->withCustomer($id, fn ($c) => ["Removed one of {$c}'s special days", $c]),
            'CrmCustomersController@storeNote' => $d->withCustomer($id, fn ($c) => ["Added a note about {$c}", $c]),
            'CrmCustomersController@pinNote' => $d->withCustomer($id, fn ($c) => ["Pinned or unpinned a note about {$c}", $c]),
            'CrmCustomersController@destroyNote' => $d->withCustomer($id, fn ($c) => ["Deleted a note about {$c}", $c]),
            'CrmCasesController@store' => $d->withCustomer($r->input('customer_id'), fn ($c) => ['Recorded a support case for ' . $c . $d->suffix($r->input('subject'), ': "', '"'), $c]),
            'CrmCasesController@reply', 'CrmCasesController@update' => $d->row('support_tickets', $id, fn ($t) => $d->withCustomer($t->requester_id, fn ($c) => [
                ($key === 'CrmCasesController@reply' ? "Replied to {$c}'s support case" : "Updated {$c}'s support case" . $d->suffix($r->input('status'), ' (now ', ')')) . ($t->reference ? " {$t->reference}" : ''), $c,
            ])),
            'CrmSegmentsController@send' => ['Sent an offer to the "' . $d->text(Str::headline((string) $r->route('segment'))) . '" customer group', null],

            default => null,
        };
    }

    public function __construct(private Request $r) {}

    // ── Lookups (never throw; null when the record can't be found) ──

    private function first(string $table, $id): ?object
    {
        return $id ? DB::table($table)->where('id', $id)->first() : null;
    }

    private function row(string $table, $id, callable $then): ?array
    {
        $row = $this->first($table, $id);

        return $row ? $then($row) : null;
    }

    private function named(string $table, $id, string $column, callable $then): ?array
    {
        $value = $id ? DB::table($table)->where('id', $id)->value($column) : null;

        return $value !== null && $value !== '' ? $then($this->text((string) $value)) : null;
    }

    /** An HR profile's full name */
    private function person($id): string
    {
        $p = $this->first('employees_info', $id);

        return $p ? $this->text(trim(implode(' ', array_filter([$p->first_name, $p->last_name])))) : 'an employee';
    }

    private function people($ids): string
    {
        $names = collect((array) $ids)->take(6)->map(fn ($id) => $this->person($id))->all();
        $more = count((array) $ids) - count($names);

        return match (true) {
            ! $names => 'nobody',
            count($names) === 1 => $names[0],
            default => implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names),
        } . ($more > 0 ? " and {$more} more" : '');
    }

    private function withPerson($id, callable $then): ?array
    {
        return $id && $this->first('employees_info', $id) ? $then($this->person($id)) : null;
    }

    private function withAccount($id, callable $then): ?array
    {
        $name = $id ? DB::table('employees')->where('id', $id)->value('name') : null;

        return $name ? $then($this->text($name)) : null;
    }

    private function withCustomer($id, callable $then): ?array
    {
        $u = $this->first('users', $id);

        return $u ? $then($this->text(trim($u->name . ' ' . ($u->surname ?? '')))) : null;
    }

    private function conversation($id, callable $then): ?array
    {
        $c = $this->first('conversations', $id);

        return $c ? $this->withCustomer($c->customer_id, $then) : null;
    }

    private function shift($id, callable $then): ?array
    {
        $s = $this->first('staff_shifts', $id);

        return $s ? $then($s, $this->person($s->employee_id), $this->day($s->shift_date)) : null;
    }

    private function assignShift(): ?array
    {
        $who = $this->person($this->r->input('employee_id'));
        $dates = (array) ($this->r->input('dates') ?? [$this->r->input('date')]);
        $when = count($dates) === 1 ? $this->day($dates[0]) : $this->count(count($dates), 'day') . ' from ' . $this->day(min($dates)) . ' to ' . $this->day(max($dates));
        if ($this->r->boolean('day_off') || $this->r->boolean('is_off')) {
            return ["Gave {$who} a day off on {$when}", $who];
        }
        $template = $this->r->filled('template_id') ? DB::table('shift_templates')->where('id', $this->r->input('template_id'))->first() : null;
        $start = $this->r->input('start') ?? $this->r->input('start_time') ?? $template?->start_time;
        $end = $this->r->input('end') ?? $this->r->input('end_time') ?? $template?->end_time;

        return ["Scheduled {$who} on {$when}" . ($template ? " for the \"{$template->name}\" shift" : '') . $this->hours($start, $end), $who];
    }

    private function attendance($id, callable $then): ?array
    {
        $a = $this->first('employee_attendance', $id);

        return $a ? $then($a, $this->person($a->employee_id), $this->day($a->attendance_date)) : null;
    }

    private function leave($id, callable $then): ?array
    {
        $l = $this->first('employee_leaves', $id);

        return $l ? $then($l, $this->person($l->employee_id)) : null;
    }

    /** "Badet's payroll for Oct 1 – Oct 15" or "payrolls for Badet and Miki" */
    private function payrolls($ids, callable $then): ?array
    {
        $rows = DB::table('payrolls')->whereIn('id', array_filter((array) $ids))->get();
        if ($rows->isEmpty()) {
            return null;
        }
        if ($rows->count() === 1) {
            $p = $rows->first();

            return $then($this->person($p->employee_id) . "'s payroll for " . $this->span($p->period_start, $p->period_end), $this->person($p->employee_id));
        }
        $who = $this->people($rows->pluck('employee_id')->unique()->values()->all());

        return $then("payrolls for {$who}", $who);
    }

    private function product($id, callable $then): ?array
    {
        $name = $id ? DB::table('products')->where('id', $id)->value('product_name') : null;

        return $name ? $then($this->text($name)) : null;
    }

    private function productName($id): string
    {
        $name = $id ? DB::table('products')->where('id', $id)->value('product_name') : null;

        return $name ? '"' . $this->text($name) . '"' : 'a product';
    }

    private function supplierFor($id): string
    {
        $name = $id ? DB::table('suppliers')->where('id', $id)->value('company_name') : null;

        return $name ? ' with "' . $this->text($name) . '"' : '';
    }

    private function order($id, callable $then): ?array
    {
        $o = $this->first('orders', $id);
        if (! $o) {
            return null;
        }
        $u = $this->first('users', $o->user_id);

        return $then($o, $u ? $this->text(trim($u->name . ' ' . ($u->surname ?? ''))) : ($o->delivery_contact_name ?? null));
    }

    // ── Wording ──

    private function text($value, int $limit = 80): string
    {
        return Str::limit(trim((string) $value), $limit);
    }

    private function suffix($value, string $before, string $after = ''): string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? $before . $this->text($value, 60) . $after : '';
    }

    /** "Thu, Aug 7" (with the year when it isn't this year) */
    private function day($date): string
    {
        if (! $date) {
            return 'a day';
        }
        $d = Carbon::parse($date);

        return $d->format($d->isCurrentYear() ? 'D, M j' : 'D, M j, Y');
    }

    private function span($from, $to): string
    {
        return ! $to || $this->day($from) === $this->day($to) ? $this->day($from) : $this->day($from) . ' – ' . $this->day($to);
    }

    private function time($value): string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : '—';
    }

    private function hours($start, $end): string
    {
        return $start && $end ? ' (' . $this->time($start) . ' – ' . $this->time($end) . ')' : '';
    }

    private function minutes($minutes): string
    {
        $m = (int) $minutes;
        $h = intdiv($m, 60);
        $rest = $m % 60;

        return trim(($h ? $this->count($h, 'hour') : '') . ($h && $rest ? ' ' : '') . ($rest || ! $h ? $this->count($rest, 'minute') : ''));
    }

    private function count($n, string $word): string
    {
        $n = (float) $n == (int) $n ? (int) $n : $n;

        return "{$n} {$word}" . ($n == 1 ? '' : 's');
    }

    private function money($amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }

    private function leaveType($type): string
    {
        return Str::lower(LeavePolicy::TYPES[$type][0] ?? Str::headline((string) $type));
    }

    private function status($status): string
    {
        return $status ? Str::lower(Str::headline((string) $status)) : 'a new status';
    }

    /** " (+5)", " (−3)" or " to 12", from the catalog form (mode + quantity) or the inventory form (new count) */
    private function stockChange(Request $r): string
    {
        $qty = $r->input('quantity') ?? $r->input('quantity_in_stock');
        if (! is_numeric($qty)) {
            return '';
        }

        $change = match ($r->input('mode')) {
            'add' => ' (+' . $qty . ')',
            'remove' => ' (−' . $qty . ')',
            default => ' to ' . $qty,
        };
        // The warehouse form sends a signed change
        if ($r->input('mode') === null && $r->has('quantity') && ! $r->has('quantity_in_stock')) {
            $change = ' (' . ((int) $qty > 0 ? '+' : '') . $qty . ')';
        }

        return $change . $this->suffix($r->input('reason'), ', reason: ');
    }
}
