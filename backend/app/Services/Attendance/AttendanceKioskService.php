<?php

namespace App\Services\Attendance;

use App\Helpers\CloudinaryHelper;
use App\Models\AttendanceDevice;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\FaceEnrollment;
use App\Services\EmployeeQRCodeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The shop scanner. The server decides everything that matters: which device may scan, whether
 * it's at the shop, which challenges to do, whether the face matches an enrolled face, and
 * whether a scan needs a person to review it. See docs/superpowers/specs/2026-10-22-attendance-security-design.md
 */
class AttendanceKioskService
{
    public const CHALLENGES = ['blink', 'smile', 'turn_left', 'turn_right', 'move_closer', 'move_back', 'nod'];
    public const FLASH_COLORS = ['red', 'green', 'blue'];

    // Face distances (face-api 128-d descriptors): lower is more alike
    public const MATCH_OK = 0.45;
    public const MATCH_REVIEW = 0.55;
    public const PHOTO_MATCH_MAX = 0.50;
    public const ENROLL_SPREAD_MAX = 0.50;

    // Anti-spoof / liveness model scores, 0–1 (higher is more likely real)
    public const SCORE_OK = 0.6;
    public const SCORE_REJECT = 0.3;

    public const MIN_SESSION_MS = 4000;
    public const CHALLENGE_MIN_MS = 300;
    public const CHALLENGE_MAX_MS = 10000;
    public const MAX_PENDING_DEVICES = 5;
    public const MAX_SNAPSHOT_BYTES = 150 * 1024;

    public function __construct(
        private AttendanceLocationService $location,
        private EmployeeQRCodeService $qr,
    ) {}

    // ── Devices ────────────────────────────────────────────────────────────

    /** The device a kiosk token belongs to ("id.secret"), whatever its state. */
    public function device(int $ownerId, ?string $token): ?AttendanceDevice
    {
        if (! $token || ! str_contains($token, '.')) {
            return null;
        }
        [$id, $secret] = explode('.', $token, 2);
        $device = ctype_digit($id) ? AttendanceDevice::where('owner_id', $ownerId)->find((int) $id) : null;

        return $device && hash_equals($device->token_hash, hash('sha256', $secret)) ? $device : null;
    }

    /** Ask the owner to allow this device. Only possible from inside the shop. */
    public function requestDevice(int $ownerId, $operator, string $name, array $position, ?string $userAgent): array
    {
        $check = $this->location->check($ownerId, $position['latitude'] ?? null, $position['longitude'] ?? null, $position['accuracy'] ?? null);
        if (! $check['ok']) {
            throw ValidationException::withMessages(['location' => $check['message']]);
        }
        if (AttendanceDevice::where('owner_id', $ownerId)->where('status', 'pending')->count() >= self::MAX_PENDING_DEVICES) {
            throw ValidationException::withMessages(['name' => 'There are already several devices waiting. Ask the shop owner to approve or remove them first.']);
        }

        $secret = Str::random(48);
        $device = AttendanceDevice::create([
            'owner_id' => $ownerId,
            'name' => $name,
            'token_hash' => hash('sha256', $secret),
            'status' => 'pending',
            'requested_by' => $operator?->name ?? 'Shop owner',
            'requested_by_employee_id' => $operator instanceof Employee ? $operator->id : null,
            'request_latitude' => $position['latitude'],
            'request_longitude' => $position['longitude'],
            'request_distance_m' => $check['distance_m'],
            'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
        ]);

        return ['device' => $device, 'token' => "{$device->id}.{$secret}"];
    }

    // ── Sessions ───────────────────────────────────────────────────────────

