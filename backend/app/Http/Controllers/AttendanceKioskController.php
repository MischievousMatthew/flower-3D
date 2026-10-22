<?php

namespace App\Http\Controllers;

use App\Services\Attendance\AttendanceKioskService;
use App\Services\Attendance\AttendanceLocationService;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The ERP attendance scanner. Works only on a device the shop owner approved, while it is
 * at the shop. The device token lives in the kiosk browser and is sent as device_token.
 */
class AttendanceKioskController extends Controller
{
    use ScopesOwner;

    public function __construct(
        private AttendanceKioskService $kiosk,
        private AttendanceLocationService $location,
    ) {}

    /** POST /attendance/kiosk/status {device_token} */
    public function status(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $device = $this->kiosk->device($ownerId, $request->input('device_token'));
        $settings = $this->location->settings($ownerId);

        return response()->json(['success' => true, 'data' => [
            'device' => $device ? ['name' => $device->name, 'status' => $device->status] : null,
            'location_set' => $settings->hasLocation(),
            'radius_m' => $settings->radius_m,
        ]]);
    }

    /** POST /attendance/kiosk/devices — ask the owner to allow this device (from inside the shop). */
    public function requestDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
        ] + $this->positionRules(), ['name.required' => 'Give this device a name, like "Front counter tablet".']);

        $result = $this->kiosk->requestDevice($this->getOwnerId(), $request->user(), trim($data['name']), $data, $request->userAgent());

        return response()->json([
            'success' => true,
            'message' => 'Request sent. The shop owner can approve it in the vendor portal under Staff → Attendance devices.',
            'data' => ['device_token' => $result['token'], 'device' => ['name' => $result['device']->name, 'status' => 'pending']],
        ], 201);
    }

    /** POST /attendance/kiosk/sessions — after scanning a QR code. */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_token' => 'required|string|max:120',
            'qr_data' => 'required|string|max:1000',
            'enroll' => 'sometimes|boolean',
        ] + $this->positionRules());

        $enroll = $request->boolean('enroll');
        if ($enroll && ! $this->canEnroll($request)) {
            return response()->json(['success' => false, 'message' => 'Your role can\'t enroll faces.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->kiosk->start(
            $this->getOwnerId(), $request->user(), $data['device_token'], $data['qr_data'], $data, $enroll,
        )]);
    }

    /** POST /attendance/kiosk/sessions/{id}/complete */
    public function complete(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'device_token' => 'required|string|max:120',
            'challenge_results' => 'required|array|max:10',
            'descriptor' => 'nullable|array',
            'photo_distance' => 'nullable|numeric',
            'antispoof' => 'required|numeric',
            'liveness' => 'required|numeric',
            'flash' => 'nullable|array|max:10',
            'camera' => 'nullable|array',
            'snapshots' => 'nullable|array|max:3',
        ] + $this->positionRules());

        $result = $this->kiosk->complete($this->getOwnerId(), $data['device_token'], $id, $data);

        return response()->json([
            'success' => true,
            'message' => $result['type'] === 'time_in' ? 'Time in recorded.' : 'Time out recorded.',
            'data' => $result,
        ]);
    }

    /** POST /attendance/kiosk/sessions/{id}/enroll */
    public function enroll(Request $request, string $id): JsonResponse
    {
        if (! $this->canEnroll($request)) {
            return response()->json(['success' => false, 'message' => 'Your role can\'t enroll faces.'], 403);
        }
        $data = $request->validate([
            'device_token' => 'required|string|max:120',
            'challenge_results' => 'required|array|max:10',
            'descriptors' => 'required|array|min:3|max:5',
            'antispoof' => 'required|numeric',
            'liveness' => 'required|numeric',
            'camera' => 'nullable|array',
            'snapshots' => 'nullable|array|max:5',
        ] + $this->positionRules());

        $result = $this->kiosk->enroll($this->getOwnerId(), $data['device_token'], $id, $data, $request->user());

        return response()->json(['success' => true, 'message' => "{$result['employee_name']}'s face is enrolled.", 'data' => $result]);
    }

    private function positionRules(): array
    {
        return [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
        ];
    }

    /** Enrolling sets whose face counts as whom: the owner, or HR who can edit attendance. */
    private function canEnroll(Request $request): bool
    {
        $user = $request->user();

        return ! $user instanceof \App\Models\Employee || $user->hasModulePermission('attendance', 'edit');
    }
}
