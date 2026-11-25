<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class PublicHolidayValidity
{
    /** Last usable day: three calendar months, inclusive of the earning day. */
    public static function expiresAt(CarbonInterface|string $holidayDate): Carbon
    {
        return Carbon::parse($holidayDate)->addMonthsNoOverflow(3)->subDay()->endOfDay();
    }

    /** Earliest earning date still usable today, including month-end boundaries. */
    public static function earliestValidDate(): Carbon
    {
        $today = Carbon::today();
        $date = $today->copy()->subMonthsNoOverflow(3)->subDays(7);
        while (self::expiresAt($date)->lt($today)) {
            $date->addDay();
        }

        return $date;
    }

    public static function isValidOn(CarbonInterface|string $holidayDate, CarbonInterface|string $date): bool
    {
        return Carbon::parse($date)->startOfDay()->lte(self::expiresAt($holidayDate));
    }
}
