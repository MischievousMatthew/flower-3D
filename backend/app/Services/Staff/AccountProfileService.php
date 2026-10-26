<?php

namespace App\Services\Staff;

use App\Models\Employee;
use App\Models\EmployeeInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A team account (someone who signs in to the ERP) always has an HR profile,
 * so they get a QR code for attendance and HR can see them. The profile starts
 * with what the account knows; HR completes the rest.
 */
class AccountProfileService
{
    public static function ready(): bool
    {
        return Schema::hasColumn('employees_info', 'account_id');
    }

    /** The account's HR profile: the linked one, one with the same email, or a new draft. */
    public function ensureProfile(Employee $account): ?EmployeeInfo
    {
        if (! self::ready() || ! $account->owner_id) {
            return null;
        }

        $profile = EmployeeInfo::forOwner($account->owner_id)->where('account_id', $account->id)->first()
            ?? $this->byEmail($account);
        if ($profile) {
            if (! $profile->account_id) {
                $profile->forceFill(['account_id' => $account->id])->save();
            }
            $this->fillBlanks($profile, $account);

            return $profile;
        }

        [$first, $last] = $this->splitName($account->name);
        $role = $account->staffRole?->name;

        $profile = new EmployeeInfo(array_intersect_key([
            'owner_id' => $account->owner_id,
            'employee_id' => $this->nextEmployeeId($account->owner_id),
            'first_name' => $first,
            'last_name' => $last,
            'work_email' => $account->email,
            'mobile_number' => $account->phone,
            'address' => $account->address,
            'position' => $account->role ?: $role,
            'department' => $role,
            'date_hired' => $account->joining_date ?? now(),
            'employment_status' => $this->statusFor($account) ?? 'Active',
            'employment_type' => 'Full-time',
            'work_schedule' => 'Fixed',
            'standard_work_hours_per_day' => 8,
            'working_days_per_week' => 6,
            'working_days_per_month' => 26,
        ], array_flip(Schema::getColumnListing('employees_info'))));
        $profile->forceFill(['account_id' => $account->id])->save();

        return $profile;
    }

    /** A profile HR adds for someone who already has a team account belongs to that account. */
    public function linkByEmail(EmployeeInfo $profile): void
    {
        if (! self::ready() || $profile->account_id) {
            return;
        }
        $emails = array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), [$profile->work_email, $profile->personal_email]));
        if (! $emails) {
            return;
        }
        $taken = EmployeeInfo::forOwner($profile->owner_id)->whereNotNull('account_id')->pluck('account_id');
        $accountId = Employee::where('owner_id', $profile->owner_id)->whereNotIn('id', $taken)
            ->whereIn(DB::raw('LOWER(email)'), $emails)->value('id');
        if ($accountId) {
            $profile->forceFill(['account_id' => $accountId])->save();
        }
    }

    /** Account changes reach the profile only where HR left a blank, plus leaving or coming back. */
    private function fillBlanks(EmployeeInfo $profile, Employee $account): void
    {
        foreach (['mobile_number' => $account->phone, 'address' => $account->address, 'position' => $account->role] as $column => $value) {
            if ($value && blank($profile->{$column})) {
                $profile->{$column} = $value;
            }
        }
        $status = $this->statusFor($account);
        if ($status === 'Inactive' || ($status === 'Active' && $profile->employment_status === 'Inactive')) {
            $profile->employment_status = $status;
        }
        if ($profile->isDirty()) {
            $profile->save();
        }
    }

    /** Resigned accounts make the profile Inactive; active again makes it Active. Anything else is HR's call. */
    private function statusFor(Employee $account): ?string
    {
        return match ($account->status) {
            'Resign' => 'Inactive',
            'Active' => 'Active',
            default => null,
        };
    }

    private function byEmail(Employee $account): ?EmployeeInfo
    {
        $email = strtolower(trim((string) $account->email));
        if ($email === '') {
            return null;
        }

        return EmployeeInfo::forOwner($account->owner_id)->whereNull('account_id')
            ->where(fn ($q) => $q->whereRaw('LOWER(work_email) = ?', [$email])->orWhereRaw('LOWER(personal_email) = ?', [$email]))
            ->orderBy('id')->first();
    }

    /** "Ana Maria Cruz" → ["Ana Maria", "Cruz"] */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [$name];
        $last = count($parts) > 1 ? array_pop($parts) : null;

        return [implode(' ', $parts), $last];
    }

    /** EMP-10001, EMP-10002 … per shop */
    public function nextEmployeeId(int $ownerId): string
    {
        $last = EmployeeInfo::withTrashed()->forOwner($ownerId)->where('employee_id', 'like', 'EMP-%')->orderByDesc('id')->value('employee_id');

        return 'EMP-' . ($last && preg_match('/EMP-(\d+)/', $last, $m) ? (int) $m[1] + 1 : 10001);
    }
}
