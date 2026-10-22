<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSetting;
use App\Models\User;

/** Is a kiosk at the shop? Compares its GPS position with the shop pin and radius. */
class AttendanceLocationService
{
    /** Coarser fixes than this (e.g. a laptop guessing from Wi-Fi far away) aren't trusted */
    public const MAX_ACCURACY_M = 100;

    /** The GPS error we forgive on top of the radius */
    private const ACCURACY_ALLOWANCE_M = 30;

    /** The shop's settings; the first time, the location comes from the store pin on the vendor application. */
    public function settings(int $ownerId): AttendanceSetting
    {
        $settings = AttendanceSetting::firstOrNew(['owner_id' => $ownerId]);
        if (! $settings->exists) {
            $application = User::find($ownerId)?->vendorApplication;
            $settings->fill([
                'latitude' => $application?->store_latitude,
                'longitude' => $application?->store_longitude,
                'radius_m' => AttendanceSetting::DEFAULT_RADIUS_M,
                'late_grace_minutes' => AttendanceSetting::DEFAULT_LATE_GRACE,
            ])->save();
        }

        return $settings;
    }

    /**
     * @return array{ok: bool, distance_m: ?int, message: ?string}
     */
    public function check(int $ownerId, $latitude, $longitude, $accuracy): array
    {
        $settings = $this->settings($ownerId);
        if (! $settings->hasLocation()) {
            return ['ok' => false, 'distance_m' => null, 'message' => 'The shop owner hasn\'t set the shop location yet (vendor portal → Staff → Attendance devices).'];
        }
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return ['ok' => false, 'distance_m' => null, 'message' => 'Turn on location for this browser so we can check you\'re at the shop.'];
        }
        $accuracy = is_numeric($accuracy) ? (float) $accuracy : INF;
        if ($accuracy > self::MAX_ACCURACY_M) {
            return ['ok' => false, 'distance_m' => null, 'message' => 'Your location isn\'t precise enough. Turn on precise location (or GPS) and try again.'];
        }

        $distance = (int) round(self::distance($settings->latitude, $settings->longitude, (float) $latitude, (float) $longitude));
        if ($distance > $settings->radius_m + min($accuracy, self::ACCURACY_ALLOWANCE_M)) {
            return ['ok' => false, 'distance_m' => $distance, 'message' => "You're about {$distance} m from the shop. Attendance can only be taken at the shop."];
        }

        return ['ok' => true, 'distance_m' => $distance, 'message' => null];
    }

    /** Metres between two points (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
