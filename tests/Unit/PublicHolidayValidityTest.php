<?php

namespace Tests\Unit;

use App\Support\PublicHolidayValidity;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PublicHolidayValidityTest extends TestCase
{
    public function test_expiry_uses_three_calendar_months_with_inclusive_last_day(): void
    {
        foreach (['2026-10-04' => '2027-01-03', '2026-01-31' => '2026-04-29', '2026-11-30' => '2027-02-27'] as $earned => $expiry) {
            $this->assertSame($expiry, PublicHolidayValidity::expiresAt($earned)->toDateString());
            $this->assertTrue(PublicHolidayValidity::isValidOn($earned, $expiry));
            $this->assertFalse(PublicHolidayValidity::isValidOn($earned, Carbon::parse($expiry)->addDay()));
        }
    }

    public function test_active_window_matches_expiry_at_month_boundaries(): void
    {
        try {
            foreach (['2027-01-03', '2027-01-04', '2027-02-27', '2027-02-28', '2026-04-30'] as $today) {
                Carbon::setTestNow($today);
                $cutoff = PublicHolidayValidity::earliestValidDate();
                $this->assertTrue(PublicHolidayValidity::isValidOn($cutoff, $today));
                $this->assertFalse(PublicHolidayValidity::isValidOn($cutoff->copy()->subDay(), $today));
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
