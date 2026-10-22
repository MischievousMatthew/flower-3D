<?php

namespace App\Services\Leave;

use App\Models\EmployeeInfo;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\EmployeeQRCodeService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Who's using the public leave page. The shop's poster QR carries a signed shop code; the
 * employee then scans their own (signed) attendance badge and gets a 30-minute session.
 */
class LeaveAccess
{
    public const SESSION_MINUTES = 30;

    public function __construct(private EmployeeQRCodeService $qr) {}

    /** "12-3f9a…" — the shop id plus a signature, so codes can't be made up */
    public function shopCode(int $ownerId): string
    {
        return $ownerId . '-' . substr(hash_hmac('sha256', "leave-shop|{$ownerId}", (string) config('app.key')), 0, 16);
    }

    public function shopFromCode(?string $code): User
    {
        [$id, $sig] = array_pad(explode('-', (string) $code, 2), 2, '');
        if (! ctype_digit($id) || ! hash_equals($this->shopCode((int) $id), (string) $code)) {
            throw ValidationException::withMessages(['shop' => 'This leave link isn\'t valid. Scan the shop\'s leave QR code again.']);
        }
        $shop = User::where('role', User::ROLE_VENDOR)->find((int) $id);
        if (! $shop) {
            throw ValidationException::withMessages(['shop' => 'This shop is no longer on BloomCraft.']);
        }

        return $shop;
    }

    public function shopInfo(User $shop): array
    {
        $application = $shop->vendorApplication;

        return [
            'name' => $application?->store_name ?: $shop->name,
            'logo' => $application?->store_logo_url,
        ];
    }

    /** Check a scanned badge for this shop and open a session. */
    public function identify(User $shop, string $badge): array
    {
        $verified = $this->qr->verify($badge, $shop->id);
        if (isset($verified['error'])) {
            throw ValidationException::withMessages(['badge' => $verified['error']]);
        }
        /** @var EmployeeInfo $employee */
        $employee = $verified['employee'];
        if (! app(AttendanceCalculator::class)->isActive($employee)) {
            throw ValidationException::withMessages(['badge' => 'Your profile is marked inactive. Talk to HR.']);
        }

        return [
            'employee' => $employee,
            'legacy' => $verified['legacy'],
            'token' => Crypt::encryptString(json_encode([
                'o' => $shop->id, 'e' => $employee->id, 'l' => $verified['legacy'],
                'x' => now()->addMinutes(self::SESSION_MINUTES)->timestamp,
            ])),
        ];
    }

    /** @return array{shop: User, employee: EmployeeInfo, legacy: bool} */
    public function session(?string $token): array
    {
        try {
            $data = json_decode(Crypt::decryptString((string) $token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['token' => 'Scan your badge again to continue.']);
        }
        if (($data['x'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['token' => 'Your session ended. Scan your badge again.']);
        }
        $shop = User::find($data['o'] ?? 0);
        $employee = $shop ? EmployeeInfo::forOwner($shop->id)->find($data['e'] ?? 0) : null;
        if (! $shop || ! $employee) {
            throw ValidationException::withMessages(['token' => 'Scan your badge again to continue.']);
        }

        return ['shop' => $shop, 'employee' => $employee, 'legacy' => (bool) ($data['l'] ?? false)];
    }
}
