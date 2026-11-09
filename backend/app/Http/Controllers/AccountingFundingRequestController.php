<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FundingRequest;
use App\Services\FundingPaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

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

    private function isShopOwner($user): bool
    {
        return $user instanceof \App\Models\User && $user->role === \App\Models\User::ROLE_VENDOR;
    }

    private function requireFinanceAccess(Request $request, string $access = 'view')
    {
        $employee = $request->user();

        // The shop owner can see every request; deciding stays with the assigned approver
        if ($access === 'view' && $this->isShopOwner($employee)) {
            return null;
        }

        if (!$employee instanceof Employee) {
            return response()->json(['message' => 'Only employees can access this endpoint'], 403);
        }

        $allowed = $employee->hasModulePermission(self::FINANCE_MODULE, $access);

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

            $requests = DB::table('funding_requests as fr')
                ->leftJoin('employees as requester', 'fr.requester_id', '=', 'requester.id')
                ->leftJoin('employees as approver', 'fr.approver_id', '=', 'approver.id')
                ->leftJoin('employees as reviewer', 'fr.reviewed_by_employee_id', '=', 'reviewer.id')
                ->where('fr.owner_id', $this->getOwnerId($request))
                // Drafts stay with Procurement until they're sent
                ->where('fr.request_status', '!=', 'Draft')
                ->select(
                    'fr.*',
                    'requester.name as submitted_by_name',
                    'requester.email as submitted_by_email',
                    'approver.name as approver_name',
                    'approver.email as approver_email',
                    'reviewer.name as reviewed_by_name'
                )
                ->selectSub(
                    DB::table('funding_request_items')->selectRaw('count(*)')->whereColumn('funding_request_items.funding_request_id', 'fr.id'),
                    'items_count'
                )
                ->orderBy('fr.created_at', 'desc')
                ->get();

            // Where each approved request's money stands
            $approvedIds = $requests->where('request_status', FundingRequest::STATUS_APPROVED)->pluck('id');
            if ($approvedIds->isNotEmpty()) {
                $payments = app(FundingPaymentService::class);
                $models = FundingRequest::with(['payments', 'linkedOrder.items', 'linkedOrder.supplier'])->whereIn('id', $approvedIds)->get()->keyBy('id');
                $requests->each(function ($r) use ($models, $payments) {
                    if ($m = $models->get($r->id)) {
                        $state = $payments->state($m);
                        unset($state['payments']);
                        $r->payment = $state;
                    }
                });
            }

            return response()->json([
                'success' => true,
                'data' => $requests,
            ], 200);
        } catch (Exception $e) {
            Log::error('Finance funding requests error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve requests'], 500);
        }
    }

    /** Finance staff who can approve funding, or the shop owner, record payments */
    private function requirePayer(Request $request)
    {
        $user = $request->user();
        if ($this->isShopOwner($user)) {
            return null;
        }

        return $this->requireFinanceAccess($request, 'approve');
    }

    private function moneyRules(): array
    {
        return [
            'payment_method' => 'nullable|in:bank,ewallet',
            'payment_channel' => ['nullable', \Illuminate\Validation\Rule::in(\App\Support\PayMongoChannels::all())],
            'reference' => 'required|string|max:100',
            'paid_on' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:500',
        ];
    }

    private function moneyResponse(callable $action)
    {
        try {
            return response()->json(['success' => true, 'data' => $action()]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Funding request not found'], 404);
        } catch (Throwable $e) {
            Log::error('Funding payment error: ' . $e->getMessage());
            return response()->json(['message' => 'Couldn\'t save the payment. Please try again.'], 500);
        }
    }

    /** POST finance/funding-requests/{id}/pay: money sent to the supplier */
    public function pay(Request $request, FundingPaymentService $payments, $id)
    {
        if ($response = $this->requirePayer($request)) {
            return $response;
        }
        $data = $request->validate(['amount' => 'required|numeric|gt:0|max:100000000'] + $this->moneyRules(), [
            'reference.required' => 'Enter the reference number from the bank or e-wallet.',
            'paid_on.before_or_equal' => 'The payment date can\'t be in the future.',
        ]);

        return $this->moneyResponse(fn () => $payments->pay($this->getOwnerId($request), (int) $id, $data));
    }

    /** POST finance/funding-requests/{id}/paymongo: start paying the supplier through PayMongo Checkout */
    public function payWithPayMongo(Request $request, \App\Services\FundingPayMongoService $paymongo, $id)
    {
        if ($response = $this->requirePayer($request)) {
            return $response;
        }
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0|max:100000000',
            'payment_channel' => ['required', \Illuminate\Validation\Rule::in(\App\Support\PayMongoChannels::all())],
        ], ['payment_channel.in' => 'Choose a bank or e-wallet PayMongo offers.']);

        return $this->moneyResponse(fn () => [
            'checkout_url' => $paymongo->create(
                $this->getOwnerId($request),
                (int) $id,
                (float) $data['amount'],
                $data['payment_channel'],
                \App\Models\PurchaseOrderEvent::nameOf($request->user()),
            )->checkout_url,
        ]);
    }

    /**
     * GET /api/funding-payments/callback: PayMongo sends Finance back here.
     * The link proves nothing by itself; the payment is looked up at PayMongo.
     */
    public function payMongoCallback(Request $request, \App\Services\FundingPayMongoService $paymongo)
    {
        $checkout = \App\Models\FundingPaymentCheckout::find($request->integer('checkout_id'));
        $state = 'cancelled';
        if ($checkout && $request->boolean('success')) {
            $state = $paymongo->confirm($checkout) ? 'paid' : ($checkout->fresh()->status === 'needs_review' ? 'review' : 'pending');
        } elseif ($checkout) {
            $paymongo->cancel($checkout);
        }

        $frontend = rtrim((string) config('app.frontend_url', ''), '/');
        if ($frontend !== '' && !preg_match('#^https?://#i', $frontend)) {
            $frontend = 'https://' . ltrim($frontend, '/');
        }
        $path = '/' . ltrim((string) config('app.frontend_finance_funding_path'), '/');

        return redirect($frontend . $path . '?funding_payment=' . $state . '&request=' . urlencode((string) $checkout?->funding_request_id));
    }

    /** POST finance/funding-requests/{id}/supplier-credit: what a supplier owes is refunded or kept as credit */
    public function settleSupplierCredit(Request $request, FundingPaymentService $payments, $id)
    {
        if ($response = $this->requirePayer($request)) {
            return $response;
        }
        $refund = $request->input('outcome') === 'refund';
        $rules = ['outcome' => 'required|in:refund,credit'] + $this->moneyRules();
        $rules['amount'] = $refund ? 'required|numeric|gt:0' : 'nullable';
        $rules['reference'] = $refund ? 'required|string|max:100' : 'nullable|string|max:100';
        $rules['paid_on'] = $refund ? 'required|date|before_or_equal:today' : 'nullable|date';
        $rules['note'] = $refund ? 'nullable|string|max:500' : 'required|string|max:500';
        $data = $request->validate($rules, [
            'note.required' => 'Say how the credit will be used, e.g. "Taken off the next order".',
            'reference.required' => 'Enter the reference number of the refund.',
        ]);

        return $this->moneyResponse(fn () => $payments->settleCredit($this->getOwnerId($request), (int) $id, $data));
    }

    public function approve(Request $request, $id)
    {
        try {
            if ($response = $this->requireFinanceAccess($request, 'approve')) {
                return $response;
            }

            $validator = Validator::make($request->all(), [
                'approved_quantity' => 'required|numeric|min:0',
                'approved_amount' => 'required|numeric|min:0',
                'finance_remarks' => 'nullable|string|max:500',
                // How much of each product is approved; left out, everything asked for is
                'items' => 'nullable|array',
                'items.*.id' => 'required|integer',
                'items.*.approved_quantity' => 'required|integer|min:0',
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

            $fundingRequest = FundingRequest::query()
                ->where('id', $id)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$fundingRequest) {
                return response()->json(['message' => 'Funding request not found'], 404);
            }

            if ((int) $fundingRequest->owner_id !== (int) $employee->owner_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ((int) $fundingRequest->approver_id !== (int) $employee->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the assigned approver can approve this request',
                ], 403);
            }

            if ($fundingRequest->request_status === FundingRequest::STATUS_APPROVED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Funding request already approved',
                    'data' => $fundingRequest,
                ], 400);
            }

            if ($fundingRequest->request_status !== FundingRequest::STATUS_PENDING) {
                return response()->json(['message' => 'Only pending requests can be approved'], 400);
            }

            $fundingRequest = DB::transaction(function () use ($id, $request, $employee, $ownerId) {
                /** @var FundingRequest $lockedFundingRequest */
                $lockedFundingRequest = FundingRequest::query()
                    ->where('id', $id)
                    ->where('owner_id', $ownerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedFundingRequest->request_status !== FundingRequest::STATUS_PENDING) {
                    throw new \RuntimeException('Only pending requests can be approved');
                }

                if ((int) $lockedFundingRequest->approver_id !== (int) $employee->id) {
                    throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                        403,
                        'Only the assigned approver can approve this request'
                    );
                }

                $decisionAt = now();
                $amount = (float) $request->approved_amount;
                $approvedQuantity = (float) $request->approved_quantity;

                // Per-product amounts: never more than was asked for
                $lines = $lockedFundingRequest->items()->get()->keyBy('id');
                if ($lines->isNotEmpty()) {
                    $given = collect($request->input('items', []))->keyBy(fn ($i) => (int) $i['id']);
                    foreach ($given->keys() as $lineId) {
                        if (!$lines->has($lineId)) {
                            throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'One of the products isn\'t on this request.');
                        }
                    }
                    $approvedQuantity = 0;
                    foreach ($lines as $line) {
                        $qty = $given->has($line->id) ? (int) $given[$line->id]['approved_quantity'] : (int) $line->quantity;
                        if ($qty > (int) $line->quantity) {
                            throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, "You can't approve more {$line->product_name} than was asked for.");
                        }
                        $line->update(['approved_quantity' => $qty]);
                        $approvedQuantity += $qty;
                    }
                    if ($approvedQuantity <= 0) {
                        throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'Approve at least one product, or reject the request.');
                    }
                }

                // The budget: more than ₱0, never more than what was asked, and
                // never more than the approved products plus the delivery fee
                if ($amount <= 0) {
                    throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'Enter a budget above ₱0, or reject the request.');
                }
                $extras = (float) $lockedFundingRequest->logistics_cost + (float) $lockedFundingRequest->tax_vat_estimate;
                $ceiling = $lines->isNotEmpty()
                    ? $lines->sum(fn ($l) => (int) $l->approved_quantity * (float) $l->unit_cost) + $extras
                    : (float) $lockedFundingRequest->estimated_total_cost;
                $ceiling = round(min($ceiling, (float) $lockedFundingRequest->estimated_total_cost ?: $ceiling), 2);
                if (round($amount, 2) > $ceiling) {
                    throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, sprintf('The budget can be up to ₱%s: the approved products plus the delivery fee.', number_format($ceiling, 2)));
                }

                // Approving sets the budget. Nothing is paid yet: Finance pays the
                // supplier later, when their payment terms say (see FundingPaymentService)
                $lockedFundingRequest->update([
                    'request_status' => FundingRequest::STATUS_APPROVED,
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'reviewed_by_employee_id' => $employee->id,
                    'accounting_decision_at' => $decisionAt,
                    'approved_quantity' => $approvedQuantity,
                    'approved_amount' => round($amount, 2),
                    'accounting_remarks' => $request->finance_remarks,
                    'rejection_reason' => null,
                    'rejection_notes' => null,
                ]);

                return $lockedFundingRequest->fresh();
            });

            return response()->json([
                'success' => true,
                'message' => 'Approved. Pay the supplier once they confirm or deliver, as their terms say.',
                'data' => $fundingRequest,
            ], 200);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            Log::error('Approve funding request error', [
                'funding_request_id' => $id,
                'employee_id' => $request->user()?->id,
                'owner_id' => $this->getOwnerId($request),
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to approve request'], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            if ($response = $this->requireFinanceAccess($request, 'reject')) {
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

            $fundingRequest = FundingRequest::query()
                ->where('id', $id)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$fundingRequest) {
                return response()->json(['message' => 'Funding request not found'], 404);
            }

            if ((int) $fundingRequest->owner_id !== (int) $employee->owner_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ((int) $fundingRequest->approver_id !== (int) $employee->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the assigned approver can reject this request',
                ], 403);
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
