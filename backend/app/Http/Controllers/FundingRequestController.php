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

        $allowed = $access === 'edit'
            ? $employee->canEditModule($module)
            : $employee->canViewModule($module);

        if (!$allowed) {
            return response()->json(['message' => 'You do not have permission to perform this action'], 403);
        }

        return null;
    }

    private function requireAnyModuleAccess(Request $request, array $modules, string $access = 'view')
    {
        $employee = $request->user();

        if (!$employee instanceof Employee) {
            return response()->json(['message' => 'Only employees can access this endpoint'], 403);
        }

        foreach ($modules as $module) {
            $allowed = $access === 'edit'
                ? $employee->canEditModule($module)
                : $employee->canViewModule($module);

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
                    ->where('access', 'edit');
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
                'error' => $e->getMessage(),
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

    public function store(Request $request)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'edit')) {
                return $response;
            }

            $isDraft = $request->boolean('is_draft', false);

            $rules = [
                'approver_id' => $isDraft ? 'nullable|exists:employees,id' : 'required|exists:employees,id',
                'related_sales_order_id' => 'nullable|string|max:255',
                'product_name' => $isDraft ? 'nullable|string|max:255' : 'required|string|max:255',
                'flower_category' => $isDraft ? 'nullable|in:Fresh,Dried,Artificial' : 'required|in:Fresh,Dried,Artificial',
                'variant' => $isDraft ? 'nullable|in:Premium,Grade A,Grade B,Standard' : 'required|in:Premium,Grade A,Grade B,Standard',
                'requested_qty' => $isDraft ? 'nullable|numeric|min:0' : 'required|numeric|min:1',
                'uom' => $isDraft ? 'nullable|in:Stems,Bunches,Boxes' : 'required|in:Stems,Bunches,Boxes',
                'moq' => 'nullable|numeric|min:0',
                'preferred_supplier' => 'nullable|string|max:255',
                'alternative_suppliers' => 'nullable|string',
                'required_delivery_date' => $isDraft ? 'nullable|date' : 'required|date',
                'current_stock' => 'nullable|numeric|min:0',
                'reserved_stock' => 'nullable|numeric|min:0',
                'required_quantity' => 'nullable|numeric|min:0',
                'incoming_stock' => 'nullable|numeric|min:0',
                'reason_for_shortage' => $isDraft ? 'nullable|in:New Order,Spoilage,Forecast Error,Seasonal Demand,Supplier Delay' : 'required|in:New Order,Spoilage,Forecast Error,Seasonal Demand,Supplier Delay',
                'estimated_unit_cost' => 'nullable|numeric|min:0',
                'payment_terms' => $isDraft ? 'nullable|in:Cash,7 Days,15 Days,30 Days' : 'required|in:Cash,7 Days,15 Days,30 Days',
                'expected_selling_price' => 'nullable|numeric|min:0',
                'tax_vat_estimate' => 'nullable|numeric|min:0',
                'logistics_cost' => 'nullable|numeric|min:0',
                'urgency_level' => $isDraft ? 'nullable|in:Normal,Urgent,Critical' : 'required|in:Normal,Urgent,Critical',
                'shelf_life' => 'nullable|numeric|min:0',
                'expected_spoilage' => $isDraft ? 'nullable|numeric|min:0|max:100' : 'required|numeric|min:0|max:100',
                'missed_sales_impact' => 'nullable|numeric|min:0',
                'seasonal_tag' => 'nullable|string|max:255',
                'demand_confidence' => $isDraft ? 'nullable|in:High,Medium,Low' : 'required|in:High,Medium,Low',
                'finance_recommendation' => $isDraft ? 'nullable|in:Approve,Approve Partial,Reject' : 'required|in:Approve,Approve Partial,Reject',
                'recommended_qty' => 'nullable|numeric|min:0',
                'price_ceiling' => 'nullable|numeric|min:0',
                'suggested_supplier' => 'nullable|string|max:255',
                'business_justification' => $isDraft ? 'nullable|string|max:150' : 'required|string|max:150',
                'approval_impact' => $isDraft ? 'nullable|string|max:150' : 'required|string|max:150',
                'rejection_risk' => $isDraft ? 'nullable|string|max:150' : 'required|string|max:150',
                'additional_notes' => 'nullable|string|max:300',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $ownerId = $this->getOwnerId($request);
            $employeeId = $this->getEmployeeId($request);

            if (!$ownerId) return response()->json(['message' => 'Unable to resolve owner'], 404);
            if (!$employeeId) return response()->json(['message' => 'Unable to resolve requesting employee'], 403);

            $approverId = $request->input('approver_id');
            if ($response = $this->validateApprover($ownerId, $approverId)) {
                return $response;
            }

            $year = date('Y');
            $count = DB::table('funding_requests')
                ->where('owner_id', $ownerId)
                ->whereYear('created_at', $year)
                ->count() + 1;
            $financeRequestId = sprintf('FR-%s-%03d', $year, $count);

            $currentStock = (float) $request->input('current_stock', 0);
            $reservedStock = (float) $request->input('reserved_stock', 0);
            $requiredQty = (float) $request->input('required_quantity', 0);
            $requestedQty = (float) $request->input('requested_qty', 0);
            $recommendedQty = (float) $request->input('recommended_qty', $requestedQty);
            $estimatedUnitCost = (float) $request->input('estimated_unit_cost', 0);
            $expectedSellingPrice = (float) $request->input('expected_selling_price', 0);

            $netAvailableStock = $currentStock - $reservedStock;
            $stockShortageQty = max(0, $requiredQty - $netAvailableStock);
            $estimatedTotalCost = $estimatedUnitCost * $requestedQty;
            $expectedRevenue = $expectedSellingPrice * $requestedQty;
            $estimatedGrossMargin = $expectedSellingPrice != 0
                ? (($expectedSellingPrice - $estimatedUnitCost) / $expectedSellingPrice) * 100
                : 0;
            $recommendedBudget = $estimatedUnitCost * $recommendedQty;

            $fundingRequestId = DB::table('funding_requests')->insertGetId([
                'owner_id' => $ownerId,
                'finance_request_id' => $financeRequestId,
                'submitted_by_employee_id' => $employeeId,
                'requester_id' => $employeeId,
                'accounting_manager_id' => $approverId,
                'approver_id' => $approverId,
                'related_sales_order_id' => $request->input('related_sales_order_id'),
                'request_date' => now()->toDateString(),
                'request_status' => $isDraft ? 'Draft' : 'Pending',
                'product_name' => $request->input('product_name'),
                'flower_category' => $request->input('flower_category'),
                'variant' => $request->input('variant'),
                'requested_qty' => $requestedQty,
                'uom' => $request->input('uom'),
                'moq' => $request->input('moq'),
                'preferred_supplier' => $request->input('preferred_supplier'),
                'alternative_suppliers' => $request->input('alternative_suppliers'),
                'required_delivery_date' => $request->input('required_delivery_date'),
                'current_stock' => $currentStock,
                'reserved_stock' => $reservedStock,
                'net_available_stock' => $netAvailableStock,
                'required_quantity' => $requiredQty,
                'stock_shortage_qty' => $stockShortageQty,
                'incoming_stock' => (float) $request->input('incoming_stock', 0),
                'reason_for_shortage' => $request->input('reason_for_shortage'),
                'estimated_unit_cost' => $estimatedUnitCost,
                'estimated_total_cost' => $estimatedTotalCost,
                'currency' => 'PHP',
                'payment_terms' => $request->input('payment_terms'),
                'expected_selling_price' => $expectedSellingPrice,
                'expected_revenue' => $expectedRevenue,
                'estimated_gross_margin' => round($estimatedGrossMargin, 1),
                'tax_vat_estimate' => (float) $request->input('tax_vat_estimate', 0),
                'logistics_cost' => (float) $request->input('logistics_cost', 0),
                'urgency_level' => $request->input('urgency_level'),
                'shelf_life' => $request->filled('shelf_life') ? (float) $request->input('shelf_life') : null,
                'expected_spoilage' => $request->filled('expected_spoilage') ? (float) $request->input('expected_spoilage') : null,
                'missed_sales_impact' => (float) $request->input('missed_sales_impact', 0),
                'seasonal_tag' => $request->input('seasonal_tag'),
                'demand_confidence' => $request->input('demand_confidence'),
                'finance_recommendation' => $request->input('finance_recommendation'),
                'recommended_qty' => $recommendedQty,
                'recommended_budget' => $recommendedBudget,
                'price_ceiling' => $request->filled('price_ceiling') ? (float) $request->input('price_ceiling') : null,
                'suggested_supplier' => $request->input('suggested_supplier'),
                'business_justification' => $request->input('business_justification'),
                'approval_impact' => $request->input('approval_impact'),
                'rejection_risk' => $request->input('rejection_risk'),
                'additional_notes' => $request->input('additional_notes'),
                'submitted_to_accounting_at' => $isDraft ? null : now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $isDraft ? 'Draft saved successfully' : 'Funding request submitted successfully',
                'id' => $fundingRequestId,
            ], 201);
        } catch (Exception $e) {
            Log::error('Funding request store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create funding request',
                'error' => $e->getMessage(),
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

            $approverId = $request->input('approver_id', $fundingRequest->approver_id ?? $fundingRequest->accounting_manager_id);
            if ($response = $this->validateApprover($ownerId, $approverId)) {
                return $response;
            }

            $currentStock = (float) $request->input('current_stock', 0);
            $reservedStock = (float) $request->input('reserved_stock', 0);
            $requiredQty = (float) $request->input('required_quantity', 0);
            $requestedQty = (float) $request->input('requested_qty', 0);
            $recommendedQty = (float) $request->input('recommended_qty', $requestedQty);
            $estimatedUnitCost = (float) $request->input('estimated_unit_cost', 0);
            $expectedSellingPrice = (float) $request->input('expected_selling_price', 0);

            $netAvailableStock = $currentStock - $reservedStock;
            $stockShortageQty = max(0, $requiredQty - $netAvailableStock);
            $estimatedTotalCost = $estimatedUnitCost * $requestedQty;
            $expectedRevenue = $expectedSellingPrice * $requestedQty;
            $estimatedGrossMargin = $expectedSellingPrice != 0
                ? (($expectedSellingPrice - $estimatedUnitCost) / $expectedSellingPrice) * 100
                : 0;
            $recommendedBudget = $estimatedUnitCost * $recommendedQty;

            DB::table('funding_requests')->where('id', $id)->update([
                'accounting_manager_id' => $approverId,
                'approver_id' => $approverId,
                'related_sales_order_id' => $request->input('related_sales_order_id'),
                'product_name' => $request->input('product_name'),
                'flower_category' => $request->input('flower_category'),
                'variant' => $request->input('variant'),
                'requested_qty' => $requestedQty,
                'uom' => $request->input('uom'),
                'moq' => $request->input('moq'),
                'preferred_supplier' => $request->input('preferred_supplier'),
                'alternative_suppliers' => $request->input('alternative_suppliers'),
                'required_delivery_date' => $request->input('required_delivery_date'),
                'current_stock' => $currentStock,
                'reserved_stock' => $reservedStock,
                'net_available_stock' => $netAvailableStock,
                'required_quantity' => $requiredQty,
                'stock_shortage_qty' => $stockShortageQty,
                'incoming_stock' => (float) $request->input('incoming_stock', 0),
                'reason_for_shortage' => $request->input('reason_for_shortage'),
                'estimated_unit_cost' => $estimatedUnitCost,
                'estimated_total_cost' => $estimatedTotalCost,
                'payment_terms' => $request->input('payment_terms'),
                'expected_selling_price' => $expectedSellingPrice,
                'expected_revenue' => $expectedRevenue,
                'estimated_gross_margin' => round($estimatedGrossMargin, 1),
                'tax_vat_estimate' => (float) $request->input('tax_vat_estimate', 0),
                'logistics_cost' => (float) $request->input('logistics_cost', 0),
                'urgency_level' => $request->input('urgency_level'),
                'shelf_life' => $request->filled('shelf_life') ? (float) $request->input('shelf_life') : null,
                'expected_spoilage' => $request->filled('expected_spoilage') ? (float) $request->input('expected_spoilage') : null,
                'missed_sales_impact' => (float) $request->input('missed_sales_impact', 0),
                'seasonal_tag' => $request->input('seasonal_tag'),
                'demand_confidence' => $request->input('demand_confidence'),
                'finance_recommendation' => $request->input('finance_recommendation'),
                'recommended_qty' => $recommendedQty,
                'recommended_budget' => $recommendedBudget,
                'price_ceiling' => $request->filled('price_ceiling') ? (float) $request->input('price_ceiling') : null,
                'suggested_supplier' => $request->input('suggested_supplier'),
                'business_justification' => $request->input('business_justification'),
                'approval_impact' => $request->input('approval_impact'),
                'rejection_risk' => $request->input('rejection_risk'),
                'additional_notes' => $request->input('additional_notes'),
                'updated_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Funding request updated successfully'], 200);
        } catch (Exception $e) {
            Log::error('Funding request update error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update funding request'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($response = $this->requireModuleAccess($request, self::INVENTORY_MODULE, 'edit')) {
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
            if ($fundingRequest->request_status !== 'Draft') return response()->json(['message' => 'Only draft requests can be submitted'], 400);
            if (!$fundingRequest->approver_id && !$fundingRequest->accounting_manager_id) {
                return response()->json(['message' => 'An approver is required before submitting this request'], 422);
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
