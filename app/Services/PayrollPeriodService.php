<?php

namespace App\Services;

use App\Models\PublicHoliday;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class PayrollPeriodService
{
    public function normalizeFilters(array $filters): array
    {
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            return $filters;
        }

        $period = $this->periodFor(now()->toDateString());

        return [
            ...$filters,
            'start_date' => $filters['start_date'] ?? $period['start_date'],
            'end_date' => $filters['end_date'] ?? $period['end_date'],
        ];
    }

    public function periodFor(string $date): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $start = $day->day >= 25
            ? $day->setDay(25)
            : $day->subMonthNoOverflow()->setDay(25);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $start->addMonthNoOverflow()->setDay(24)->toDateString(),
        ];
    }

    public function workdaySummary(string $startDate, string $endDate): array
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = 0;
        $sundays = 0;

        foreach ($period as $date) {
            $totalDays++;
            if ($date->isSunday()) {
                $sundays++;
            }
        }

        $publicHolidays = PublicHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->get()
            ->filter(fn (PublicHoliday $holiday): bool => ! $holiday->holiday_date->isSunday())
            ->count();

        return [
            'period_days' => $totalDays,
            'sundays' => $sundays,
            'public_holidays' => $publicHolidays,
            'workdays' => max($totalDays - $sundays - $publicHolidays, 1),
        ];
    }
}
