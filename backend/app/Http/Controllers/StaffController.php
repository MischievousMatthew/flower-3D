<?php

namespace App\Http\Controllers;

use App\Constants\ErpModule;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\StaffActivityLog;
use App\Models\StaffRole;
use App\Models\StaffShift;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceLocationService;
use App\Services\ResourceLimitService;
use App\Services\StaffAccessService;
use App\Services\SubscriptionAccessService;
use App\Subscriptions\SubscriptionPlans;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The vendor portal's Staff & operations pages, for shop owners:
 *  - team accounts (people who sign in to the ERP) and roles & permissions, like the admin console;
 *  - HR profiles, attendance, schedules and payroll (these belong to HR profiles, as in the ERP);
 *  - the activity log of what employees changed.
 */
class StaffController extends Controller
{
    use ResolvesOwner;

    private const TIME = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

    private static ?bool $ready = null;

    public function __construct(private StaffAccessService $access) {}

    // ── Roles & permissions ────────────────────────────────────────────────

    /** GET /vendor/staff/roles */
    public function roles(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $vendor = User::find($ownerId);
        $subscription = app(SubscriptionAccessService::class);

        $roles = StaffRole::where('owner_id', $ownerId)->withCount('employees')->orderBy('name')->get()
            ->map(fn (StaffRole $r) => $this->formatRole($r));

        $catalog = collect(ErpModule::MODULE_PERMISSIONS)->map(fn ($actions, $module) => [
            'module' => $module,
            'actions' => $actions,
            'available' => $subscription->canAccess($vendor, SubscriptionPlans::canonicalModule($module)),
        ])->values();

        return response()->json(['success' => true, 'data' => ['roles' => $roles, 'catalog' => $catalog]]);
    }

    /** POST /vendor/staff/roles */
    public function storeRole(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $role = StaffRole::create($this->validateRole($request, $ownerId) + ['owner_id' => $ownerId]);

        return response()->json(['success' => true, 'message' => 'Role created.', 'data' => $this->formatRole($role->loadCount('employees'))], 201);
    }

    /** PUT /vendor/staff/roles/{id} */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $role = StaffRole::where('owner_id', $ownerId)->findOrFail($id);
        $role->update($this->validateRole($request, $ownerId, $role));
        $this->access->syncRole($role);
        $count = $role->employees()->count();

