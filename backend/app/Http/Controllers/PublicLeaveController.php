<?php

namespace App\Http\Controllers;

use App\Models\EmployeeInfo;
use App\Models\EmployeeLeave;
use App\Models\LeavePolicy;
use App\Models\OvertimeRequest;
use App\Services\Leave\LeaveAccess;
use App\Services\Leave\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public leave page (/leaves?s=<shop code>), opened from the shop's leave QR poster.
 * Employees identify themselves by scanning their own badge, then see their balances,
 * file leave or overtime requests, and follow or cancel them. The session token travels
 * in request bodies, never in URLs.
 */
class PublicLeaveController extends Controller
{
    public function __construct(private LeaveAccess $access, private LeaveService $leave) {}

    /** POST /public/leave/shop {shop} */
    public function shop(Request $request): JsonResponse
    {
        $shop = $this->access->shopFromCode($request->input('shop'));

        return response()->json(['success' => true, 'data' => $this->access->shopInfo($shop)]);
    }

    /** POST /public/leave/identify {shop, badge} */
    public function identify(Request $request): JsonResponse
    {
        $data = $request->validate(['shop' => 'required|string|max:80', 'badge' => 'required|string|max:1000']);
        $shop = $this->access->shopFromCode($data['shop']);
        $result = $this->access->identify($shop, $data['badge']);

        return response()->json(['success' => true, 'data' => [
            'token' => $result['token'],
            'expires_in' => LeaveAccess::SESSION_MINUTES * 60,
        ] + $this->overview($shop->id, $result['employee'], $result['legacy'])]);
    }

    /** POST /public/leave/me {token} */
    public function me(Request $request): JsonResponse
    {
        $s = $this->access->session($request->input('token'));

        return response()->json(['success' => true, 'data' => $this->overview($s['shop']->id, $s['employee'], $s['legacy'])]);
    }

    /** POST /public/leave/requests {token, leave_type, start_date, end_date, reason, document?} */
    public function file(Request $request): JsonResponse
    {
        $s = $this->access->session($request->input('token'));
        $data = $request->validate([
            'leave_type' => 'required|string|in:' . implode(',', array_keys(LeavePolicy::TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'required|string|min:5|max:1000',
            'document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
        ], [
            'reason.min' => 'Say a bit more about why.',
            'document.max' => 'Documents can be up to 10 MB.',
            'document.mimes' => 'Upload a PDF, photo or Word file.',
        ]);
        $leave = $this->leave->file($s['shop']->id, $s['employee'], $data, $request->file('document'), 'employee', $s['employee']->full_name, ! $s['legacy']);

        return response()->json(['success' => true, 'message' => 'Leave request sent. HR will review it.', 'data' => [
            'request' => $this->leave->formatLeave($leave->load('employee')),
        ] + $this->overview($s['shop']->id, $s['employee'], $s['legacy'])], 201);
    }

    /** POST /public/leave/requests/{id}/cancel {token} */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $s = $this->access->session($request->input('token'));
        $leave = $this->leave->leaves($s['shop']->id)->where('employee_id', $s['employee']->id)->findOrFail($id);
        $this->leave->cancel($s['shop']->id, $leave, $s['employee']->full_name, true);

        return response()->json(['success' => true, 'message' => 'Request cancelled.', 'data' => $this->overview($s['shop']->id, $s['employee'], $s['legacy'])]);
    }

    /** POST /public/leave/overtime {token, date, minutes, reason} */
    public function overtime(Request $request): JsonResponse
    {
        $s = $this->access->session($request->input('token'));
        $data = $request->validate([
            'date' => 'required|date',
            'minutes' => 'required|integer',
            'reason' => 'required|string|min:5|max:1000',
        ], ['reason.min' => 'Say a bit more about why.']);
        $this->leave->requestOvertime($s['shop']->id, $s['employee'], $data, 'employee', $s['employee']->full_name);

        return response()->json(['success' => true, 'message' => 'Overtime request sent. HR will review it.', 'data' => $this->overview($s['shop']->id, $s['employee'], $s['legacy'])], 201);
    }

    /** POST /public/leave/overtime/{id}/cancel {token} */
    public function cancelOvertime(Request $request, int $id): JsonResponse
    {
        $s = $this->access->session($request->input('token'));
        $overtime = OvertimeRequest::where('owner_id', $s['shop']->id)->where('employee_info_id', $s['employee']->id)->findOrFail($id);
        $this->leave->cancelOvertimeRequest($s['shop']->id, $overtime, $s['employee']->full_name);

        return response()->json(['success' => true, 'message' => 'Request cancelled.', 'data' => $this->overview($s['shop']->id, $s['employee'], $s['legacy'])]);
    }

    /** Everything the page shows for one person */
    private function overview(int $ownerId, EmployeeInfo $employee, bool $legacy): array
    {
        $leaves = $this->leave->leaves($ownerId)->where('employee_id', $employee->id)->with('employee')
            ->orderByDesc('start_date')->limit(30)->get();
        $overtime = OvertimeRequest::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)->with('employee')
            ->orderByDesc('date')->limit(30)->get();

        return [
            'employee' => [
                'name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
                'position' => $employee->position,
                'avatar' => $employee->avatar_url,
            ],
            'old_badge' => $legacy,
            'balances' => $this->leave->balances($ownerId, $employee),
            'leaves' => $leaves->map(fn (EmployeeLeave $l) => $this->leave->formatLeave($l, true))->values(),
            'overtime' => $overtime->map(fn (OvertimeRequest $r) => $this->leave->formatOvertimeRequest($r, true))->values(),
            'today' => now()->toDateString(),
            'backdate_days' => LeaveService::EMPLOYEE_BACKDATE_DAYS,
        ];
    }
}
