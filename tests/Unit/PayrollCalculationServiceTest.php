<?php

namespace Tests\Unit;

use App\Models\EmployeePayrollProfile;
use App\Services\PayrollAttendanceReadinessService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollPeriodService;
use App\Services\PayrollValidationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PayrollCalculationServiceTest extends TestCase
{
    public function test_it_calculates_hris_draft_with_bpjs_overtime_and_absence_deductions(): void
    {
        $service = new PayrollCalculationService(
            Mockery::mock(PayrollAttendanceReadinessService::class),
            Mockery::mock(PayrollValidationService::class),
            Mockery::mock(PayrollPeriodService::class)
        );
        $profile = new EmployeePayrollProfile([
            'gaji_pokok' => 4106250,
            'tunjangan_jabatan' => 1368750,
            'tunjangan_tidak_tetap' => 1028935,
            'bruto_man_power' => 7300000,
            'payroll_group' => 'staff',
            'dasar_bpjs' => 5475000,
            'dasar_jp' => 5475000,
            'rate_jkk_percent' => 0.54,
        ]);

        $result = $service->calculate($profile, true, [
            'scheduled_workdays' => 23,
            'periode_hari_kerja' => 23,
            'total_hari_masuk' => 22,
            'overtime_minutes' => 120,
            'unresolved_workdays' => 1,
            'permission_days' => 1,
            'sick_without_document_days' => 1,
        ]);

        $this->assertSame(44736, $result['daily_rate']);
        $this->assertSame(6530406, $result['gross_salary']);
        $this->assertSame(308472, $result['total_deduction']);
        $this->assertSame(6440934, $result['take_home_pay']);
        $this->assertSame(577065, $result['employer_contribution']);
        $this->assertSame(7017999, $result['company_cost']);
    }

    public function test_it_caps_paid_attendance_to_period_workdays_and_tracks_extra_off(): void
    {
        $service = new PayrollCalculationService(
            Mockery::mock(PayrollAttendanceReadinessService::class),
            Mockery::mock(PayrollValidationService::class),
            Mockery::mock(PayrollPeriodService::class)
        );
        $profile = new EmployeePayrollProfile([
            'gaji_pokok' => 3000000,
            'tunjangan_jabatan' => 500000,
            'bruto_man_power' => 5000000,
            'payroll_group' => 'operator',
            'rate_jkk_percent' => 0,
        ]);

        $result = $service->calculate($profile, false, [
            'periode_hari_kerja' => 20,
            'total_hari_masuk' => 23,
            'overtime_minutes' => 0,
            'permission_days' => 0,
            'sick_without_document_days' => 0,
        ]);

        $this->assertSame(20, $result['paid_hari_masuk']);
        $this->assertSame(3, $result['extra_off_days']);
        $this->assertSame(1500000, $result['tunjangan_tidak_tetap_full']);
        $this->assertSame(5000000, $result['gross_salary']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
