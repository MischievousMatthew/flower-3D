<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A reporting window (7d, 30d, 90d, 12m, ytd, all) with the equal-length
 * period before it and the chart buckets inside it.
 */
final class ReportPeriod
{
    public const RANGES = ['7d', '30d', '90d', '12m', 'ytd', 'all'];

    public function __construct(
        public readonly string $range,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly ?CarbonImmutable $previousStart,
    ) {
    }

    /** @param ?CarbonImmutable $firstActivity used for "all" */
    public static function make(?string $range, ?CarbonImmutable $firstActivity = null): self
    {
        $range = in_array($range, self::RANGES, true) ? $range : '30d';
        $now = CarbonImmutable::now();

        $start = match ($range) {
            '7d' => $now->subDays(6)->startOfDay(),
            '30d' => $now->subDays(29)->startOfDay(),
            '90d' => $now->subDays(89)->startOfDay(),
            '12m' => $now->subMonthsNoOverflow(11)->startOfMonth(),
            'ytd' => $now->startOfYear(),
            'all' => ($firstActivity ?? $now)->startOfMonth(),
        };

        return new self($range, $start, $now, $range === 'all' ? null : $start->sub($start->diff($now)));
    }

    public function unit(): string
    {
        return match ($this->range) {
            '7d', '30d' => 'day',
            '90d' => 'week',
            default => 'month',
        };
    }

    /** @return array<int, array{start: CarbonImmutable, end: CarbonImmutable, label: string}> */
    public function buckets(): array
    {
        $unit = $this->unit();
        $cursor = match ($unit) {
            'day' => $this->start->startOfDay(),
            'week' => $this->start->startOfWeek(),
            'month' => $this->start->startOfMonth(),
        };

        $buckets = [];
        while ($cursor->lte($this->end)) {
            $next = match ($unit) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
            };
            $buckets[] = [
                'start' => $cursor,
                'end' => $next,
                'label' => $unit === 'month' ? $cursor->format('M Y') : $cursor->format('M j'),
            ];
            $cursor = $next;
        }

        return $buckets;
    }
}
