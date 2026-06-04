<?php

namespace App\Services;

use App\Models\PublicHoliday;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

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

    public function completedPeriods(int $months = 12, ?string $asOfDate = null): array
    {
        $today = CarbonImmutable::parse($asOfDate ?? now()->toDateString())->startOfDay();
        $period = $this->periodFor($today->toDateString());
        $start = CarbonImmutable::parse($period['start_date'])->startOfDay();

        if (CarbonImmutable::parse($period['end_date'])->greaterThanOrEqualTo($today)) {
            $start = $start->subMonthNoOverflow();
        }

        $periods = [];
        for ($index = 0; $index < $months; $index++) {
            $end = $start->addMonthNoOverflow()->setDay(24);
            $periods[] = [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'label' => $end->locale('id')->translatedFormat('F Y').' ('.$start->format('d M Y').' - '.$end->format('d M Y').')',
                'can_generate' => $end->lessThan($today),
            ];
            $start = $start->subMonthNoOverflow();
        }

        return $periods;
    }

    public function assertCompletedPayrollPeriod(array $filters): void
    {
        $start = CarbonImmutable::parse($filters['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'])->startOfDay();
        $expected = $this->periodFor($end->toDateString());

        if ($expected['start_date'] !== $start->toDateString() || $expected['end_date'] !== $end->toDateString()) {
            throw ValidationException::withMessages([
                'period' => 'Periode payroll harus mengikuti siklus 25 sampai 24.',
            ]);
        }

        if ($end->greaterThanOrEqualTo(CarbonImmutable::parse(now()->toDateString())->startOfDay())) {
            throw ValidationException::withMessages([
                'period' => 'Payroll hanya dapat digenerate untuk periode yang sudah terlewati.',
            ]);
        }
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
