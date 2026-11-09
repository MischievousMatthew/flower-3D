<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FundingRequestController extends Controller
{
    private const INVENTORY_MODULE = 'inventory_funding';
    private const FINANCE_APPROVER_MODULE = 'funding_requests';

    private function getOwnerId(Request $request): ?int
    {
        $user = $request->user();
        if ($user instanceof \App\Models\Employee) return $user->owner_id;
        if ($user instanceof \App\Models\User) return $user->id;
        return null;
    }

    private function getEmployeeId(Request $request): ?int
    {
        $user = $request->user();
        if ($user instanceof \App\Models\Employee) return $user->id;
        return null;
    }

    private function requireModuleAccess(Request $request, string $module, string $access = 'view')
    {
        $employee = $request->user();

        if (!$employee instanceof Employee) {
            return response()->json(['message' => 'Only employees can access this endpoint'], 403);
        }

        $allowed = $employee->hasModulePermission($module, $access);

        if (!$allowed) {
            return response()->json(['message' => 'You do not have permission to perform this action'], 403);
        }

        return null;
    }

    private function requireAnyModuleAccess(Request $request, array $modules, string $access = 'view')
    {
        $employee = $request->user();

        // The shop owner can look at every request
        if ($access === 'view' && $employee instanceof \App\Models\User && $employee->role === \App\Models\User::ROLE_VENDOR) {
            return null;
        }

        if (!$employee instanceof Employee) {
            return response()->json(['message' => 'Only employees can access this endpoint'], 403);
        }

        foreach ($modules as $module) {
            $allowed = $employee->hasModulePermission($module, $access);

            if ($allowed) {
                return null;
            }
        }

        return response()->json(['message' => 'You do not have permission to perform this action'], 403);
    }

    private function eligibleFinanceApproversQuery(int $ownerId)
    {
        return Employee::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'Active')
            ->whereHas('modulePermissions', function ($query) {
                $query->where('module', self::FINANCE_APPROVER_MODULE)
                    ->where('permission', 'approve');
            });
    }

    private function validateApprover(int $ownerId, ?int $approverId)
    {
        if (!$approverId) {
            return null;
        }

        if ($this->eligibleFinanceApproversQuery($ownerId)->where('id', $approverId)->exists()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Selected approver must have finance edit permission',
            'errors' => [
                'approver_id' => ['Selected approver must have finance edit permission'],
            ],
        ], 422);
    }

    private function generateFinanceRequestId(int $year): string
    {
        $prefix = sprintf('FR-%s-', $year);
        $sequence = DB::table('funding_requests')
            ->where('finance_request_id', 'like', $prefix . '%')
            ->count() + 1;

        do {
            $candidate = sprintf('%s%03d', $prefix, $sequence);
            $exists = DB::table('funding_requests')
                ->where('finance_request_id', $candidate)
                ->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }

    public function getEligibleApprovers(Request $request)
    {
        try {
            $ownerId = $this->getOwnerId($request);
            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);

            $approvers = $this->eligibleFinanceApproversQuery($ownerId)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'label' => sprintf('%s (Finance)', $employee->name),
                ])
                ->values();

            return response()->json(['success' => true, 'data' => $approvers], 200);
        } catch (Exception $e) {
            Log::error('Get eligible approvers error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve approvers'], 500);
        }
    }

    public function getProducts(Request $request)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'view')) {
                return $response;
            }

            $ownerId = $this->getOwnerId($request);
            if (!$ownerId) return response()->json(['success' => false, 'message' => 'Unable to resolve owner'], 404);

            $products = DB::table('products')
                ->where('owner_id', $ownerId)
                ->whereIn('status', ['active', 'inactive', 'draft'])
                ->whereNull('deleted_at')
                ->select(
                    'id',
                    'product_name',
                    'description',
                    'sku',
                    'category',
                    'flower_type',
                    'color',
                    'color_other',
                    'purchase_price',
                    'selling_price',
                    'has_discount',
                    'discount_price',
                    'quantity_in_stock',
                    'min_stock_level',
                    'max_stock_level',
                    'selling_type',
                    'season',
                    'supplier_name',
                    'supplier_contact',
                    'supplier_sku',
                    'supplier_lead_time',
                    'care_instructions',
                    'occasion_tags',
                    'notes',
                    'is_fragile',
                    'requires_refrigeration'
                )
                ->orderBy('product_name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $products,
                'owner_id' => $ownerId,
            ], 200);
        } catch (Exception $e) {
            Log::error('Get products error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products',
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'view')) {
                return $response;
            }

            $ownerId = $this->getOwnerId($request);
            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);

            $requests = DB::table('funding_requests as fr')
                ->leftJoin('employees as requester', 'fr.requester_id', '=', 'requester.id')
                ->leftJoin('employees as approver', 'fr.approver_id', '=', 'approver.id')
                ->leftJoin('employees as reviewer', 'fr.reviewed_by_employee_id', '=', 'reviewer.id')
                ->where('fr.owner_id', $ownerId)
                ->select(
                    'fr.*',
                    'requester.name as requester_name',
                    'requester.email as requester_email',
                    'requester.name as submitted_by_name',
                    'requester.email as submitted_by_email',
                    'approver.name as approver_name',
                    'approver.name as accounting_manager_name',
                    'reviewer.name as reviewed_by_name'
                )
                ->orderBy('fr.created_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $requests], 200);
        } catch (Exception $e) {
            Log::error('Funding requests index error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve funding requests'], 500);
        }
    }

    /** Banks and e-wallets Finance can pay through: only those PayMongo offers */
    public const PAYMENT_CHANNELS = [
        'bank' => ['BPI', 'UnionBank', 'BDO', 'Landbank', 'Metrobank'],
        'ewallet' => ['GCash', 'Maya', 'GrabPay', 'ShopeePay'],
    ];

    private const REASONS = ['New Order', 'Spoilage', 'Forecast Error', 'Seasonal Demand', 'Supplier Delay'];
    private const URGENCY = ['Normal', 'Urgent', 'Critical'];

    private static function uomFor(?string $sellingType): string
    {
        return ['per_piece' => 'Stems', 'per_bunch' => 'Bunches', 'per_box' => 'Boxes'][$sellingType] ?? 'Stems';
    }

    /**
     * Checks the form and works out what gets saved: the request's own columns
     * and its product lines. Returns [errorResponse|null, columns, lines].
     */
    private function buildRequest(Request $request, int $ownerId, bool $isDraft): array
    {
        $req = $isDraft ? 'nullable' : 'required';
        $method = $request->input('payment_method');
        $channels = self::PAYMENT_CHANNELS[$method] ?? array_merge(...array_values(self::PAYMENT_CHANNELS));

        $validator = Validator::make($request->all(), [
            'approver_id' => "$req|integer|exists:employees,id",
            'items' => $isDraft ? 'nullable|array|max:50' : 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'required|numeric|min:0|max:10000000',
            'required_delivery_date' => "$req|date",
            'urgency_level' => "$req|in:" . implode(',', self::URGENCY),
            'reason_for_shortage' => "$req|in:" . implode(',', self::REASONS),
            'payment_method' => "$req|in:bank,ewallet",
            'payment_channel' => [$req, 'string', 'max:60', \Illuminate\Validation\Rule::in($channels)],
            'logistics_cost' => 'nullable|numeric|min:0|max:10000000',
            'business_justification' => "$req|string|max:150",
            'additional_notes' => 'nullable|string|max:300',
        ], [
            'items.required' => 'Pick at least one product.',
            'items.min' => 'Pick at least one product.',
            'items.*.product_id.distinct' => 'Each product can only be added once.',
            'items.*.quantity.min' => 'Ask for at least 1.',
            'payment_channel.in' => 'Pick a bank or e-wallet from the list.',
        ], [
            'approver_id' => 'Finance approver',
            'required_delivery_date' => 'needed-by date',
            'reason_for_shortage' => 'reason',
            'payment_channel' => 'bank or e-wallet',
            'business_justification' => 'reason in your words',
        ]);

        if ($validator->fails()) {
            return [response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422), [], []];
        }

        $input = collect($request->input('items', []));
        $products = DB::table('products')
            ->where('owner_id', $ownerId)
            ->whereNull('deleted_at')
            ->whereIn('id', $input->pluck('product_id')->all())
            ->get(['id', 'product_name', 'sku', 'selling_type', 'quantity_in_stock', 'supplier_name'])
            ->keyBy('id');

        $lines = [];
        foreach ($input as $i => $row) {
            $product = $products->get((int) $row['product_id']);
            if (!$product) {
                return [response()->json([
                    'success' => false,
                    'message' => 'One of the products is no longer in your inventory. Remove it and try again.',
                    'errors' => ["items.$i.product_id" => ['This product is no longer in your inventory.']],
                ], 422), [], []];
            }
            $qty = (int) $row['quantity'];
            $cost = round((float) $row['unit_cost'], 2);
            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'sku' => $product->sku,
                'uom' => self::uomFor($product->selling_type),
                'current_stock' => (int) $product->quantity_in_stock,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'line_total' => round($qty * $cost, 2),
                'supplier_name' => $product->supplier_name,
            ];
        }

        $count = count($lines);
        $totalQty = array_sum(array_column($lines, 'quantity'));
        $subtotal = array_sum(array_column($lines, 'line_total'));
        $delivery = round((float) $request->input('logistics_cost', 0), 2);
        $total = round($subtotal + $delivery, 2);
        $uoms = array_unique(array_column($lines, 'uom'));
        $single = $count === 1 ? $lines[0] : null;

        $name = $count ? $lines[0]['product_name'] . ($count > 1 ? sprintf(' + %d more', $count - 1) : '') : null;

        // The request's own columns still describe it as a whole, so the
        // request list, Finance's review and purchase orders keep working
        $columns = [
            'approver_id' => $request->filled('approver_id') ? (int) $request->input('approver_id') : null,
            'accounting_manager_id' => $request->filled('approver_id') ? (int) $request->input('approver_id') : null,
            'product_name' => $name ? mb_substr($name, 0, 255) : null,
            'requested_qty' => $totalQty ?: null,
            'required_quantity' => $totalQty ?: null,
            'recommended_qty' => $totalQty ?: null,
            'uom' => count($uoms) === 1 ? reset($uoms) : null,
            'current_stock' => $single['current_stock'] ?? null,
            'preferred_supplier' => $single['supplier_name'] ?? null,
            'required_delivery_date' => $request->input('required_delivery_date') ?: null,
            'urgency_level' => $request->input('urgency_level') ?: null,
            'reason_for_shortage' => $request->input('reason_for_shortage') ?: null,
            // An average, so quantity × unit cost still equals the products' total
            'estimated_unit_cost' => $totalQty ? round($subtotal / $totalQty, 2) : null,
            'estimated_total_cost' => $total,
            'recommended_budget' => $total,
            'currency' => 'PHP',
            'payment_method' => $request->input('payment_method') ?: null,
            'payment_channel' => $request->input('payment_channel') ?: null,
            'logistics_cost' => $delivery,
            'business_justification' => $request->input('business_justification') ?: null,
            'additional_notes' => $request->input('additional_notes') ?: null,
            // No longer asked for
            'payment_terms' => null,
            'tax_vat_estimate' => null,
            'related_sales_order_id' => null,
            'flower_category' => null,
            'variant' => null,
            'moq' => null,
            'alternative_suppliers' => null,
            'reserved_stock' => null,
            'net_available_stock' => null,
            'stock_shortage_qty' => null,
            'incoming_stock' => 0,
            'expected_selling_price' => null,
            'expected_revenue' => null,
            'estimated_gross_margin' => null,
            'expected_spoilage' => null,
            'missed_sales_impact' => null,
            'seasonal_tag' => null,
            'demand_confidence' => null,
            'finance_recommendation' => null,
            'price_ceiling' => null,
            'suggested_supplier' => null,
            'approval_impact' => null,
            'rejection_risk' => null,
        ];

        $lines = array_map(function ($l) {
            unset($l['supplier_name']);
            return $l;
        }, $lines);

        return [null, $columns, $lines];
    }

    private function saveLines(int $fundingRequestId, array $lines): void
    {
        DB::table('funding_request_items')->where('funding_request_id', $fundingRequestId)->delete();
        if (!$lines) return;

        $now = now();
        DB::table('funding_request_items')->insert(array_map(fn ($l) => $l + [
            'funding_request_id' => $fundingRequestId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $lines));
    }

    public function store(Request $request)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'create')) {
                return $response;
            }

            $isDraft = $request->boolean('is_draft', false);
            $ownerId = $this->getOwnerId($request);
            $employeeId = $this->getEmployeeId($request);

            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);
            if (!$employeeId) return response()->json(['message' => 'Unable to resolve requesting employee'], 403);

            [$error, $columns, $lines] = $this->buildRequest($request, $ownerId, $isDraft);
            if ($error) return $error;

            if ($response = $this->validateApprover($ownerId, $columns['approver_id'])) {
                return $response;
            }

            $fundingRequestId = DB::transaction(function () use ($ownerId, $employeeId, $isDraft, $columns, $lines) {
                $id = DB::table('funding_requests')->insertGetId($columns + [
                    'owner_id' => $ownerId,
                    'finance_request_id' => $this->generateFinanceRequestId((int) date('Y')),
                    'submitted_by_employee_id' => $employeeId,
                    'requester_id' => $employeeId,
                    'request_date' => now()->toDateString(),
                    'request_status' => $isDraft ? 'Draft' : 'Pending',
                    'submitted_to_accounting_at' => $isDraft ? null : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->saveLines($id, $lines);
                return $id;
            });

            return response()->json([
                'success' => true,
                'message' => $isDraft ? 'Draft saved' : 'Funding request sent to Finance',
                'id' => $fundingRequestId,
            ], 201);
        } catch (Exception $e) {
            Log::error('Funding request store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Couldn\'t save the funding request. Please try again.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            if ($response = $this->requireAnyModuleAccess($request, [self::INVENTORY_MODULE, self::FINANCE_APPROVER_MODULE], 'view')) {
                return $response;
            }

            $ownerId = $this->getOwnerId($request);
            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);

            $fundingRequest = DB::table('funding_requests as fr')
                ->leftJoin('employees as requester', 'fr.requester_id', '=', 'requester.id')
                ->leftJoin('employees as approver', 'fr.approver_id', '=', 'approver.id')
                ->leftJoin('employees as reviewer', 'fr.reviewed_by_employee_id', '=', 'reviewer.id')
                ->where('fr.id', $id)
                ->where('fr.owner_id', $ownerId)
                ->select(
                    'fr.*',
                    'requester.name as requester_name',
                    'requester.email as requester_email',
                    'requester.name as submitted_by_name',
                    'approver.name as approver_name',
                    'approver.name as accounting_manager_name',
                    'reviewer.name as reviewed_by_name'
                )
                ->first();

            if (!$fundingRequest) return response()->json(['message' => 'Funding request not found'], 404);

            $fundingRequest->items = DB::table('funding_request_items')
                ->where('funding_request_id', $fundingRequest->id)
                ->orderBy('id')
                ->get(['id', 'product_id', 'product_name', 'sku', 'uom', 'current_stock', 'quantity', 'approved_quantity', 'unit_cost', 'line_total']);

            // Budget, what's due under the supplier's terms, and what was paid
            $model = \App\Models\FundingRequest::with(['payments', 'linkedOrder.items', 'linkedOrder.supplier'])->find($fundingRequest->id);
            $fundingRequest->payment = $model ? app(\App\Services\FundingPaymentService::class)->state($model) : null;
            // The supplier's full account number is for Finance, who pays into it
            if (!$request->is('api/finance/*') && isset($fundingRequest->payment['order']['payout']['account_number'])) {
                $fundingRequest->payment['order']['payout']['account_number'] = \App\Support\SupplierPayout::mask($fundingRequest->payment['order']['payout']['account_number']);
            }

            return response()->json(['success' => true, 'data' => $fundingRequest], 200);
        } catch (Exception $e) {
            Log::error('Funding request show error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve funding request'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'edit')) {
                return $response;
            }

            $ownerId = $this->getOwnerId($request);
            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);

            $fundingRequest = DB::table('funding_requests')
                ->where('id', $id)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$fundingRequest) return response()->json(['message' => 'Funding request not found'], 404);
            if ($fundingRequest->request_status !== 'Draft') return response()->json(['message' => 'Only draft requests can be edited'], 400);

            // Saving keeps it a draft unless it's being sent to Finance now
            $send = $request->boolean('submit', false);

            [$error, $columns, $lines] = $this->buildRequest($request, $ownerId, !$send);
            if ($error) return $error;

            if ($response = $this->validateApprover($ownerId, $columns['approver_id'])) {
                return $response;
            }

            DB::transaction(function () use ($id, $columns, $lines, $send) {
                DB::table('funding_requests')->where('id', $id)->update($columns + [
                    'updated_at' => now(),
                ] + ($send ? ['request_status' => 'Pending', 'submitted_to_accounting_at' => now()] : []));
                $this->saveLines((int) $id, $lines);
            });

            return response()->json([
                'success' => true,
                'message' => $send ? 'Funding request sent to Finance' : 'Draft saved',
            ], 200);
        } catch (Exception $e) {
            Log::error('Funding request update error: ' . $e->getMessage());
            return response()->json(['message' => 'Couldn\'t save the funding request. Please try again.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'delete')) {
                return $response;
            }

            $ownerId = $this->getOwnerId($request);
            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);

            $deleted = DB::table('funding_requests')
                ->where('id', $id)
                ->where('owner_id', $ownerId)
                ->delete();

            if (!$deleted) return response()->json(['message' => 'Funding request not found'], 404);

            return response()->json(['success' => true, 'message' => 'Funding request deleted successfully'], 200);
        } catch (Exception $e) {
            Log::error('Funding request delete error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete funding request'], 500);
        }
    }

    public function submitToAccounting(Request $request, $id)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'create')) {
                return $response;
            }

            $ownerId = $this->getOwnerId($request);
            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);

            $fundingRequest = DB::table('funding_requests')
                ->where('id', $id)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$fundingRequest) return response()->json(['message' => 'Funding request not found'], 404);
            if ($fundingRequest->request_status !== 'Draft') return response()->json(['message' => 'Only draft requests can be submitted'], 400);
            if (!$fundingRequest->approver_id && !$fundingRequest->accounting_manager_id) {
                return response()->json(['message' => 'An approver is required before submitting this request'], 422);
            }

            // A draft may have been saved half-filled: everything Finance needs must be there
            $hasLines = DB::table('funding_request_items')->where('funding_request_id', $id)->exists();
            $missing = array_filter([
                'products' => !$hasLines && !$fundingRequest->product_name,
                'a needed-by date' => !$fundingRequest->required_delivery_date,
                'how it will be paid' => !$fundingRequest->payment_method && !$fundingRequest->payment_terms,
                'the reason' => !$fundingRequest->reason_for_shortage,
                'why it\'s needed' => !$fundingRequest->business_justification,
            ]);
            if ($missing) {
                return response()->json([
                    'message' => 'Edit the draft and add ' . implode(', ', array_keys($missing)) . ' before sending it.',
                ], 422);
            }

            DB::table('funding_requests')->where('id', $id)->update([
                'request_status' => 'Pending',
                'submitted_to_accounting_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Request submitted to finance successfully'], 200);
        } catch (Exception $e) {
            Log::error('Submit to finance error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to submit request'], 500);
        }
    }
}
