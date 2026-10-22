<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeInfo;
use App\Models\PayComponent;
use App\Models\Payroll;
use App\Models\SalaryRecord;
use App\Services\PayrollService;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Payroll for HR (payroll module), Finance (payroll_requests module) and the shop owner.
 * HR creates and revises; Finance approves, sends back and marks paid; nobody approves their own.
 * Every endpoint is scoped to the signed-in person's shop.
 */
class PayrollController extends Controller
{
    use ScopesOwner;

    public function __construct(private PayrollService $payrollService) {}

    // ── Lists and payslips ────────────────────────────────────────────────

    /** GET /payroll and GET /payroll/finance-requests */
    public function index(Request $request): JsonResponse
    {
        $payrolls = $this->payrollService->getPayrolls($this->getOwnerId(), $this->filters($request) + [
            'per_page' => min((int) ($request->per_page ?? 30), 200),
            'page' => $request->page ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => collect($payrolls->items())->map(fn (Payroll $p) => $this->row($p))->values(),
            'pagination' => [
                'total' => $payrolls->total(),
                'per_page' => $payrolls->perPage(),
                'current_page' => $payrolls->currentPage(),
                'last_page' => $payrolls->lastPage(),
                'from' => $payrolls->firstItem(),
                'to' => $payrolls->lastItem(),
            ],
        ]);
    }

