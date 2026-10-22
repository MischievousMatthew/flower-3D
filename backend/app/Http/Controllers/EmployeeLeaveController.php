<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeInfo;
use App\Models\EmployeeLeave;
use App\Models\LeavePolicy;
use App\Models\OvertimeRequest;
use App\Services\Leave\LeaveAccess;
use App\Services\Leave\LeaveService;
use App\Traits\ScopesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Leave & overtime management for HR (ERP) and the shop owner: requests with their history,
 * balances, the shop's leave rules, overtime requests and the leave QR poster.
 * Employees file from the public leave page (PublicLeaveController).
 */
class EmployeeLeaveController extends Controller
{
    use ScopesOwner;

    public function __construct(private LeaveService $leave, private LeaveAccess $access) {}

    /** The old public endpoints trusted a made-up QR token. POST /public/leave/verify-qr and /submit */
    public function retired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The leave page has moved. Scan the shop\'s leave QR code again.',
        ], 410);
    }

    /** GET /leaves?status&leave_type&employee_id&year */
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,cancelled',
            'leave_type' => 'nullable|string|max:40',
            'employee_id' => 'nullable|integer',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);
        $leaves = $this->leave->leaves($ownerId)->with('employee')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['leave_type'] ?? null, fn ($q, $t) => $q->where('leave_type', $t))
            ->when($data['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($data['year'] ?? null, fn ($q, $y) => $q->whereYear('start_date', $y))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->limit(300)->get();

        return response()->json(['success' => true, 'data' => $leaves->map(fn ($l) => $this->leave->formatLeave($l, true))->values()]);
    }

    /** GET /leaves/statistics */
    public function getStatistics(): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $month = fn () => $this->leave->leaves($ownerId)->where('status', 'approved')
            ->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year);

        return response()->json(['success' => true, 'data' => [
            'pending' => $this->leave->leaves($ownerId)->where('status', 'pending')->count(),
            'approved_this_month' => $month()->count(),
            'total_days_this_month' => (float) $month()->sum('total_days'),
            'rejected' => $this->leave->leaves($ownerId)->where('status', 'rejected')->count(),
            'on_leave_today' => $this->leave->leaves($ownerId)->where('status', 'approved')
                ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->count(),
            'overtime_pending' => OvertimeRequest::where('owner_id', $ownerId)->where('status', 'pending')->count(),
        ]]);
    }

    /** POST /leaves — HR or the owner files leave for someone (multipart) */
    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')],
            'leave_type' => 'required|string|in:' . implode(',', array_keys(LeavePolicy::TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'required|string|min:5|max:1000',
            'document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
        ]);
        $employee = EmployeeInfo::forOwner($ownerId)->findOrFail($data['employee_id']);
        $user = $request->user();
        $leave = $this->leave->file($ownerId, $employee, $data, $request->file('document'),
            $user instanceof Employee ? 'hr' : 'owner', $user instanceof Employee ? $user->name : 'Shop owner');

        return response()->json(['success' => true, 'message' => 'Leave filed. It still needs approval.', 'data' => $this->leave->formatLeave($leave->load('employee'), true)], 201);
    }

    /** PUT /leaves/{id}/status {status: approved|rejected, admin_notes} */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'admin_notes' => 'nullable|string|max:1000']);
        $ownerId = $this->getOwnerId();
        $leave = $this->leave->leaves($ownerId)->with('employee')->findOrFail($id);
        $this->leave->decide($ownerId, $leave, $data['status'], $data['admin_notes'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'approved' ? 'Leave approved.' : 'Leave rejected.',
            'data' => $this->leave->formatLeave($leave->fresh('employee'), true),
        ]);
    }

    /** POST /leaves/{id}/cancel {note} */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $ownerId = $this->getOwnerId();
        $leave = $this->leave->leaves($ownerId)->findOrFail($id);
        $user = $request->user();
        $this->leave->cancel($ownerId, $leave, $user instanceof Employee ? $user->name : 'Shop owner', false, $data['note'] ?? null);

        return response()->json(['success' => true, 'message' => 'Leave cancelled.']);
    }

    /** GET /leaves/{id}/document — a link valid for 5 minutes */
    public function document(int $id): JsonResponse
    {
        $leave = $this->leave->leaves($this->getOwnerId())->findOrFail($id);

        return response()->json(['success' => true, 'data' => ['url' => $this->leave->documentLink($leave)]]);
    }

    /** DELETE /leaves/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->leave->leaves($this->getOwnerId())->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Leave request deleted.']);
    }

    /** GET /leaves/balances?year */
    public function balances(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $year = (int) ($request->validate(['year' => 'nullable|integer|min:2000|max:2100'])['year'] ?? now()->year);
        $people = EmployeeInfo::forOwner($ownerId)->orderBy('first_name')->get()
            ->filter(fn ($p) => ! in_array(strtolower((string) $p->employment_status), ['inactive', 'resigned', 'terminated'], true));

        return response()->json(['success' => true, 'data' => [
            'year' => $year,
            'people' => $people->map(fn ($p) => [
                'employee_id' => $p->id,
                'name' => $p->full_name,
                'position' => $p->position,
                'balances' => $this->leave->balances($ownerId, $p, $year),
            ])->values(),
        ]]);
    }

    /** POST /leaves/balances/adjust {employee_id, leave_type, year, days, note} */
    public function adjust(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)],
            'leave_type' => 'required|string|in:' . implode(',', array_keys(LeavePolicy::TYPES)),
            'year' => 'required|integer|min:2000|max:2100',
            'days' => 'required|numeric|min:-365|max:365|not_in:0',
            'note' => 'required|string|min:3|max:255',
        ], ['note.required' => 'Say why, e.g. "Carried over from last year".', 'days.not_in' => 'Enter how many days to add or take away.']);
        $employee = EmployeeInfo::forOwner($ownerId)->findOrFail($data['employee_id']);
        $this->leave->adjustBalance($ownerId, $employee, $data['leave_type'], $data['year'], (float) $data['days'], trim($data['note']), $request->user());

        return response()->json(['success' => true, 'message' => 'Balance updated.', 'data' => $this->leave->balances($ownerId, $employee, $data['year'])]);
    }

    /** GET /leaves/policies */
    public function policies(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->leave->policies($this->getOwnerId())->map(fn ($p) => $this->leave->formatPolicy($p))->values()]);
    }

    /** PUT /leaves/policies {policies: [{leave_type, annual_days, is_paid, enabled, requires_document}]} */
    public function savePolicies(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'policies' => 'required|array',
            'policies.*.leave_type' => 'required|string|in:' . implode(',', array_keys(LeavePolicy::TYPES)),
            'policies.*.annual_days' => 'nullable|numeric|min:0|max:365',
            'policies.*.is_paid' => 'required|boolean',
            'policies.*.enabled' => 'required|boolean',
            'policies.*.requires_document' => 'required|boolean',
        ]);
        $policies = $this->leave->policies($ownerId);
        foreach ($data['policies'] as $row) {
            $policies[$row['leave_type']]->update([
                'annual_days' => $row['annual_days'] ?? null,
                'is_paid' => $row['is_paid'],
                'enabled' => $row['enabled'],
                'requires_document' => $row['requires_document'],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Leave rules saved. New requests follow them.']);
    }

    /** GET /leaves/poster — the shop code the leave QR poster opens */
    public function poster(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $shop = \App\Models\User::findOrFail($ownerId);

        return response()->json(['success' => true, 'data' => ['code' => $this->access->shopCode($ownerId)] + $this->access->shopInfo($shop)]);
    }

    // ── Overtime requests ──────────────────────────────────────────────────

    /** GET /overtime-requests?status */
    public function overtimeIndex(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $status = $request->validate(['status' => 'nullable|in:pending,approved,rejected,cancelled'])['status'] ?? null;
        $rows = OvertimeRequest::where('owner_id', $ownerId)->with('employee')
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('date')->limit(300)->get();

        return response()->json(['success' => true, 'data' => $rows->map(fn ($r) => $this->leave->formatOvertimeRequest($r, true))->values()]);
    }

    /** POST /overtime-requests {employee_id, date, minutes, reason} */
    public function overtimeStore(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')],
            'date' => 'required|date',
            'minutes' => 'required|integer',
            'reason' => 'required|string|min:5|max:1000',
        ]);
        $employee = EmployeeInfo::forOwner($ownerId)->findOrFail($data['employee_id']);
        $user = $request->user();
        $r = $this->leave->requestOvertime($ownerId, $employee, $data, $user instanceof Employee ? 'hr' : 'owner', $user instanceof Employee ? $user->name : 'Shop owner');

        return response()->json(['success' => true, 'message' => 'Overtime request filed. It still needs approval.', 'data' => $this->leave->formatOvertimeRequest($r->load('employee'), true)], 201);
    }

    /** PUT /overtime-requests/{id}/status {status: approved|rejected, note} */
    public function overtimeDecide(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:1000']);
        $user = $request->user();
        if ($user instanceof Employee && ! $user->hasModulePermission('leave_management', $data['status'] === 'approved' ? 'approve' : 'reject')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $ownerId = $this->getOwnerId();
        $r = OvertimeRequest::where('owner_id', $ownerId)->with('employee')->findOrFail($id);
        $this->leave->decideOvertimeRequest($ownerId, $r, $data['status'], $data['note'] ?? null, $user);

        return response()->json(['success' => true, 'message' => $data['status'] === 'approved'
            ? 'Overtime approved. Staying late that day is approved automatically, up to these minutes.'
            : 'Overtime request rejected.']);
    }

    /** POST /overtime-requests/{id}/cancel */
    public function overtimeCancel(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $r = OvertimeRequest::where('owner_id', $ownerId)->findOrFail($id);
        $user = $request->user();
        $this->leave->cancelOvertimeRequest($ownerId, $r, $user instanceof Employee ? $user->name : 'Shop owner');

        return response()->json(['success' => true, 'message' => 'Overtime request cancelled.']);
    }
}
