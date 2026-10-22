<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceLocationService;
use App\Services\Attendance\AttendanceReviewService;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The shop owner's side of attendance security (vendor portal → Staff): where the shop is,
 * which devices may run the scanner (up to 3), and the review queue.
 */
class VendorAttendanceController extends Controller
{
    use ResolvesOwner;

    public function __construct(
        private AttendanceLocationService $location,
        private AttendanceReviewService $reviews,
        private AttendanceCalculator $calculator,
    ) {}

    /** GET /vendor/staff/attendance/setup — location settings and devices. */
    public function setup(Request $request): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $settings = $this->location->settings($ownerId);
        $application = $request->user()->vendorApplication;

        return response()->json(['success' => true, 'data' => [
            'settings' => [
                'latitude' => $settings->latitude,
                'longitude' => $settings->longitude,
                'radius_m' => $settings->radius_m,
                'late_grace_minutes' => $settings->late_grace_minutes,
                'early_clock_in_minutes' => $settings->earlyMinutes(),
                'overtime_min_minutes' => $settings->overtimeMinimum(),
                'overtime_rate' => $settings->overtimeRate(),
            ],
            'store_pin' => $application?->store_latitude !== null ? [
                'latitude' => $application->store_latitude,
                'longitude' => $application->store_longitude,
                'address' => $application->store_address,
            ] : null,
            'devices' => $this->devices($ownerId),
            'max_devices' => AttendanceDevice::MAX_APPROVED,
        ]]);
    }

    /** PUT /vendor/staff/attendance/setup */
    public function saveSetup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_m' => 'required|integer|min:30|max:500',
            'late_grace_minutes' => 'required|integer|min:0|max:60',
            'early_clock_in_minutes' => 'sometimes|integer|min:0|max:60',
            'overtime_min_minutes' => 'sometimes|integer|min:1|max:240',
            'overtime_rate' => 'sometimes|numeric|min:1|max:3',
        ], [
            'radius_m.min' => 'Use at least 30 m — GPS isn\'t more precise than that.',
            'overtime_rate.min' => 'Overtime pays at least the normal rate (1.00).',
        ]);
        $this->location->settings($this->resolveOwnerId($request))->update($data);

        return response()->json(['success' => true, 'message' => 'Saved. Scanners must be within ' . $data['radius_m'] . ' m of this spot.']);
    }

    /** POST /vendor/staff/attendance/devices/{id}/approve */
    public function approveDevice(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $device = AttendanceDevice::where('owner_id', $ownerId)->findOrFail($id);
        if ($device->isApproved()) {
            return response()->json(['success' => true, 'message' => 'Already approved.', 'data' => $this->devices($ownerId)]);
        }
        $approved = AttendanceDevice::where('owner_id', $ownerId)->where('status', 'approved')->count();
        if ($approved >= AttendanceDevice::MAX_APPROVED) {
            throw ValidationException::withMessages(['device' => 'You can approve up to ' . AttendanceDevice::MAX_APPROVED . ' devices. Remove one first.']);
        }
        if ($device->request_distance_m === null) {
            throw ValidationException::withMessages(['device' => 'This device wasn\'t requested from the shop. Ask HR to request it again while at the shop.']);
        }
        $device->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

        return response()->json(['success' => true, 'message' => "{$device->name} can now take attendance at the shop.", 'data' => $this->devices($ownerId)]);
    }

    /** POST /vendor/staff/attendance/devices/{id}/revoke */
    public function revokeDevice(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $device = AttendanceDevice::where('owner_id', $ownerId)->findOrFail($id);
        $device->forceFill(['status' => 'revoked'])->save();

        return response()->json(['success' => true, 'message' => "{$device->name} can no longer take attendance.", 'data' => $this->devices($ownerId)]);
    }

    /** PUT /vendor/staff/attendance/devices/{id} {name} */
    public function renameDevice(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $data = $request->validate(['name' => 'required|string|max:80']);
        AttendanceDevice::where('owner_id', $ownerId)->findOrFail($id)->update(['name' => trim($data['name'])]);

        return response()->json(['success' => true, 'message' => 'Renamed.', 'data' => $this->devices($ownerId)]);
    }

    /** DELETE /vendor/staff/attendance/devices/{id} */
    public function deleteDevice(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        AttendanceDevice::where('owner_id', $ownerId)->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Device removed.', 'data' => $this->devices($ownerId)]);
    }

    /** GET /vendor/staff/attendance/review */
    public function reviewQueue(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->reviews->queue($this->resolveOwnerId($request))]);
    }

    /** POST /vendor/staff/attendance/review/{id} {decision, note} */
    public function review(Request $request, int $id): JsonResponse
    {
        $data = $this->validateDecision($request);
        $this->reviews->decideRecord($this->resolveOwnerId($request), $id, $data['decision'], $data['note'] ?? null, $request->user());

        return response()->json(['success' => true, 'message' => $data['decision'] === 'approve' ? 'Approved. It now counts for payroll.' : 'Rejected. It won\'t count for payroll.']);
    }

    /** POST /vendor/staff/attendance/corrections/{id} {decision, note} */
    public function reviewCorrection(Request $request, int $id): JsonResponse
    {
        $data = $this->validateDecision($request);
        $this->reviews->decideCorrection($this->resolveOwnerId($request), $id, $data['decision'], $data['note'] ?? null, $request->user());

        return response()->json(['success' => true, 'message' => $data['decision'] === 'approve' ? 'Correction approved and applied.' : 'Correction rejected.']);
    }

    /** POST /vendor/staff/attendance/overtime/{id} {decision, minutes?, note} */
    public function overtime(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'minutes' => 'nullable|integer|min:1|max:1440',
            'note' => 'nullable|string|max:500',
        ]);
        $record = $this->reviews->decideOvertime($this->resolveOwnerId($request), $id, $data['decision'], $data['minutes'] ?? null, $data['note'] ?? null, $request->user());

        return response()->json(['success' => true, 'message' => AttendanceController::overtimeMessage($record)]);
    }

    /** GET /vendor/staff/attendance/{id}/evidence/{which}/{index} */
    public function evidence(Request $request, int $id, string $which, int $index): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['url' => $this->reviews->evidenceLink($this->resolveOwnerId($request), $id, $which, $index)]]);
    }

    /** GET /vendor/staff/attendance/report?from&to */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'employee_id' => 'nullable|integer']);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        if ($from->diffInDays($to) > AttendanceCalculator::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['from' => 'Choose a range of three months or less.']);
        }

        return response()->json(['success' => true, 'data' => $this->calculator->report($this->resolveOwnerId($request), $from, $to, $data['employee_id'] ?? null)]);
    }

    private function devices(int $ownerId): array
    {
        return AttendanceDevice::where('owner_id', $ownerId)
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->latest()->get()
            ->map(fn (AttendanceDevice $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'status' => $d->status,
                'requested_by' => $d->requested_by,
                'requested_at' => $d->created_at->toIso8601String(),
                'request_distance_m' => $d->request_distance_m,
                'browser' => $this->browser($d->user_agent),
                'approved_at' => $d->approved_at?->toIso8601String(),
                'last_used_at' => $d->last_used_at?->toIso8601String(),
            ])->values()->all();
    }

    /** "Chrome on Android" from a user agent */
    private function browser(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'])
            ->first(fn ($label, $needle) => str_contains($ua, $needle)) ?? 'Browser';
        $os = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'Mac', 'Linux' => 'Linux'])
            ->first(fn ($label, $needle) => str_contains($ua, $needle));

        return $os ? "{$browser} on {$os}" : $browser;
    }

    private function validateDecision(Request $request): array
    {
        return $request->validate(['decision' => 'required|in:approve,reject', 'note' => 'nullable|string|max:500']);
    }
}