        return response()->json([
            'success' => true,
            'message' => $count ? "Role saved. {$count} " . ($count === 1 ? 'person has' : 'people have') . ' the new permissions.' : 'Role saved.',
            'data' => $this->formatRole($role->loadCount('employees')),
        ]);
    }

    /** DELETE /vendor/staff/roles/{id} */
    public function destroyRole(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $role = StaffRole::where('owner_id', $this->resolveOwnerId($request))->withCount('employees')->findOrFail($id);
        if ($role->employees_count) {
            return response()->json(['success' => false, 'message' => 'Move the people in this role to another role first.'], 422);
        }
        $role->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    // ── Employees: team accounts + HR profiles ─────────────────────────────

    /** GET /vendor/staff/employees */
    public function employees(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $vendor = User::find($ownerId);

        $accounts = Employee::forOwner($ownerId)->with('staffRole:id,name,color')->orderBy('name')->get();
        $profiles = EmployeeInfo::forOwner($ownerId)->with('createdBy:id,name')->orderBy('first_name')->get();
        $links = $this->accountProfileLinks($accounts, $profiles);

        $lastActive = StaffActivityLog::where('owner_id', $ownerId)
            ->selectRaw('employee_id, MAX(created_at) as last_at')->groupBy('employee_id')->pluck('last_at', 'employee_id');
        $todayAttendance = EmployeeAttendance::forOwner($ownerId)->whereDate('attendance_date', today())->get()->keyBy('employee_id');
        $limit = app(ResourceLimitService::class)->summary($vendor)['staff_employees'] ?? null;
        $subscription = app(SubscriptionAccessService::class);

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'initials' => $e->initials,
                    'username' => $e->username,
                    'email' => $e->email,
                    'phone' => $e->phone,
                    'address' => $e->address,
                    'title' => $e->role,
                    'status' => $e->status,
                    'joining_date' => $e->joining_date?->toDateString(),
                    'role' => $e->staffRole ? ['id' => $e->staffRole->id, 'name' => $e->staffRole->name, 'color' => $e->staffRole->color] : null,
                    'profile_id' => $links['account_to_profile'][$e->id] ?? null,
                    'last_active_at' => isset($lastActive[$e->id]) ? Carbon::parse($lastActive[$e->id])->toIso8601String() : null,
                ])->values(),
                'profiles' => $profiles->map(function (EmployeeInfo $p) use ($links, $accounts, $todayAttendance) {
                    $accountId = $links['profile_to_account'][$p->id] ?? null;
                    $account = $accountId ? $accounts->firstWhere('id', $accountId) : null;
                    $att = $todayAttendance->get($p->id);

                    return [
                        'id' => $p->id,
                        'name' => $p->full_name,
                        'initials' => $this->initials($p->full_name),
                        'avatar' => $p->avatar_url,
                        'position' => $p->position,
                        'department' => $p->department,
                        'employment_type' => $p->employment_type,
                        'employment_status' => $p->employment_status,
                        'date_hired' => $p->date_hired ? Carbon::parse($p->date_hired)->toDateString() : null,
                        'email' => $p->work_email ?: $p->personal_email,
                        'mobile' => $p->mobile_number,
                        'salary' => $p->basic_salary !== null ? (float) $p->basic_salary : null,
                        'salary_type' => $p->salary_type,
                        'usual_hours' => $this->usualHours($p),
                        'rest_days' => $this->restDays($p),
                        'account' => $account ? ['id' => $account->id, 'name' => $account->name, 'role' => $account->staffRole?->name] : null,
                        'today' => $att ? ['time_in' => $this->hm($att->time_in), 'time_out' => $this->hm($att->time_out)] : null,
                        'added_by' => $p->added_by,
                    ];
                })->values(),
                'limit' => $limit,
                'hr_available' => $subscription->canAccess($vendor, 'employees'),
            ],
        ]);
    }

    /** POST /vendor/staff/employees — a new team account that can sign in to the ERP. */
    public function storeEmployee(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            // The email is also the username they sign in with
            'email' => 'required|email|max:255|unique:employees,email|unique:employees,username',
            // The same password rule as everyone else
            'password' => 'required|string|min:8|max:100|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            'title' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'joining_date' => 'required|date',
            'staff_role_id' => ['required', Rule::exists('staff_roles', 'id')->where('owner_id', $ownerId)],
        ], [
            'email.unique' => 'Someone already uses that email.',
            'password.regex' => 'Use at least one uppercase letter, one lowercase letter and a number.',
            'staff_role_id.required' => 'Choose a role.',
        ]);

        $employee = DB::transaction(function () use ($data, $ownerId) {
            $employee = Employee::create([
                'owner_id' => $ownerId,
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'joining_date' => $data['joining_date'],
                'status' => 'Active',
            ]);
            $employee->forceFill(['staff_role_id' => $data['staff_role_id']])->save();
            $this->access->syncEmployee($employee);

            return $employee;
        });

        return response()->json(['success' => true, 'message' => "{$employee->name} can now sign in to the ERP."], 201);
    }

    /** PUT /vendor/staff/employees/{id} */
    public function updateEmployee(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $employee = Employee::forOwner($ownerId)->findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee->id)],
            'title' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'joining_date' => 'required|date',
            'status' => 'required|in:Active,On Leave,Resign',
            'staff_role_id' => ['required', Rule::exists('staff_roles', 'id')->where('owner_id', $ownerId)],
            'password' => 'nullable|string|min:8|max:100|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        ], [
            'email.unique' => 'Someone already uses that email.',
            'password.regex' => 'Use at least one uppercase letter, one lowercase letter and a number.',
            'staff_role_id.required' => 'Choose a role.',
        ]);

        DB::transaction(function () use ($employee, $data) {
            $employee->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'joining_date' => $data['joining_date'],
                'status' => $data['status'],
            ]);
            // A new password, or leaving the shop, signs them out everywhere
            if (! empty($data['password'])) {
                $employee->password = Hash::make($data['password']);
                $employee->api_token = null;
            }
            if ($data['status'] === 'Resign') {
                $employee->api_token = null;
            }
            $employee->forceFill(['staff_role_id' => $data['staff_role_id']])->save();
            $this->access->syncEmployee($employee);
        });

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }

    // ── Attendance ─────────────────────────────────────────────────────────

    /** GET /vendor/staff/attendance?from&to&employee_id */
    public function attendance(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'employee_id' => 'nullable|integer',
        ]);
        $from = Carbon::parse($data['from'] ?? now()->startOfWeek())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now())->endOfDay();
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => 'Choose a range of a year or less.']);
        }

        $profiles = EmployeeInfo::forOwner($ownerId)->orderBy('first_name')->get();
        $rows = EmployeeAttendance::forOwner($ownerId)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->when($data['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->orderByDesc('attendance_date')->orderByDesc('time_in')->get();
        $shifts = $this->shiftsBetween($ownerId, $from, $to);
        $byId = $profiles->keyBy('id');
        $calculator = app(AttendanceCalculator::class);
        $grace = app(AttendanceLocationService::class)->settings($ownerId)->late_grace_minutes;

        $records = $rows->map(function ($a) use ($shifts, $byId, $calculator, $grace) {
            $date = Carbon::parse($a->attendance_date)->toDateString();
            $profile = $byId->get($a->employee_id);
            $expected = $profile ? $this->expectedHours($profile, $date, $shifts->get($a->employee_id . '|' . $date)) : null;
            $facts = $calculator->facts($a, $expected, $grace);

            return [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'employee' => $profile?->full_name ?? 'Former employee',
                'date' => $date,
                'time_in' => $this->hm($a->time_in),
                'time_out' => $this->hm($a->time_out),
                'hours' => $a->total_hours !== null ? (float) $a->total_hours : null,
                'status' => $a->time_out ? 'complete' : ($date < today()->toDateString() ? 'missing_out' : 'open'),
                'late_minutes' => $facts['late_minutes'],
                'undertime_minutes' => $facts['undertime_minutes'],
                'expected' => $expected ? "{$expected['start']}–{$expected['end']}" : null,
                'verified' => str_contains((string) $a->notes, 'Face verified'),
                'by_owner' => str_contains((string) $a->notes, 'by the shop owner'),
                'note' => $this->cleanNote($a->notes),
                'source' => $a->source,
                'review_status' => $a->review_status,
                'review_reasons' => $a->review_reasons ?? [],
                'reviewed_by' => $a->reviewed_by,
                'photos' => ['time_in' => count($a->time_in_evidence['photos'] ?? []), 'time_out' => count($a->time_out_evidence['photos'] ?? [])],
                'overtime_minutes' => $a->overtime_minutes,
                'overtime_status' => $a->overtime_status,
                'overtime_approved_minutes' => $a->overtime_approved_minutes,
            ];
        })->values();

        $today = today()->toDateString();
        $todayRows = $records->where('date', $today);
        $todayShifts = $this->shiftsBetween($ownerId, today(), today());
        $working = $profiles->filter(fn ($p) => $this->isActive($p) && $this->expectedHours($p, $today, $todayShifts->get($p->id . '|' . $today)));
        $inToday = $todayRows->pluck('employee_id')->unique();

        return response()->json([
            'success' => true,
            'data' => [
                'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'records' => $records,
                'employees' => $profiles->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name])->values(),
                'stats' => [
                    'present_today' => $inToday->count(),
                    'still_in' => $todayRows->where('status', 'open')->count(),
                    'expected_today' => $working->count(),
                    'not_in_yet' => $working->pluck('id')->diff($inToday)->count(),
                    'hours' => round((float) $records->sum('hours'), 2),
                    'late' => $records->where('late_minutes', '>', 0)->count(),
                    'undertime' => $records->where('undertime_minutes', '>', 0)->count(),
                    'missing_out' => $records->where('status', 'missing_out')->count(),
                    'needs_review' => $records->where('review_status', 'pending')->count(),
                    'overtime_pending' => $records->where('overtime_status', 'pending')->count(),
                    // Absences need a day-by-day pass, so only for ranges up to three months
                    'absent' => $from->diffInDays($to) <= AttendanceCalculator::MAX_RANGE_DAYS
                        ? $calculator->report($ownerId, $from, $to, $data['employee_id'] ?? null)['totals']['absent']
                        : null,
                ],
            ],
        ]);
    }

    /** POST /vendor/staff/attendance — a record the owner adds (e.g. a forgotten time-in). */
    public function storeAttendance(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $data = $this->validateAttendance($request, $ownerId, true);
        $exists = EmployeeAttendance::forOwner($ownerId)->where('employee_id', $data['employee_id'])
            ->whereDate('attendance_date', $data['date'])->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'There is already a record for that day. Edit it instead.'], 422);
        }
        $date = Carbon::parse($data['date']);
        $record = EmployeeAttendance::forOwner($ownerId)->make([
            'owner_id' => $ownerId,
            'employee_id' => $data['employee_id'],
            'attendance_date' => $date->toDateString(),
            'day' => $date->format('l'),
            'month' => (int) $date->format('n'),
            'year' => (int) $date->format('Y'),
            'time_in' => $data['time_in'] . ':00',
            'time_out' => ! empty($data['time_out']) ? $data['time_out'] . ':00' : null,
            'total_hours' => $this->hours($data['time_in'], $data['time_out'] ?? null),
            'status' => ! empty($data['time_out']) ? 'complete' : 'incomplete',
            'notes' => 'Added by the shop owner' . (! empty($data['note']) ? ' | ' . $data['note'] : ''),
            'source' => 'owner',
        ]);
        // Times the owner enters themselves count as approved, overtime included
        app(AttendanceCalculator::class)->applyOvertime($ownerId, $record, EmployeeInfo::forOwner($ownerId)->findOrFail($data['employee_id']), 'Shop owner');
        $record->save();

        return response()->json(['success' => true, 'message' => 'Attendance added.'], 201);
    }

    /** PUT /vendor/staff/attendance/{id} — correct the times. */
    public function updateAttendance(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $record = EmployeeAttendance::forOwner($ownerId)->findOrFail($id);
        $data = $this->validateAttendance($request, $ownerId, false);

        $notes = str_contains((string) $record->notes, 'by the shop owner')
            ? $record->notes
            : trim(($record->notes ? $record->notes . ' | ' : '') . 'Corrected by the shop owner');
        if (! empty($data['note'])) {
            $notes .= ' | ' . $data['note'];
        }
        $record->fill([
            'time_in' => $data['time_in'] . ':00',
            'time_out' => ! empty($data['time_out']) ? $data['time_out'] . ':00' : null,
            'total_hours' => $this->hours($data['time_in'], $data['time_out'] ?? null),
            'status' => ! empty($data['time_out']) ? 'complete' : 'incomplete',
            'notes' => mb_substr($notes, 0, 1000),
            'source' => 'owner',
        ] + ($record->review_status === 'pending'
            // The owner fixing a flagged day settles it
            ? ['review_status' => 'approved', 'reviewed_by' => 'Shop owner', 'reviewed_at' => now()]
            : []));
        if ($profile = EmployeeInfo::forOwner($ownerId)->find($record->employee_id)) {
            app(AttendanceCalculator::class)->applyOvertime($ownerId, $record, $profile, 'Shop owner');
        }
        $record->save();

        return response()->json(['success' => true, 'message' => 'Attendance updated.']);
    }

    // ── Schedules ──────────────────────────────────────────────────────────

    /** GET /vendor/staff/schedules?week=YYYY-MM-DD (any day of the week) */
    public function schedules(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate(['week' => 'nullable|date']);
        $start = Carbon::parse($data['week'] ?? now())->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $profiles = EmployeeInfo::forOwner($ownerId)->orderBy('first_name')->get()->filter(fn ($p) => $this->isActive($p))->values();
        $shifts = $this->shiftsBetween($ownerId, $start, $end);
        $links = $this->accountProfileLinks(Employee::forOwner($ownerId)->with('staffRole:id,name,color')->get(), $profiles);

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->toDateString();
        }

        $people = $profiles->map(function (EmployeeInfo $p) use ($days, $shifts, $links) {
            $cells = [];
            foreach ($days as $day) {
                $shift = $shifts->get($p->id . '|' . $day);
                $expected = $this->expectedHours($p, $day, $shift);
                $cells[] = [
                    'date' => $day,
                    'shift_id' => $shift?->id,
                    'source' => $shift ? 'custom' : 'usual',
                    'off' => ! $expected,
                    'start' => $expected['start'] ?? null,
                    'end' => $expected['end'] ?? null,
                    'hours' => $expected ? round($this->shiftHours($expected['start'], $expected['end']), 1) : 0,
                    'label' => $shift?->label,
                    'note' => $shift?->note,
                ];
            }

            return [
                'id' => $p->id,
                'name' => $p->full_name,
                'initials' => $this->initials($p->full_name),
                'avatar' => $p->avatar_url,
                'position' => $p->position,
                'role' => $links['profile_role'][$p->id] ?? null,
                'usual_hours' => $this->usualHours($p),
                'rest_days' => $this->restDays($p),
                'week_hours' => round(collect($cells)->sum('hours'), 1),
                'days' => $cells,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'week_start' => $start->toDateString(),
                'days' => $days,
                'people' => $people->values(),
                'total_hours' => round($people->sum('week_hours'), 1),
            ],
        ]);
    }

    /** POST /vendor/staff/shifts — set someone's hours (or a day off) on one or more days. */
    public function saveShift(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')],
            'dates' => 'required|array|min:1|max:31',
            'dates.*' => 'date',
            'is_off' => 'boolean',
            'start_time' => ['required_unless:is_off,true', 'nullable', self::TIME],
            'end_time' => ['required_unless:is_off,true', 'nullable', self::TIME],
            'label' => 'nullable|string|max:40',
            'note' => 'nullable|string|max:255',
        ], [
            'start_time.required_unless' => 'Enter a start time.',
            'end_time.required_unless' => 'Enter an end time.',
        ]);
        $off = (bool) ($data['is_off'] ?? false);
        if (! $off && $data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages(['end_time' => 'The shift must end after it starts.']);
        }

        $dates = array_unique(array_map(fn ($d) => Carbon::parse($d)->toDateString(), $data['dates']));
        foreach ($dates as $date) {
            StaffShift::updateOrCreate(
                ['employee_id' => $data['employee_id'], 'shift_date' => $date],
                [
                    'owner_id' => $ownerId,
                    'is_off' => $off,
                    'start_time' => $off ? null : $data['start_time'],
                    'end_time' => $off ? null : $data['end_time'],
                    'label' => $data['label'] ?? null,
                    'note' => $data['note'] ?? null,
                    // Custom hours take the place of any planned shift or replacement that day
                    'shift_template_id' => null,
                    'status' => 'scheduled',
                    'replaced_reason' => null,
                    'replaced_note' => null,
                    'replaced_by_employee_id' => null,
                    'assigned_by' => 'Shop owner',
                ],
            );
        }
        $n = count($dates);

        return response()->json(['success' => true, 'message' => $n === 1 ? 'Schedule saved.' : "Saved for {$n} days."]);
    }

    /** DELETE /vendor/staff/shifts/{id} — back to their usual hours. */
    public function destroyShift(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        StaffShift::where('owner_id', $this->resolveOwnerId($request))->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Back to their usual hours.']);
    }

    /** POST /vendor/staff/shifts/copy-week — repeat last week's changes in this week. */
    public function copyWeek(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate(['week' => 'required|date']);
        $start = Carbon::parse($data['week'])->startOfWeek();
        $previous = StaffShift::where('owner_id', $ownerId)
            ->onDays($start->copy()->subWeek()->toDateString(), $start->copy()->subDay()->toDateString())->get();

        if ($previous->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Last week only had usual hours, so there is nothing to copy.'], 422);
        }
        $copied = 0;
        foreach ($previous as $s) {
            $made = StaffShift::firstOrCreate(
                ['employee_id' => $s->employee_id, 'shift_date' => $s->shift_date->copy()->addWeek()->toDateString()],
                ['owner_id' => $ownerId, 'is_off' => $s->is_off, 'start_time' => $s->start_time, 'end_time' => $s->end_time, 'label' => $s->label, 'note' => $s->note],
            );
            $copied += $made->wasRecentlyCreated ? 1 : 0;
        }

        return response()->json(['success' => true, 'message' => $copied ? "Copied {$copied} " . ($copied === 1 ? 'change' : 'changes') . ' from last week.' : 'This week already has those changes.']);
    }

    // ── Activity ───────────────────────────────────────────────────────────

    /** GET /vendor/staff/activity?employee_id&area&from&to&page */
    public function activity(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'employee_id' => 'nullable|integer',
            'area' => 'nullable|string|max:40',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
        ]);

        $page = StaffActivityLog::where('owner_id', $ownerId)
            ->when($data['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($data['area'] ?? null, fn ($q, $a) => $q->where('area', $a))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => [
                'entries' => collect($page->items())->map(fn (StaffActivityLog $l) => [
                    'key' => md5('staff-activity' . $l->id),
                    'employee_id' => $l->employee_id,
                    'who' => $l->actor_name ?? 'Former employee',
                    'action' => $l->action,
                    'area' => $l->area,
                    'at' => $l->created_at?->toIso8601String(),
                ]),
                'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
                'areas' => StaffActivityLog::where('owner_id', $ownerId)->distinct()->orderBy('area')->pluck('area'),
                'employees' => Employee::forOwner($ownerId)->withTrashed()->orderBy('name')->get(['id', 'name']),
                'today' => StaffActivityLog::where('owner_id', $ownerId)->where('created_at', '>=', today())->count(),
            ],
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('staff_roles') && Schema::hasTable('staff_shifts')
            && Schema::hasTable('staff_activity_logs') && Schema::hasColumn('employees', 'staff_role_id');
    }

    private function notReady(): ?JsonResponse
    {
        return self::ready() ? null : response()->json([
            'success' => false,
            'code' => 'staff_setting_up',
            'message' => 'Staff & operations is still being set up. It will be ready after the next update.',
        ], 503);
    }

    private function validateRole(Request $request, int $ownerId, ?StaffRole $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', Rule::unique('staff_roles', 'name')->where('owner_id', $ownerId)->ignore($role?->id)],
            'description' => 'nullable|string|max:255',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'permissions' => 'present|array',
            'permissions.*' => 'string|max:60',
        ], ['name.unique' => 'You already have a role with that name.']);

        $permissions = StaffRole::normalize($data['permissions']);

        // Areas outside the plan can't be newly granted (a role keeps what it already had)
        $vendor = User::find($ownerId);
        $subscription = app(SubscriptionAccessService::class);
        $locked = collect($permissions)->filter(function ($key) use ($vendor, $subscription, $role) {
            [$module] = explode('.', $key, 2);

            return ! $subscription->canAccess($vendor, SubscriptionPlans::canonicalModule($module))
                && ! in_array($key, $role?->permissions ?? [], true);
        });
        if ($locked->isNotEmpty()) {
            throw ValidationException::withMessages(['permissions' => 'Some of these areas aren\'t in your plan yet.']);
        }

        return ['name' => trim($data['name']), 'description' => $data['description'] ?? null, 'color' => $data['color'] ?? null, 'permissions' => $permissions];
    }

    private function formatRole(StaffRole $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'description' => $r->description,
            'color' => $r->color,
            'permissions' => $r->permissions ?? [],
            'members' => (int) ($r->employees_count ?? 0),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }

    /** Sign-in accounts and HR profiles are separate records; they belong together when the emails match. */
    private function accountProfileLinks(Collection $accounts, Collection $profiles): array
    {
        $byEmail = $accounts->filter(fn ($a) => $a->email)->keyBy(fn ($a) => strtolower(trim($a->email)));
        $links = ['account_to_profile' => [], 'profile_to_account' => [], 'profile_role' => []];
        foreach ($profiles as $p) {
            foreach ([$p->work_email, $p->personal_email] as $email) {
                $account = $email ? $byEmail->get(strtolower(trim($email))) : null;
                if ($account && ! isset($links['account_to_profile'][$account->id])) {
                    $links['account_to_profile'][$account->id] = $p->id;
                    $links['profile_to_account'][$p->id] = $account->id;
                    $links['profile_role'][$p->id] = $account->staffRole ? ['name' => $account->staffRole->name, 'color' => $account->staffRole->color] : null;
                    break;
                }
            }
        }

        return $links;
    }

    private function shiftsBetween(int $ownerId, Carbon $from, Carbon $to): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return StaffShift::where('owner_id', $ownerId)
            ->onDays($from->toDateString(), $to->toDateString())->get()
            ->keyBy(fn ($s) => $s->employee_id . '|' . $s->shift_date->toDateString());
    }

    /** The hours someone should work on a day: a set shift, else their usual hours unless it's a rest day. Null means off. */
    private function expectedHours(EmployeeInfo $p, string $date, ?StaffShift $shift): ?array
    {
        return app(AttendanceCalculator::class)->expected($p, $date, $shift);
    }

    private function usualHours(EmployeeInfo $p): ?string
    {
        return $p->shift_start && $p->shift_end ? $this->hm($p->shift_start) . '–' . $this->hm($p->shift_end) : null;
    }

    /** "Sunday, Saturday" → ["Saturday", "Sunday"] in week order */
    private function restDays(EmployeeInfo $p): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $raw = is_array($p->rest_days) ? $p->rest_days : preg_split('/[\s,;]+/', (string) $p->rest_days, -1, PREG_SPLIT_NO_EMPTY);
        $picked = collect($raw)->map(fn ($d) => ucfirst(strtolower(trim($d))))->all();

        return array_values(array_filter($days, fn ($d) => in_array($d, $picked, true) || in_array(substr($d, 0, 3), $picked, true)));
    }

    private function isActive(EmployeeInfo $p): bool
    {
        return ! in_array(strtolower((string) $p->employment_status), ['resigned', 'terminated', 'inactive', 'resign'], true);
    }

    private function validateAttendance(Request $request, int $ownerId, bool $new): array
    {
        $data = $request->validate(array_filter([
            'employee_id' => $new ? ['required', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')] : null,
            'date' => $new ? 'required|date|before_or_equal:today' : null,
            'time_in' => ['required', self::TIME],
            'time_out' => ['nullable', self::TIME],
            'note' => 'nullable|string|max:200',
        ]), [
            'date.before_or_equal' => "You can't add attendance for a future day.",
            'employee_id.required' => 'Choose who this is for.',
        ]);
        if (! empty($data['time_out']) && $data['time_out'] <= $data['time_in']) {
            throw ValidationException::withMessages(['time_out' => 'Time out must be after time in.']);
        }

        return $data;
    }

    private function hours(string $in, ?string $out): ?float
    {
        if (! $out) {
            return null;
        }

        return round(Carbon::createFromFormat('H:i', substr($in, 0, 5))->diffInMinutes(Carbon::createFromFormat('H:i', substr($out, 0, 5))) / 60, 2);
    }

    private function shiftHours(string $start, string $end): float
    {
        return max(0, Carbon::parse($start)->diffInMinutes(Carbon::parse($end), false) / 60);
    }

    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function initials(string $name): string
    {
        return strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    }

    /** The scanner's technical notes ("Face verified (78%, challenges …)") left out. */
    private function cleanNote(?string $notes): ?string
    {
        if (! $notes) {
            return null;
        }
        $parts = collect(explode('|', $notes))->map(fn ($p) => trim($p))
            ->reject(fn ($p) => $p === '' || str_starts_with($p, 'Face verified') || str_starts_with($p, 'Time out face verified'))
            ->values();

        return $parts->isEmpty() ? null : $parts->implode(' · ');
    }
}
