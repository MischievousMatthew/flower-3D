<?php

namespace App\Services;

class GovernmentContributionService
{
    // ─── SSS ─────────────────────────────────────────────────────────────────
    // Employee rate: 4.5% of monthly salary, capped at ₱1,900

    public function computeSSS(float $monthlySalary): float
    {
        $contribution = $monthlySalary * 0.045;
        return round(min($contribution, 1900.00), 2);
    }

    // ─── PhilHealth ──────────────────────────────────────────────────────────
    // Rate: 2.5% of monthly salary, salary ceiling ₱100,000
    // Employee pays half of the total premium (1.25%)

    public function computePhilHealth(float $monthlySalary): float
    {
        $base = min($monthlySalary, 100000.00);
        return round($base * 0.025, 2);
    }

    // ─── Pag-IBIG ─────────────────────────────────────────────────────────────
    // ≤ ₱1,500 → 1%, > ₱1,500 → 2%, salary cap ₱10,000

    public function computePagIbig(float $monthlySalary): float
    {
        $base = min($monthlySalary, 10000.00);
        $rate = $monthlySalary <= 1500 ? 0.01 : 0.02;
        return round($base * $rate, 2);
    }

    // ─── Compute all three ────────────────────────────────────────────────────

    public function computeAll(float $monthlySalary): array
    {
        $sss       = $this->computeSSS($monthlySalary);
        $philhealth = $this->computePhilHealth($monthlySalary);
        $pagibig   = $this->computePagIbig($monthlySalary);

        return [
            'sss'        => $sss,
            'philhealth' => $philhealth,
            'pagibig'    => $pagibig,
            'total'      => round($sss + $philhealth + $pagibig, 2),
        ];
    }

    // ─── Convert period salary to monthly equivalent ───────────────────────
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