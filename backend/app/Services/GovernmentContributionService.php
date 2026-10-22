<?php

namespace App\Services;

/**
 * The employee's share of government contributions, per month. Payroll takes the share
 * of a month each payroll covers (PayrollService::contributionShare), so weekly or
 * half-month payrolls don't each take a whole month's worth.
 *
 * Rates as of 2025 — check them against the agencies' current tables each year:
 *  - SSS: 15% of the monthly salary credit (MSC), employee pays 5%. MSC is the salary rounded
 *    to the nearest ₱500, from ₱5,000 to ₱35,000.
 *  - PhilHealth: 5% premium on salary between ₱10,000 and ₱100,000, employee pays half (2.5%).
 *  - Pag-IBIG: employee pays 1% (salary ≤ ₱1,500) or 2%, on salary up to ₱10,000.
 */
class GovernmentContributionService
{
    public const SSS_EMPLOYEE_RATE = 0.05;
    public const SSS_MSC_MIN = 5000.00;
    public const SSS_MSC_MAX = 35000.00;
    public const PHILHEALTH_EMPLOYEE_RATE = 0.025;
    public const PHILHEALTH_FLOOR = 10000.00;
    public const PHILHEALTH_CEILING = 100000.00;
    public const PAGIBIG_CEILING = 10000.00;

    public function computeSSS(float $monthlySalary): float
    {
        if ($monthlySalary <= 0) {
            return 0.0;
        }
        $msc = min(max(round($monthlySalary / 500) * 500, self::SSS_MSC_MIN), self::SSS_MSC_MAX);

        return round($msc * self::SSS_EMPLOYEE_RATE, 2);
    }

    public function computePhilHealth(float $monthlySalary): float
    {
        if ($monthlySalary <= 0) {
            return 0.0;
        }
        $base = min(max($monthlySalary, self::PHILHEALTH_FLOOR), self::PHILHEALTH_CEILING);

        return round($base * self::PHILHEALTH_EMPLOYEE_RATE, 2);
    }

    public function computePagIbig(float $monthlySalary): float
    {
        if ($monthlySalary <= 0) {
            return 0.0;
        }
        $rate = $monthlySalary <= 1500 ? 0.01 : 0.02;

        return round(min($monthlySalary, self::PAGIBIG_CEILING) * $rate, 2);
    }

    /** The monthly amounts, times $share (the part of a month this payroll covers, 0–1). */
    public function computeAll(float $monthlySalary, float $share = 1.0): array
    {
        $share = max(0, min(1, $share));
        $sss = round($this->computeSSS($monthlySalary) * $share, 2);
        $philhealth = round($this->computePhilHealth($monthlySalary) * $share, 2);
        $pagibig = round($this->computePagIbig($monthlySalary) * $share, 2);

        return [
            'sss' => $sss,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'total' => round($sss + $philhealth + $pagibig, 2),
        ];
    }

    // Contributions are always based on monthly salary regardless of pay period
    public function toMonthlySalary(float $basicSalary, string $salaryType): float
    {
        return match ($salaryType) {
            'daily'   => $basicSalary * 26,  // standard 26 working days/month
            'weekly'  => $basicSalary * 4.33,
            'monthly' => $basicSalary,
            default   => $basicSalary,
        };
    }
}
