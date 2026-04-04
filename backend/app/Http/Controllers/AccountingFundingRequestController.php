<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AccountingFundingRequestController extends Controller
{
    private const FINANCE_MODULE = 'funding_requests';

    private function getOwnerId(Request $request): ?int
    {
        $user = $request->user();
        if ($user instanceof \App\Models\Employee) return $user->owner_id;
        if ($user instanceof \App\Models\User) return $user->id;
        return null;
    }

    private function requireFinanceAccess(Request $request, string $access = 'view')
    {
        $employee = $request->user();

        if (!$employee instanceof Employee) {
            return response()->json(['message' => 'Only employees can access this endpoint'], 403);
        }

        $allowed = $access === 'edit'
            ? $employee->canEditModule(self::FINANCE_MODULE)
            : $employee->canViewModule(self::FINANCE_MODULE);

        if (!$allowed) {
            return response()->json(['message' => 'You do not have permission to perform this action'], 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        try {
            if ($response = $this->requireFinanceAccess($request, 'view')) {
                return $response;
            }

            /** @var Employee $employee */
            $employee = $request->user();

            $requests = DB::table('funding_requests as fr')
                ->leftJoin('employees as requester', 'fr.requester_id', '=', 'requester.id')
                ->leftJoin('employees as reviewer', 'fr.reviewed_by_employee_id', '=', 'reviewer.id')
                ->where('fr.owner_id', $employee->owner_id)
                ->where('fr.approver_id', $employee->id)
                ->select(
                    'fr.*',
                    'requester.name as submitted_by_name',
                    'requester.email as submitted_by_email',
                    'reviewer.name as reviewed_by_name'
                )
                ->orderBy('fr.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $requests,
            ], 200);
        } catch (Exception $e) {
            Log::error('Finance funding requests error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve requests'], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            if ($response = $this->requireFinanceAccess($request, 'edit')) {
                return $response;
            }

            $validator = Validator::make($request->all(), [
                'approved_quantity' => 'required|numeric|min:0',
                'approved_amount' => 'required|numeric|min:0',
                'finance_remarks' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            /** @var Employee $employee */
            $employee = $request->user();
            $ownerId = $this->getOwnerId($request);

            if (!$ownerId) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $fundingRequest = DB::table('funding_requests')
                ->where('id', $id)
                ->where('owner_id', $ownerId)
                ->where('approver_id', $employee->id)
                ->first();

            if (!$fundingRequest) {
                return response()->json(['message' => 'Funding request not found'], 404);
            }

            if ($fundingRequest->request_status !== 'Pending') {
                return response()->json(['message' => 'Only pending requests can be approved'], 400);
            }

            DB::transaction(function () use ($id, $request, $employee, $ownerId, $fundingRequest) {
                DB::table('funding_requests')
                    ->where('id', $id)
                    ->update([
                        'request_status' => 'Approved',
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                        'reviewed_by_employee_id' => $employee->id,
                        'accounting_decision_at' => now(),
                        'approved_quantity' => $request->approved_quantity,
                        'approved_amount' => $request->approved_amount,
                        'accounting_remarks' => $request->finance_remarks,
                        'rejection_reason' => null,
                        'rejection_notes' => null,
                        'updated_at' => now(),
                    ]);

                $vendorBalance = \App\Models\VendorBalance::forVendor($ownerId);
                $balanceBefore = (float) $vendorBalance->balance;
                $amount = (float) $request->approved_amount;
                $balanceAfter = max(0, $balanceBefore - $amount);

                $vendorBalance->update([
                    'balance' => $balanceAfter,
                    'total_withdrawn' => $vendorBalance->total_withdrawn + $amount,
                ]);

                \App\Models\VendorTransaction::create([
                    'vendor_id' => $ownerId,
                    'order_id' => null,
                    'type' => 'debit',
                    'category' => 'procurement',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => "Procurement: {$fundingRequest->product_name} ({$request->approved_quantity} {$fundingRequest->uom})",
                    'status' => 'paid',
                    'metadata' => [
                        'funding_request_id' => $fundingRequest->finance_request_id,
                        'product_name' => $fundingRequest->product_name,
                        'approved_quantity' => $request->approved_quantity,
                        'approved_amount' => $amount,
                        'approved_by' => $employee->name,
                        'approved_at' => now()->toIso8601String(),
                    ],
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Funding request approved successfully'], 200);
        } catch (Exception $e) {
            Log::error('Approve funding request error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to approve request'], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            if ($response = $this->requireFinanceAccess($request, 'edit')) {
                return $response;
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:255',
                'rejection_notes' => 'required|string|max:500',
                'finance_remarks' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            /** @var Employee $employee */
            $employee = $request->user();
            $ownerId = $this->getOwnerId($request);

            if (!$ownerId) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $fundingRequest = DB::table('funding_requests')
                ->where('id', $id)
                ->where('owner_id', $ownerId)
                ->where('approver_id', $employee->id)
                ->first();

            if (!$fundingRequest) {
                return response()->json(['message' => 'Funding request not found'], 404);
            }

            if ($fundingRequest->request_status !== 'Pending') {
                return response()->json(['message' => 'Only pending requests can be rejected'], 400);
            }

            DB::table('funding_requests')
                ->where('id', $id)
                ->update([
                    'request_status' => 'Rejected',
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'reviewed_by_employee_id' => $employee->id,
                    'accounting_decision_at' => now(),
                    'approved_quantity' => 0,
                    'approved_amount' => 0,
                    'rejection_reason' => $request->rejection_reason,
                    'rejection_notes' => $request->rejection_notes,
                    'accounting_remarks' => $request->finance_remarks,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Funding request rejected',
            ], 200);
        } catch (Exception $e) {
            Log::error('Reject funding request error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to reject request'], 500);
        }
    }
}