    /** GET /payroll/summary and GET /payroll/finance-summary */
    public function summary(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->payrollService->getPayrollSummary($this->getOwnerId(), $this->filters($request))]);
    }

    /** GET /payroll/{id} — the payslip */
    public function show(int $id): JsonResponse
    {
        $payroll = Payroll::withoutGlobalScopes()->where('owner_id', $this->getOwnerId())->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->payrollService->payslip($this->getOwnerId(), $payroll)]);
    }

    /** GET /payroll/people — who can be paid, and what's missing for those who can't */
    public function people(): JsonResponse
    {
        $people = EmployeeInfo::forOwner($this->getOwnerId())->orderBy('first_name')->get()
            ->filter(fn ($p) => ! in_array(strtolower((string) $p->employment_status), ['inactive', 'resigned', 'terminated'], true))
            ->map(fn (EmployeeInfo $p) => [
                'id' => $p->id,
                'name' => $p->full_name,
                'position' => $p->position,
                'salary_type' => $p->salary_type,
                'basic_salary' => $p->basic_salary !== null ? (float) $p->basic_salary : null,
                'problem' => $this->payrollService->payConfigProblem($p),
            ])->values();

        return response()->json(['success' => true, 'data' => $people]);
    }

    // ── Creating ──────────────────────────────────────────────────────────

    /** POST /payroll/preview {employee_id, period_start, period_end, include_contributions, items[]} */
    public function preview(Request $request): JsonResponse
    {
        $data = $this->validateRun($request);
        $result = $this->payrollService->previewPayroll($this->getOwnerId(), $data['employee_id'], $data['period_start'], $data['period_end'], (bool) ($data['include_contributions'] ?? false), $data['items'] ?? []);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /** POST /payroll */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRun($request);
        $result = $this->payrollService->generatePayroll($this->getOwnerId(), $data['employee_id'], $data['period_start'], $data['period_end'], $data['notes'] ?? null, (bool) ($data['include_contributions'] ?? false), $data['items'] ?? [], $request->user());

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    /** POST /payroll/batch-preview {employee_ids[], period_start, period_end, include_contributions, items: {id: [...]}} */
    public function batchPreview(Request $request): JsonResponse
    {
        $data = $this->validateBatch($request);
        $rows = collect($data['employee_ids'])->map(function ($id) use ($data) {
            $r = $this->payrollService->previewPayroll($this->getOwnerId(), (int) $id, $data['period_start'], $data['period_end'], (bool) ($data['include_contributions'] ?? false), $data['items'][$id] ?? []);

            return $r['success'] ? ['ok' => true] + $r['data'] : ['ok' => false, 'employee_id' => (int) $id, 'message' => $r['message']];
        })->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /payroll/batch — create payrolls for several people at once */
    public function batch(Request $request): JsonResponse
    {
        $data = $this->validateBatch($request);
        $made = 0;
        $failed = [];
        foreach ($data['employee_ids'] as $id) {
            $r = $this->payrollService->generatePayroll($this->getOwnerId(), (int) $id, $data['period_start'], $data['period_end'], $data['notes'] ?? null, (bool) ($data['include_contributions'] ?? false), $data['items'][$id] ?? [], $request->user());
            $r['success'] ? $made++ : $failed[] = ['employee_id' => (int) $id, 'message' => $r['message']];
        }

        return response()->json([
            'success' => $made > 0,
            'message' => $made
                ? "{$made} " . ($made === 1 ? 'payroll' : 'payrolls') . ' created and sent to Finance.' . ($failed ? ' ' . count($failed) . ' couldn\'t be made.' : '')
                : 'No payrolls were made.',
            'data' => ['created' => $made, 'failed' => $failed],
        ], $made ? 201 : 422);
    }

    /** PUT /payroll/{id} {items[], notes, include_contributions} — recalculate and send to Finance again */
    public function revise(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->itemRules() + [
            'notes' => 'nullable|string|max:1000',
            'include_contributions' => 'nullable|boolean',
        ]);
        $payroll = Payroll::withoutGlobalScopes()->where('owner_id', $this->getOwnerId())->findOrFail($id);
        $result = $this->payrollService->revisePayroll($this->getOwnerId(), $payroll, $data['items'] ?? [], $data['notes'] ?? null, (bool) ($data['include_contributions'] ?? false), $request->user());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /** DELETE /payroll/{id} — only payrolls that weren't approved */
    public function destroy(int $id): JsonResponse
    {
        $payroll = Payroll::withoutGlobalScopes()->where('owner_id', $this->getOwnerId())->findOrFail($id);
        if (! in_array($payroll->status, ['pending', 'rejected'], true)) {
            return response()->json(['success' => false, 'message' => 'Approved and paid payrolls are kept for the records.'], 422);
        }
        $payroll->delete();

        return response()->json(['success' => true, 'message' => 'Payroll deleted.']);
    }

    // ── Finance ───────────────────────────────────────────────────────────

    public function financeRequests(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function financeSummary(Request $request): JsonResponse
    {
        return $this->summary($request);
    }

    public function financeApprove(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'finance_notes' => 'nullable|string|max:1000']);
        $result = $this->payrollService->financeApprovePayrolls($this->getOwnerId(), $data['ids'], $data['finance_notes'] ?? null, $request->user());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function financeReject(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'finance_notes' => 'required|string|max:1000'],
            ['finance_notes.required' => 'Say what needs fixing, so HR knows.']);
        $result = $this->payrollService->financeRejectPayrolls($this->getOwnerId(), $data['ids'], $data['finance_notes'], $request->user());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function markAsPaid(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $result = $this->payrollService->markPayrollsAsPaid($this->getOwnerId(), $data['ids'], $request->user());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // ── Recurring pay items and salary history ────────────────────────────

    /** GET /payroll/components?employee_id */
    public function components(Request $request): JsonResponse
    {
        $employeeId = $request->validate(['employee_id' => 'nullable|integer'])['employee_id'] ?? null;
        $rows = PayComponent::where('owner_id', $this->getOwnerId())
            ->when($employeeId, fn ($q, $id) => $q->where('employee_info_id', $id))
            ->orderByDesc('active')->orderBy('kind')->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /payroll/components */
    public function storeComponent(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'employee_info_id' => ['required', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')],
        ] + $this->componentRules());
        $user = $request->user();
        $row = PayComponent::create($data + ['owner_id' => $ownerId, 'created_by' => $user instanceof Employee ? $user->name : 'Shop owner']);

        return response()->json(['success' => true, 'message' => 'Added. It appears on their next payrolls.', 'data' => $row], 201);
    }

    /** PUT /payroll/components/{id} */
    public function updateComponent(Request $request, int $id): JsonResponse
    {
        $row = PayComponent::where('owner_id', $this->getOwnerId())->findOrFail($id);
        $row->update($request->validate($this->componentRules()));

        return response()->json(['success' => true, 'message' => 'Saved. Payrolls already made keep the old amount.', 'data' => $row]);
    }

    /** DELETE /payroll/components/{id} */
    public function destroyComponent(int $id): JsonResponse
    {
        PayComponent::where('owner_id', $this->getOwnerId())->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Removed from future payrolls.']);
    }

    /** GET /payroll/salary-history/{employeeId} */
    public function salaryHistory(int $employeeId): JsonResponse
    {
        $rows = SalaryRecord::where('owner_id', $this->getOwnerId())->where('employee_info_id', $employeeId)
            ->orderByDesc('effective_from')->orderByDesc('id')->get(['basic_salary', 'salary_type', 'effective_from', 'changed_by', 'note']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function row(Payroll $p): array
    {
        return [
            'id' => $p->id,
            'number' => sprintf('PS-%d-%05d', $p->period_start->year, $p->id),
            'employee_id' => $p->employee_id,
            'employee' => $p->employee?->full_name ?? 'Former employee',
            'position' => $p->employee?->position,
            'period_start' => $p->period_start->toDateString(),
            'period_end' => $p->period_end->toDateString(),
            'days_worked' => (int) $p->actual_work_days,
            'expected_days' => (int) $p->expected_work_days,
            'hours' => (float) $p->total_hours_worked,
            'overtime_hours' => (float) ($p->overtime_hours ?? 0),
            'gross' => (float) $p->gross_salary,
            'deductions' => (float) $p->deduction_amount,
            'net' => (float) $p->net_salary,
            'status' => $p->status,
            'created_by' => $p->created_by_name,
            'created_by_employee_id' => $p->created_by_employee_id,
            'approved_by' => $p->approved_by_name,
            'finance_notes' => $p->finance_notes,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => 'nullable|string|max:100',
            'employee_id' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'status' => 'nullable|in:all,pending,approved,rejected,paid',
        ]);
    }

    private function itemRules(): array
    {
        return [
            'items' => 'nullable|array|max:20',
            'items.*.kind' => 'required|in:allowance,bonus,deduction',
            'items.*.label' => 'required|string|max:80',
            'items.*.amount' => 'required|numeric|min:0.01|max:9999999',
        ];
    }

    private function validateRun(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', Rule::exists('employees_info', 'id')->where('owner_id', $this->getOwnerId())->whereNull('deleted_at')],
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'include_contributions' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ] + $this->itemRules(), ['period_end.after_or_equal' => 'The period must end on or after its start.']);
    }

    private function validateBatch(Request $request): array
    {
        $ownerId = $this->getOwnerId();

        return $request->validate([
            'employee_ids' => 'required|array|min:1|max:100',
            'employee_ids.*' => ['integer', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')],
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'include_contributions' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*' => 'array|max:20',
            'items.*.*.kind' => 'required|in:allowance,bonus,deduction',
            'items.*.*.label' => 'required|string|max:80',
            'items.*.*.amount' => 'required|numeric|min:0.01|max:9999999',
        ], ['employee_ids.required' => 'Choose who to pay.', 'period_end.after_or_equal' => 'The period must end on or after its start.']);
    }

    private function componentRules(): array
    {
        return [
            'kind' => 'required|in:allowance,deduction',
            'label' => 'required|string|max:80',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'basis' => 'required|in:per_payroll,per_day',
            'active' => 'boolean',
            'note' => 'nullable|string|max:255',
        ];
    }
}