    /** Scan a QR code: checks device, location and code, then hands out a one-time session. */
    public function start(int $ownerId, $operator, ?string $token, string $qrText, array $position, bool $enroll): array
    {
        $device = $this->usableDevice($ownerId, $token, $position);

        $verified = $this->qr->verify($qrText, $ownerId);
        if (isset($verified['error'])) {
            throw ValidationException::withMessages(['qr_data' => $verified['error']]);
        }
        /** @var EmployeeInfo $employee */
        $employee = $verified['employee'];
        if (! app(AttendanceCalculator::class)->isActive($employee)) {
            throw ValidationException::withMessages(['qr_data' => "{$employee->full_name} is marked inactive, so they can't time in."]);
        }

        $enrolled = FaceEnrollment::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)->exists();
        $flags = [];
        if ($enroll) {
            $purpose = 'enroll';
            if ($verified['legacy']) {
                throw ValidationException::withMessages(['qr_data' => 'Print a new QR code for this person first (old codes can\'t be used to enroll).']);
            }
        } else {
            $today = EmployeeAttendance::forOwner($ownerId)->where('employee_id', $employee->id)
                ->whereDate('attendance_date', now()->toDateString())->first();
            if ($today && $today->time_out) {
                throw ValidationException::withMessages(['qr_data' => "{$employee->full_name} already timed in and out today. Ask for a correction if that's wrong."]);
            }
            $purpose = $today ? 'time_out' : 'time_in';
            if ($purpose === 'time_in') {
                $this->guardClockIn($ownerId, $employee);
            }
            if (! $enrolled && ! $employee->avatar_url) {
                throw ValidationException::withMessages(['qr_data' => "{$employee->full_name} has no enrolled face or profile photo yet. Enroll their face first."]);
            }
            if ($verified['legacy']) {
                $flags[] = 'old_qr';
            }
            if (! $enrolled) {
                $flags[] = 'not_enrolled';
            }
        }

        $session = AttendanceSession::create([
            'owner_id' => $ownerId,
            'device_id' => $device->id,
            'employee_info_id' => $employee->id,
            'purpose' => $purpose,
            'challenges' => $this->pickChallenges(),
            'flash' => $this->pickFlash(),
            'flags' => $flags,
            'operator_employee_id' => $operator instanceof Employee ? $operator->id : null,
            'operator_name' => $operator?->name,
            'expires_at' => now()->addSeconds(AttendanceSession::TTL_SECONDS),
        ]);
        $device->forceFill(['last_used_at' => now()])->save();

