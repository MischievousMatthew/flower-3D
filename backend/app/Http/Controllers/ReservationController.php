<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorClosedDate;
use App\Models\ReservationAvailabilityCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReservationController extends Controller
{
    const LEAD_TIME_DAYS = 3; // 3-day lead time for flower preparation

    /**
     * Get calendar availability for a vendor (PUBLIC) with 3-day lead time
     */
    public function getAvailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_id' => 'required|exists:users,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendorId = $request->vendor_id;
            $vendor = User::find($vendorId);
            
            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor not found'
                ], 404);
            }

            // Calculate date ranges with 3-day lead time
            $today = Carbon::today();
            $minDate = $today->copy()->addDays(self::LEAD_TIME_DAYS);
            $startDate = $request->start_date ?? $minDate->toDateString();
            $endDate = $request->end_date ?? $today->copy()->addMonths(3)->toDateString();
            $maxEndDate = $today->copy()->addMonths(3)->toDateString();

            // Ensure start date respects lead time
            $requestedStartDate = Carbon::parse($startDate);
            if ($requestedStartDate->lessThan($minDate)) {
                $startDate = $minDate->toDateString();
            }

            if ($endDate > $maxEndDate) {
                $endDate = $maxEndDate;
            }

            $availability = ReservationAvailabilityCache::getAvailabilityForRange(
                $vendorId,
                $startDate,
                $endDate
            );

            $userReservations = [];
            if ($request->user()) {
                $userReservations = Order::where('user_id', $request->user()->id)
                    ->where('vendor_id', $vendorId)
                    ->whereBetween('reservation_date', [$startDate, $endDate])
                    ->whereNotIn('status', ['cancelled', 'failed'])
                    ->pluck('reservation_date')
                    ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                    ->toArray();
            }

            $calendar = [];
            foreach ($availability as $date => $data) {
                $dateCarbon = Carbon::parse($date);
                $hasUserOrder = in_array($date, $userReservations);
                
                // Disable dates within lead time
                $isWithinLeadTime = $dateCarbon->lessThan($minDate);
                $isPast = $dateCarbon->lessThan($today);
                
                $isDisabled = $isPast || 
                             $isWithinLeadTime || 
                             in_array($data['status'], ['fully_booked', 'closed']);

                $calendar[$date] = [
                    'status' => $data['status'],
                    'color' => $this->getColorForStatus(
                        $data['status'],
                        $hasUserOrder,
                        $isWithinLeadTime
                    ),
                    'available_slots' => $data['available_slots'],
                    'orders_count' => $data['orders_count'],
                    'max_orders' => $data['max_orders'],
                    'is_disabled' => $isDisabled,
                    'has_user_order' => $hasUserOrder,
                    'is_within_lead_time' => $isWithinLeadTime,
                    'lead_time_message' => $isWithinLeadTime ? 
                        'Requires ' . self::LEAD_TIME_DAYS . ' days preparation time' : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'calendar' => $calendar,
                    'vendor' => [
                        'id' => $vendor->id,
                        'store_name' => $vendor->store_name,
                        'max_orders_per_day' => $vendor->max_orders_per_day ?? 10,
                        'default_delivery_fee' => $vendor->default_delivery_fee ?? 0,
                        'lead_time_days' => self::LEAD_TIME_DAYS,
                    ],
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate,
                        'max_allowed_end' => $maxEndDate,
                        'min_allowed_start' => $minDate->toDateString(),
                        'today' => $today->toDateString(),
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Error getting reservation availability', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load calendar availability'
            ], 500);
        }
    }

    /**
     * Check if a specific date is available (PUBLIC) with 3-day lead time
     */
    public function checkDateAvailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_id' => 'required|exists:users,id',
                'reservation_date' => 'required|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendorId = $request->vendor_id;
            $requestDate = $request->reservation_date;

            $vendor = User::find($vendorId);
            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'available' => false,
                    'message' => 'Vendor not found'
                ], 404);
            }

            $reservationDate = Carbon::parse($requestDate)->startOfDay();
            $today = Carbon::today();
            $minDate = $today->copy()->addDays(self::LEAD_TIME_DAYS);
            $maxDate = $today->copy()->addMonths(3);

            // Check if date is in the past
            if ($reservationDate->lessThan($today)) {
                return response()->json([
                    'success' => true,
                    'available' => false,
                    'reason' => 'past_date',
                    'message' => 'Cannot book dates in the past'
                ]);
            }

            // Check lead time
            if ($reservationDate->lessThan($minDate)) {
                return response()->json([
                    'success' => true,
                    'available' => false,
                    'reason' => 'lead_time',
                    'message' => 'This date requires at least ' . self::LEAD_TIME_DAYS . ' days preparation time',
                    'min_date' => $minDate->toDateString(),
                ]);
            }

            // Check max date
            if ($reservationDate->greaterThan($maxDate)) {
                return response()->json([
                    'success' => true,
                    'available' => false,
                    'reason' => 'too_far',
                    'message' => 'Reservations can only be made up to 3 months in advance',
                    'max_date' => $maxDate->toDateString(),
                ]);
            }

            // Check if vendor closed the date
            if (VendorClosedDate::isDateClosed($vendorId, $requestDate)) {
                return response()->json([
                    'success' => true,
                    'available' => false,
                    'reason' => 'closed',
                    'message' => 'This date is not available for reservations'
                ]);
            }

            // Check max orders per day
            $maxOrders = $vendor->max_orders_per_day ?? 10;
            $ordersCount = Order::where('vendor_id', $vendorId)
                ->whereDate('reservation_date', $reservationDate)
                ->whereNotIn('status', ['cancelled', 'failed'])
                ->count();

            $available = $ordersCount < $maxOrders;

            return response()->json([
                'success' => true,
                'available' => $available,
                'orders_count' => $ordersCount,
                'max_orders' => $maxOrders,
                'remaining_slots' => max(0, $maxOrders - $ordersCount),
                'lead_time_days' => self::LEAD_TIME_DAYS,
                'message' => $available ? 'Date is available' : 'Date is fully booked'
            ]);

        } catch (\Throwable $e) {
            Log::error('Error checking date availability', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability'
            ], 500);
        }
    }

    /**
     * Vendor-only: get orders for a specific date
     */
    public function getOrdersForDate(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $date = $request->date;

            $orders = Order::with(['user', 'items.product'])
                ->where('vendor_id', $user->id)
                ->whereDate('reservation_date', $date)
                ->whereNotIn('status', ['cancelled'])
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'orders' => $orders,
                    'total_orders' => $orders->count(),
                ],
                'message' => 'Orders retrieved successfully'
            ]);

        } catch (\Throwable $e) {
            Log::error('Error getting orders for date', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders'
            ], 500);
        }
    }

    /**
     * Vendor-only: mark date as closed
     */
    public function markDateAsClosed(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'date' => 'required|date|after_or_equal:today',
                'reason' => 'nullable|string|max:255',
                'type' => 'nullable|in:manual,holiday,emergency',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $closedDate = VendorClosedDate::create([
                'vendor_id' => $user->id,
                'closed_date' => $request->date,
                'reason' => $request->reason,
                'type' => $request->type ?? 'manual',
            ]);

            ReservationAvailabilityCache::updateForDate($user->id, $request->date);

            return response()->json([
                'success' => true,
                'data' => $closedDate,
                'message' => 'Date marked as closed successfully'
            ]);

        } catch (\Throwable $e) {
            Log::error('Error marking date as closed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark date as closed'
            ], 500);
        }
    }

    /**
     * Helper: calendar color with lead time consideration
     */
    private function getColorForStatus(string $status, bool $hasUserOrder, bool $isWithinLeadTime): string
    {
        if ($hasUserOrder) {
            return 'green';
        }

        if ($isWithinLeadTime) {
            return 'gray'; // Gray for dates within lead time
        }

        return match ($status) {
            'closed' => 'red',
            'fully_booked' => 'orange',
            'almost_full' => 'yellow',
            default => 'white',
        };
    }
}