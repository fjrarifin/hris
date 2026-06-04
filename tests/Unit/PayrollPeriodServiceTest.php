<?php

namespace Tests\Unit;

use App\Services\PayrollPeriodService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollPeriodServiceTest extends TestCase
{
    public function test_completed_periods_use_25_to_24_cycle_and_exclude_current_unfinished_period(): void
    {
        $service = new PayrollPeriodService();

        $periods = $service->completedPeriods(2, '2026-06-04');

        $this->assertSame('2026-04-25', $periods[0]['start_date']);
        $this->assertSame('2026-05-24', $periods[0]['end_date']);
        $this->assertTrue($periods[0]['can_generate']);
        $this->assertSame('2026-03-25', $periods[1]['start_date']);
        $this->assertSame('2026-04-24', $periods[1]['end_date']);
    }

    public function test_it_rejects_unfinished_or_non_payroll_cycle_periods(): void
    {
        Carbon::setTestNow('2026-06-04');
        $service = new PayrollPeriodService();

        $service->assertCompletedPayrollPeriod([
            'start_date' => '2026-04-25',
            'end_date' => '2026-05-24',
        ]);
        $this->assertTrue(true);

        try {
            $service->assertCompletedPayrollPeriod([
                'start_date' => '2026-05-25',
                'end_date' => '2026-06-24',
            ]);
            $this->fail('Periode berjalan seharusnya belum boleh digenerate.');
        } catch (ValidationException $exception) {
            $this->assertSame('Payroll hanya dapat digenerate untuk periode yang sudah terlewati.', $exception->errors()['period'][0]);
        }

        try {
            $service->assertCompletedPayrollPeriod([
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ]);
            $this->fail('Periode non 25-24 seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame('Periode payroll harus mengikuti siklus 25 sampai 24.', $exception->errors()['period'][0]);
        } finally {
            Carbon::setTestNow();
        }
    }
}
