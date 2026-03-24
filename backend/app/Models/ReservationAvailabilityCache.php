<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationAvailabilityCache extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'date',
        'orders_count',
        'max_orders',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'orders_count' => 'integer',
        'max_orders' => 'integer',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    // Scopes
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeInRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeFullyBooked($query)
    {
        return $query->where('status', 'fully_booked');
    }

    // Helper methods
    public static function updateForDate($vendorId, $date, $maxOrders = null)
    {
        $ordersCount = Order::where('vendor_id', $vendorId)
            ->where('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->count();

        if ($maxOrders === null) {
            $vendor = VendorApplication::where('email', User::find($vendorId)->email)->first();
            $maxOrders = $vendor->max_orders_per_day ?? 10;
        }

        $isClosed = VendorClosedDate::isDateClosed($vendorId, $date);

        $status = $isClosed ? 'closed' : static::calculateStatus($ordersCount, $maxOrders);

        return static::updateOrCreate(
            ['vendor_id' => $vendorId, 'date' => $date],
            [
                'orders_count' => $ordersCount,
                'max_orders' => $maxOrders,
                'status' => $status,
            ]
        );
    }

    private static function calculateStatus(int $ordersCount, int $maxOrders): string
    {
        if ($ordersCount >= $maxOrders) {
            return 'fully_booked';
        } elseif ($ordersCount >= ($maxOrders * 0.8)) {
            return 'almost_full';
        }
        return 'available';
    }

    public static function getAvailabilityForRange($vendorId, $startDate, $endDate): array
    {
        $data = static::forVendor($vendorId)
            ->inRange($startDate, $endDate)
            ->get()
            ->keyBy(fn($item) => $item->date->format('Y-m-d'));

        $result = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $cached = $data->get($dateStr);

            if ($cached) {
                $result[$dateStr] = [
                    'status' => $cached->status,
                    'orders_count' => $cached->orders_count,
                    'max_orders' => $cached->max_orders,
                    'available_slots' => $cached->max_orders - $cached->orders_count,
                ];
            } else {
                // Cache miss - generate on the fly
                $availability = static::updateForDate($vendorId, $dateStr);
                $result[$dateStr] = [
                    'status' => $availability->status,
                    'orders_count' => $availability->orders_count,
                    'max_orders' => $availability->max_orders,
                    'available_slots' => $availability->max_orders - $availability->orders_count,
                ];
            }

            $current->modify('+1 day');
        }

        return $result;
    }
}