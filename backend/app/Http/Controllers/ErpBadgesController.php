<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\CustomerInquiry;
use App\Models\Delivery;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\FundingRequest;
use App\Models\OrderRequest;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SupportTicket;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GET /erp/badges — the sidebar's numbers: for each page that has something
 * waiting for someone to act on, how many. Only pages the person may open,
 * and only those with a number above zero, so most pages never show one.
 * (Chat keeps its own live unread count.)
 */
class ErpBadgesController extends Controller
{
    use ScopesOwner;

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $user = $request->user();
        $may = fn (string $module) => ! $user instanceof Employee || $user->hasModulePermission($module, 'view');

        $counts = [];
        foreach ($this->counters($ownerId) as $module => $count) {
            if (! $may($module)) {
                continue;
            }
            // One page failing to count never hides the others
            try {
                $n = (int) $count();
            } catch (\Throwable $e) {
                Log::warning('Sidebar badge failed to count.', ['module' => $module, 'error' => $e->getMessage()]);
                continue;
            }
            if ($n > 0) {
                $counts[$module] = $n;
            }
        }

        return response()->json(['success' => true, 'data' => $counts]);
    }

    /** What each page counts as waiting */
    private function counters(int $ownerId): array
    {
        $shopOrder = fn ($q) => $q->where('vendor_id', $ownerId);

        return [
            // Flagged scans and correction requests waiting for approval
            'attendance' => fn () => EmployeeAttendance::forOwner($ownerId)->where('review_status', 'pending')->count()
                + AttendanceCorrection::where('owner_id', $ownerId)->where('status', 'pending')->count(),
            // Leave and overtime requests waiting for a decision
            'leave_management' => fn () => EmployeeLeave::forOwner($ownerId)->where('status', 'pending')->count()
                + OvertimeRequest::where('owner_id', $ownerId)->where('status', 'pending')->count(),
            // Payroll sent to Finance, not yet approved or sent back
            'payroll_requests' => fn () => Payroll::forOwner($ownerId)->where('status', 'pending')->count(),
            // Funding waiting for Finance's approval
            'funding_requests' => fn () => FundingRequest::forOwner($ownerId)->where('request_status', FundingRequest::STATUS_PENDING)->count(),
            // Refunds agreed with the customer but not yet sent
            'refunds' => fn () => OrderRequest::query()->whereHas('order', $shopOrder)
                ->where('status', 'approved')
                ->whereIn('settled_resolution', [OrderRequest::FULL_REFUND, OrderRequest::PARTIAL_REFUND])
                ->whereNull('refund_sent_at')->count(),
            // New cases nobody has picked up yet
            'crm_cases' => fn () => SupportTicket::query()->where('vendor_id', $ownerId)->where('status', 'open')->count(),
            // Customer questions without an answer
            'crm_inquiries' => fn () => CustomerInquiry::query()->where('vendor_id', $ownerId)->where('status', 'open')->count(),
            // Products at or below their minimum stock
            'inventory_products' => fn () => Product::withoutGlobalScopes()->where('owner_id', $ownerId)->whereNull('deleted_at')
                ->where('min_stock_level', '>', 0)->whereColumn('quantity_in_stock', '<=', 'min_stock_level')->count(),
            // Supplier orders to approve, or arriving and still to be received
            'sc_orders' => fn () => PurchaseOrder::withoutGlobalScopes()->where('owner_id', $ownerId)
                ->whereIn('status', ['pending_approval', 'shipped', 'partially_received'])->count(),
            // Customer return requests waiting for the shop's decision
            'deliveries' => fn () => OrderRequest::query()->whereHas('order', $shopOrder)->where('status', 'pending')->count(),
            // Orders still waiting for a scan (to pack, to hand over, to drop off)
            'order_scan' => fn () => Delivery::withoutGlobalScopes()->whereHas('order', $shopOrder)
                ->whereIn('status', array_keys(Delivery::AUTO_NEXT))->count(),
        ];
    }
}