        return [
            'session_id' => $session->id,
            'purpose' => $purpose,
            'challenges' => $session->challenges,
            'flash' => $session->flash,
            'expires_at' => $session->expires_at->toIso8601String(),
            'enrolled' => $enrolled,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
                'position' => $employee->position,
                // Only needed to compare against when no face is enrolled yet
                'avatar_url' => $enrolled ? null : $employee->avatar_url,
            ],
        ];
    }

    /**
     * Finish a time in / time out. Clear failures are refused; weaker signals still record
     * the time but mark it "needs review" with the reasons.
     */
    public function complete(int $ownerId, ?string $token, string $sessionId, array $data): array
    {
        $session = $this->openSession($ownerId, $token, $sessionId, $data, ['time_in', 'time_out']);
        $employee = $session->employee;
        $flags = $session->flags ?? [];
        $reasons = [];
        $signals = [];

        try {
            $this->checkChallenges($session, $data);
            $this->checkCamera($data);
            $signals = $this->checkScores($data, $reasons);
            $signals['flash'] = $this->checkFlash($session, $data, $reasons);
            $signals['face_distance'] = $this->checkFace($ownerId, $employee, $data, in_array('not_enrolled', $flags, true), $reasons);
        } catch (ValidationException $e) {
            $this->closeSession($session, 'blocked', collect($e->errors())->flatten()->first());
            throw $e;
        }

        if (in_array('old_qr', $flags, true)) {
            $reasons[] = 'Old QR code — print the new one for this person.';
        }
        if (in_array('not_enrolled', $flags, true)) {
            $reasons[] = 'Face not enrolled yet — matched against the profile photo.';
        }

        $photos = $this->storeSnapshots($ownerId, $data['snapshots'] ?? [], "{$employee->id}-{$session->purpose}");
        if (! $photos) {
            $reasons[] = 'The evidence photos couldn\'t be saved.';
        }

        $evidence = array_merge($signals, [
            'photos' => $photos,
            'device' => $session->device->name,
            'device_id' => $session->device_id,
            'operator' => $session->operator_name,
            'distance_m' => $data['_distance_m'] ?? null,
            'session' => $session->id,
            'at' => now()->toIso8601String(),
        ]);

        try {
            $result = DB::transaction(fn () => $this->record($ownerId, $employee, $session->purpose, $evidence, $reasons));
        } catch (ValidationException $e) {
            $this->closeSession($session, 'blocked', collect($e->errors())->flatten()->first());
            throw $e;
        }
        $this->closeSession($session, $reasons ? 'needs_review' : 'ok', $reasons ? implode(' ', $reasons) : null);

        return $result;
    }

    /** Enroll a face: 3–5 captures that agree with each other, with strong liveness. */
    public function enroll(int $ownerId, ?string $token, string $sessionId, array $data, $operator): array
    {
        $session = $this->openSession($ownerId, $token, $sessionId, $data, ['enroll']);

        try {
            $this->checkChallenges($session, $data);
            $this->checkCamera($data);
            $reasons = [];
            $this->checkScores($data, $reasons);
            if ($reasons) {
                throw ValidationException::withMessages(['liveness' => 'The live-face check wasn\'t strong enough to enroll. Use good light and try again.']);
            }
            $descriptors = collect($data['descriptors'] ?? [])->map(fn ($d) => $this->descriptor($d))->values();
            if ($descriptors->count() < 3 || $descriptors->count() > 5) {
                throw ValidationException::withMessages(['descriptors' => 'Enrollment needs 3 to 5 captures.']);
            }
            foreach ($descriptors as $i => $a) {
                foreach ($descriptors->slice($i + 1) as $b) {
                    if (self::faceDistance($a, $b) > self::ENROLL_SPREAD_MAX) {
                        throw ValidationException::withMessages(['descriptors' => 'The captures don\'t look like the same person. Try again with only one face in view.']);
                    }
                }
            }
        } catch (ValidationException $e) {
            $this->closeSession($session, 'blocked', collect($e->errors())->flatten()->first());
            throw $e;
        }

        $employee = $session->employee;
        $photos = $this->storeSnapshots($ownerId, $data['snapshots'] ?? [], "{$employee->id}-enroll");
        $old = FaceEnrollment::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)->first();
        FaceEnrollment::updateOrCreate(
            ['owner_id' => $ownerId, 'employee_info_id' => $employee->id],
            ['descriptors' => $descriptors->all(), 'photos' => $photos, 'enrolled_by' => $operator?->name],
        );
        foreach ($old?->photos ?? [] as $publicId) {
            CloudinaryHelper::destroy($publicId, ['type' => 'authenticated']);
        }
        $this->closeSession($session, 'enrolled', null);

        return ['employee_name' => $employee->full_name, 'captures' => $descriptors->count()];
    }

    // ── Checks ─────────────────────────────────────────────────────────────

    /**
     * People time in only on a scheduled day, from their shift's start (or as many minutes
     * before as the shop allows), and not after the shift is over.
     */
    private function guardClockIn(int $ownerId, EmployeeInfo $employee): void
    {
        $calculator = app(AttendanceCalculator::class);
        $now = now();
        $expected = $calculator->expectedOn($ownerId, $employee, $now->toDateString());
        if (! $expected) {
            throw ValidationException::withMessages(['qr_data' => "{$employee->full_name} isn't scheduled to work today. HR can put them on a shift in Schedules."]);
        }
        $early = $this->location->settings($ownerId)->earlyMinutes();
        $start = Carbon::parse($now->toDateString() . ' ' . $expected['start']);
        $opens = $start->copy()->subMinutes($early);
        if ($now->lt($opens)) {
            throw ValidationException::withMessages(['qr_data' => sprintf(
                "%s's shift starts at %s. Time in from %s.",
                $employee->full_name, $start->format('g:i A'), $opens->format('g:i A'),
            )]);
        }
        if ($now->format('H:i') >= $expected['end']) {
            throw ValidationException::withMessages(['qr_data' => sprintf(
                "%s's shift ended at %s. Ask HR for a correction if they worked.",
                $employee->full_name, Carbon::parse($now->toDateString() . ' ' . $expected['end'])->format('g:i A'),
            )]);
        }
    }

    private function usableDevice(int $ownerId, ?string $token, array $position): AttendanceDevice
    {
        $device = $this->device($ownerId, $token);
        if (! $device) {
            throw ValidationException::withMessages(['device' => 'This device isn\'t set up for attendance. Request access from the scanner page.']);
        }
        if (! $device->isApproved()) {
            throw ValidationException::withMessages(['device' => $device->status === 'pending'
                ? 'This device is waiting for the shop owner\'s approval.'
                : 'The shop owner removed this device from attendance.']);
        }
        $check = $this->location->check($ownerId, $position['latitude'] ?? null, $position['longitude'] ?? null, $position['accuracy'] ?? null);
        if (! $check['ok']) {
            throw ValidationException::withMessages(['location' => $check['message']]);
        }

        return $device;
    }

    private function openSession(int $ownerId, ?string $token, string $sessionId, array &$data, array $purposes): AttendanceSession
    {
        $device = $this->usableDevice($ownerId, $token, $data);
        $data['_distance_m'] = $this->location->check($ownerId, $data['latitude'] ?? null, $data['longitude'] ?? null, $data['accuracy'] ?? null)['distance_m'];

        $session = AttendanceSession::where('owner_id', $ownerId)->with(['employee', 'device'])->find($sessionId);
        if (! $session || $session->device_id !== $device->id || ! in_array($session->purpose, $purposes, true)) {
            throw ValidationException::withMessages(['session' => 'This scan isn\'t valid. Scan the QR code again.']);
        }
        if ($session->used_at) {
            throw ValidationException::withMessages(['session' => 'This scan was already used. Scan the QR code again.']);
        }
        if ($session->expires_at->isPast()) {
            $this->closeSession($session, 'expired', null);
            throw ValidationException::withMessages(['session' => 'That took too long. Scan the QR code again.']);
        }
        if ($session->created_at->diffInMilliseconds(now()) < self::MIN_SESSION_MS) {
            $this->closeSession($session, 'blocked', 'Finished impossibly fast.');
            throw ValidationException::withMessages(['session' => 'The checks finished too fast to be real. Scan the QR code again.']);
        }
        if (! $session->employee) {
            throw ValidationException::withMessages(['session' => 'This person\'s profile was removed.']);
        }

        return $session;
    }

    private function checkChallenges(AttendanceSession $session, array $data): void
    {
        $results = array_values($data['challenge_results'] ?? []);
        $expected = $session->challenges;
        if (count($results) !== count($expected)) {
            throw ValidationException::withMessages(['challenges' => 'Not every check was done. Scan the QR code again.']);
        }
        foreach ($expected as $i => $id) {
            $r = $results[$i] ?? [];
            if (($r['id'] ?? null) !== $id || empty($r['passed'])) {
                throw ValidationException::withMessages(['challenges' => 'The checks weren\'t done in the order asked. Scan the QR code again.']);
            }
            $ms = (int) ($r['duration_ms'] ?? 0);
            if ($ms < self::CHALLENGE_MIN_MS || $ms > self::CHALLENGE_MAX_MS) {
                throw ValidationException::withMessages(['challenges' => 'A check was done too fast or too slow. Scan the QR code again.']);
            }
        }
    }

    private function checkCamera(array $data): void
    {
        if (! empty($data['camera']['virtual'])) {
            throw ValidationException::withMessages(['camera' => 'A virtual camera was detected. Use the device\'s real camera.']);
        }
    }

    /** Anti-spoof and liveness model scores. */
    private function checkScores(array $data, array &$reasons): array
    {
        $out = [];
        foreach (['antispoof' => 'Screen/photo check', 'liveness' => 'Live-face check'] as $key => $label) {
            $score = $data[$key] ?? null;
            if (! is_numeric($score)) {
                throw ValidationException::withMessages([$key => 'The live-face check didn\'t run. Reload the scanner and try again.']);
            }
            $score = max(0, min(1, (float) $score));
            if ($score < self::SCORE_REJECT) {
                throw ValidationException::withMessages([$key => 'This looks like a photo or a screen, not a live face.']);
            }
            if ($score < self::SCORE_OK) {
                $reasons[] = sprintf('%s score was low (%.2f).', $label, $score);
            }
            $out[$key] = round($score, 3);
        }

        return $out;
    }

    /** Did the face reflect the colors the screen flashed, in order? Screens replaying a video don't. */
    private function checkFlash(AttendanceSession $session, array $data, array &$reasons): ?int
    {
        $samples = array_values($data['flash'] ?? []);
        $matches = 0;
        foreach ($session->flash as $i => $color) {
            $s = $samples[$i] ?? null;
            $delta = $s['delta'] ?? null;
            if (($s['color'] ?? null) !== $color || ! is_array($delta) || count($delta) !== 3) {
                continue;
            }
            $channel = array_search($color, self::FLASH_COLORS, true);
            $d = array_map('floatval', $delta);
            $others = $d;
            unset($others[$channel]);
            if ($d[$channel] > 1.5 && $d[$channel] > max($others)) {
                $matches++;
            }
        }
        $needed = count($session->flash) - 1;
        if ($matches < $needed) {
            $reasons[] = "The face didn't reflect the screen's color flashes well ({$matches} of " . count($session->flash) . ').';
        }

        return $matches;
    }

    /** Server-side match against enrolled faces; before enrollment, the browser's comparison with the profile photo. */
    private function checkFace(int $ownerId, EmployeeInfo $employee, array $data, bool $notEnrolled, array &$reasons): float
    {
        if ($notEnrolled) {
            $distance = $data['photo_distance'] ?? null;
            if (! is_numeric($distance) || (float) $distance > self::PHOTO_MATCH_MAX) {
                throw ValidationException::withMessages(['face' => "This face doesn't match {$employee->full_name}'s photo."]);
            }

            return round((float) $distance, 3);
        }

        $live = $this->descriptor($data['descriptor'] ?? null);
        $enrollment = FaceEnrollment::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)->first();
        $distance = collect($enrollment?->descriptors ?? [])->map(fn ($d) => self::faceDistance($live, $d))->min();
        if ($distance === null || $distance > self::MATCH_REVIEW) {
            throw ValidationException::withMessages(['face' => "This face doesn't match {$employee->full_name}."]);
        }
        if ($distance > self::MATCH_OK) {
            $reasons[] = sprintf('The face match was borderline (%.2f).', $distance);
        }

        return round($distance, 3);
    }

    private function descriptor($value): array
    {
        if (! is_array($value) || count($value) !== 128) {
            throw ValidationException::withMessages(['descriptor' => 'The face reading was incomplete. Scan the QR code again.']);
        }
        $out = [];
        foreach ($value as $v) {
            if (! is_numeric($v) || ! is_finite((float) $v) || abs((float) $v) > 2) {
                throw ValidationException::withMessages(['descriptor' => 'The face reading was invalid. Scan the QR code again.']);
            }
            $out[] = round((float) $v, 6);
        }

        return $out;
    }

    public static function faceDistance(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $v) {
            $sum += ($v - (float) ($b[$i] ?? 0)) ** 2;
        }

        return sqrt($sum);
    }

    // ── Recording ──────────────────────────────────────────────────────────

    private function record(int $ownerId, EmployeeInfo $employee, string $purpose, array $evidence, array $reasons): array
    {
        $now = now();
        $today = $now->toDateString();
        $attendance = EmployeeAttendance::forOwner($ownerId)->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today)->lockForUpdate()->first();
        $note = sprintf('Face verified at the shop scanner (%s)', $evidence['device']);

        if ($purpose === 'time_in') {
            if ($attendance) {
                throw ValidationException::withMessages(['session' => "{$employee->full_name} already timed in today."]);
            }
            $attendance = EmployeeAttendance::forOwner($ownerId)->create([
                'owner_id' => $ownerId,
                'employee_id' => $employee->id,
                'attendance_date' => $today,
                'day' => $now->format('l'),
                'month' => (int) $now->format('n'),
                'year' => (int) $now->format('Y'),
                'time_in' => $now->format('H:i:s'),
                'status' => 'incomplete',
                'notes' => $note,
                'source' => 'kiosk',
                'time_in_evidence' => $evidence + ['reasons' => $reasons],
                'review_status' => $reasons ? 'pending' : null,
                'review_reasons' => $reasons ?: null,
            ]);
        } else {
            if (! $attendance || $attendance->time_out) {
                throw ValidationException::withMessages(['session' => "{$employee->full_name} has no open time in today."]);
            }
            $in = Carbon::parse($today . ' ' . $attendance->time_in);
            $attendance->fill([
                'time_out' => $now->format('H:i:s'),
                'total_hours' => round(abs($now->timestamp - $in->timestamp) / 3600, 2),
                'status' => 'complete',
                'notes' => trim(($attendance->notes ? $attendance->notes . ' | ' : '') . 'Time out ' . lcfirst($note)),
                'time_out_evidence' => $evidence + ['reasons' => $reasons],
            ]);
            if ($reasons) {
                // An approved time in doesn't cover a doubtful time out: review the day again
                $earlier = $attendance->review_status === 'pending' ? ($attendance->review_reasons ?? []) : [];
                $attendance->review_reasons = array_values(array_merge($earlier, array_map(fn ($r) => "Time out: {$r}", $reasons)));
                $attendance->review_status = 'pending';
                $attendance->reviewed_by = null;
                $attendance->reviewed_at = null;
            }
            // Staying past the shift's end is overtime, which HR approves before it's paid
            app(AttendanceCalculator::class)->applyOvertime($ownerId, $attendance, $employee);
            $attendance->save();
        }

        $calculator = app(AttendanceCalculator::class);
        $facts = $calculator->facts($attendance, $calculator->expectedOn($ownerId, $employee, $today), $this->location->settings($ownerId)->late_grace_minutes);

        return [
            'type' => $purpose,
            'needs_review' => (bool) $reasons,
            'reasons' => $reasons,
            'late_minutes' => $facts['late_minutes'],
            'undertime_minutes' => $purpose === 'time_out' ? $facts['undertime_minutes'] : null,
            'overtime_minutes' => $attendance->overtime_minutes,
            'employee_name' => $employee->full_name,
            'employee_id' => $employee->employee_id,
            'time_in' => Carbon::parse($attendance->time_in)->format('h:i A'),
            'time_out' => $attendance->time_out ? Carbon::parse($attendance->time_out)->format('h:i A') : null,
            'total_hours' => $attendance->total_hours !== null ? number_format((float) $attendance->total_hours, 2) : null,
            'date' => $now->format('M d, Y'),
        ];
    }

    /** Small JPEG snapshots, stored privately as evidence. Returns their Cloudinary ids. */
    protected function storeSnapshots(int $ownerId, $snapshots, string $label): array
    {
        $ids = [];
        foreach (array_slice(is_array($snapshots) ? $snapshots : [], 0, 5) as $i => $dataUrl) {
            if (! is_string($dataUrl) || ! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
                continue;
            }
            $bytes = base64_decode($m[1], true);
            if ($bytes === false || strlen($bytes) > self::MAX_SNAPSHOT_BYTES || ! str_starts_with($bytes, "\xFF\xD8")) {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'att_');
            file_put_contents($tmp, $bytes);
            try {
                $upload = CloudinaryHelper::upload($tmp, [
                    'folder' => "attendance-evidence/{$ownerId}",
                    'public_id' => $label . '-' . now()->format('Ymd-His') . "-{$i}-" . Str::lower(Str::random(6)),
                    'type' => 'authenticated',
                    'resource_type' => 'image',
                ]);
                $ids[] = $upload['public_id'];
            } catch (\Throwable $e) {
                Log::warning('Attendance snapshot upload failed', ['error' => $e->getMessage()]);
            } finally {
                @unlink($tmp);
            }
        }

        return $ids;
    }

    private function closeSession(AttendanceSession $session, string $outcome, ?string $reason): void
    {
        $session->forceFill([
            'used_at' => now(),
            'flags' => array_merge($session->flags ?? [], ['outcome' => $outcome, 'reason' => $reason]),
        ])->save();
    }

    private function pickChallenges(): array
    {
        $pick = fn (array $list) => $list[random_int(0, count($list) - 1)];
        $chosen = [
            $pick(['blink', 'smile']),
            $pick(['turn_left', 'turn_right']),
            $pick(['move_closer', 'move_back']),
        ];
        $extra = array_values(array_diff(self::CHALLENGES, $chosen, ['move_closer', 'move_back']));
        $chosen[] = $pick($extra);

        // Fisher–Yates with a secure random source
        for ($i = count($chosen) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chosen[$i], $chosen[$j]] = [$chosen[$j], $chosen[$i]];
        }

        return $chosen;
    }

    private function pickFlash(): array
    {
        $out = [];
        while (count($out) < 4) {
            $color = self::FLASH_COLORS[random_int(0, count(self::FLASH_COLORS) - 1)];
            if (end($out) !== $color) {
                $out[] = $color;
            }
        }

        return $out;
    }
}
