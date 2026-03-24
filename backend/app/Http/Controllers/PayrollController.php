<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Traits\ScopesOwner;

class PayrollController extends Controller
{
    use ScopesOwner;
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    // =========================================================================
    // HR ENDPOINTS — scoped to the logged-in user's own payrolls
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search'       => $request->search,
            'employee_id'  => $request->employee_id,
            'month'        => $request->month,
            'year'         => $request->year,
            'status'       => $request->status,
            'period_start' => $request->period_start,
            'period_end'   => $request->period_end,
            'per_page'     => $request->per_page ?? 15,
            'page'         => $request->page ?? 1,
        ];

        $payrolls = $this->payrollService->getPayrolls($this->getOwnerId(), $filters);

        return response()->json([
            'success'    => true,
            'data'       => $payrolls->items(),
            'pagination' => [
                'total'        => $payrolls->total(),
                'per_page'     => $payrolls->perPage(),
                'current_page' => $payrolls->currentPage(),
                'last_page'    => $payrolls->lastPage(),
                'from'         => $payrolls->firstItem(),
                'to'           => $payrolls->lastItem(),
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id'  => 'required|exists:employees_info,id',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->payrollService->previewPayroll(
            $this->getOwnerId(), $request->employee_id, $request->period_start, $request->period_end
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id'  => 'required|exists:employees_info,id',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->payrollService->generatePayroll(
            $this->getOwnerId(), $request->employee_id, $request->period_start, $request->period_end, $request->notes
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->search,
            'month'  => $request->month,
            'year'   => $request->year,
            'status' => $request->status,
        ];

        // If no year specified, defaults to current year in service, or we can just pass it as is
        $summary = $this->payrollService->getPayrollSummary($this->getOwnerId(), $filters);

        return response()->json(['success' => true, 'data' => $summary]);
    }

    public function show(int $id): JsonResponse
    {
        $payroll = \App\Models\Payroll::with('employee')
            ->where('id', $id)
            ->where('owner_id', $this->getOwnerId())
            ->first();

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $payroll]);
    }

    public function destroy(int $id): JsonResponse
    {
        $payroll = \App\Models\Payroll::where('id', $id)
            ->where('owner_id', $this->getOwnerId())
            ->first();

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll not found'], 404);
        }

        $payroll->delete();

        return response()->json(['success' => true, 'message' => 'Payroll deleted successfully']);
    }

    /**
     * HR marks Finance-approved payrolls as paid → approved → paid
     */
    public function markAsPaid(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:payrolls,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // No owner_id filter — payrolls were approved by Finance (different user)
        $result = $this->payrollService->markPayrollsAsPaid($request->ids);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // =========================================================================
    // FINANCE ENDPOINTS
    //
    // *** ROOT CAUSE FIX ***
    // Finance is a DIFFERENT user account than HR.
    // HR saves payrolls with owner_id = HR's auth()->id() (e.g. user #5).
    // If Finance queries with owner_id = Finance's auth()->id() (e.g. user #8),
    // they get ZERO results because no payroll has owner_id = 8.
    //
    // Solution: Finance endpoints call dedicated service methods that do NOT
    // filter by owner_id at all — they query ALL payrolls by status only.
    // =========================================================================

    /**
     * Finance: Get all pending payrolls (no owner_id scope)
     */
    public function financeRequests(Request $request): JsonResponse
    {
        $filters = [
            'search'   => $request->search,
            'month'    => $request->month,
            'year'     => $request->year,
            'status'   => $request->status,   // null / '' = no filter (all statuses)
            'per_page' => $request->per_page ?? 50,
            'page'     => $request->page ?? 1,
        ];

        $payrolls = $this->payrollService->getFinancePayrolls($filters);

        return response()->json([
            'success'    => true,
            'data'       => $payrolls->items(),
            'pagination' => [
                'total'        => $payrolls->total(),
                'per_page'     => $payrolls->perPage(),
                'current_page' => $payrolls->currentPage(),
                'last_page'    => $payrolls->lastPage(),
                'from'         => $payrolls->firstItem(),
                'to'           => $payrolls->lastItem(),
            ],
        ]);
    }

    /**
     * Finance: Summary cards (no owner_id scope)
     */
    public function financeSummary(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->search,
            'month'  => $request->month,
            'year'   => $request->year ?? now()->year,
            'status' => $request->status,
        ];

        $summary = $this->payrollService->getFinanceSummary($filters);

        return response()->json(['success' => true, 'data' => $summary]);
    }

    /**
     * Finance: Approve → pending becomes approved (no owner_id scope)
     */
    public function financeApprove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids'           => 'required|array|min:1',
            'ids.*'         => 'integer|exists:payrolls,id',
            'finance_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->payrollService->financeApprovePayrolls($request->ids, $request->finance_notes);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Finance: Reject → pending becomes rejected (no owner_id scope)
     */
    public function financeReject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids'           => 'required|array|min:1',
            'ids.*'         => 'integer|exists:payrolls,id',
            'finance_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->payrollService->financeRejectPayrolls($request->ids, $request->finance_notes);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}