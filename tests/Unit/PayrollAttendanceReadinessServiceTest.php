<?php

namespace Tests\Unit;

use App\Models\AttendanceScheduleCategory;
use App\Models\EmployeeDailySchedule;
use App\Services\HrAttendanceReportService;
use App\Services\PayrollAttendanceReadinessService;
use App\Services\PayrollPeriodService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PayrollAttendanceReadinessServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_daily_schedules');
        Schema::dropIfExists('attendance_schedule_categories');

        Schema::create('attendance_schedule_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('type');
            $table->boolean('is_workday');
            $table->timestamps();
        });

        Schema::create('employee_daily_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('schedule_date');
            $table->unsignedBigInteger('schedule_category_id')->nullable();
            $table->string('schedule_code');
            $table->timestamps();
        });
    }

    public function test_it_blocks_submit_when_schedule_or_attendance_still_needs_review(): void
    {
        $work = $this->category('P1', 'work', true);
        $off = $this->category('O', 'off', false);

        $this->schedule('EMP001', '2026-05-25', $work);
        $this->schedule('EMP001', '2026-05-26', $work);
        $this->schedule('EMP001', '2026-05-27', $work);
        $this->schedule('EMP001', '2026-05-28', $work);
        $this->schedule('EMP001', '2026-05-29', $off);
        $this->schedule('EMP001', '2026-05-30', $work);

        $result = $this->serviceWithDays([
            '2026-05-25' => $this->day('M', scanIn: '08:00:00'),
            '2026-05-26' => $this->day('A'),
            '2026-05-27' => $this->day('S', hasDocument: false),
            '2026-05-28' => $this->day('M', scanIn: '08:00:00', scanOut: '17:00:00', conflict: true),
            '2026-05-29' => $this->day('A'),
            '2026-05-30' => $this->day('M', scanIn: '08:00:00', scanOut: '17:00:00', overtimeMinutes: 120),
            '2026-05-31' => $this->day('A'),
        ])->audit($this->filters());

        $this->assertFalse($result['can_submit']);
        $this->assertSame(1, $result['summary']['missing_schedule_days']);
        $this->assertSame(1, $result['summary']['unresolved_workdays']);
        $this->assertSame(1, $result['summary']['incomplete_scan_days']);
        $this->assertSame(1, $result['summary']['approved_absence_conflicts']);
        $this->assertSame(1, $result['summary']['sick_without_document_days']);
        $this->assertSame(120, $result['summary']['overtime_minutes']);
        $this->assertSame(4, $result['summary']['blocker_count']);
    }

    public function test_it_allows_submit_when_every_scheduled_workday_is_resolved(): void
    {
        $work = $this->category('P1', 'work', true);
        $off = $this->category('O', 'off', false);

        $this->schedule('EMP001', '2026-05-25', $work);
        $this->schedule('EMP001', '2026-05-26', $work);
        $this->schedule('EMP001', '2026-05-27', $work);
        $this->schedule('EMP001', '2026-05-28', $work);
        $this->schedule('EMP001', '2026-05-29', $off);

        $result = $this->serviceWithDays([
            '2026-05-25' => $this->day('M', '08:00:00', '17:00:00'),
            '2026-05-26' => $this->day('C'),
            '2026-05-27' => $this->day('PH'),
            '2026-05-28' => $this->day('S', hasDocument: true),
            '2026-05-29' => $this->day('A'),
        ])->audit($this->filters('2026-05-29'));

        $this->assertTrue($result['can_submit']);
        $this->assertSame(4, $result['summary']['scheduled_workdays']);
        $this->assertSame(1, $result['summary']['sick_with_document_days']);
        $this->assertSame(0, $result['summary']['blocker_count']);
    }

    public function test_it_counts_attendance_even_when_schedule_is_missing(): void
    {
        $result = $this->serviceWithDays([
            '2026-05-25' => $this->day('M', '08:00:00', '17:00:00'),
            '2026-05-26' => $this->day('M', '08:00:00', '17:00:00'),
            '2026-05-27' => $this->day('A'),
        ])->audit($this->filters('2026-05-27'));

        $this->assertFalse($result['can_submit']);
        $this->assertSame(2, $result['records'][0]['present_days']);
        $this->assertSame(2, $result['records'][0]['total_hari_masuk']);
        $this->assertSame(2, $result['summary']['total_hari_masuk']);
        $this->assertSame(3, $result['summary']['missing_schedule_days']);
    }

    public function test_it_counts_extra_attendance_on_scheduled_off_days(): void
    {
        $work = $this->category('P1', 'work', true);
        $off = $this->category('O', 'off', false);

        $this->schedule('EMP001', '2026-05-25', $work);
        $this->schedule('EMP001', '2026-05-26', $off);

        $result = $this->serviceWithDays([
            '2026-05-25' => $this->day('M', '08:00:00', '17:00:00'),
            '2026-05-26' => $this->day('M', '08:00:00', '17:00:00'),
        ], workdays: 1)->audit($this->filters('2026-05-26'));

        $this->assertSame(2, $result['records'][0]['total_hari_masuk']);
        $this->assertSame(1, $result['records'][0]['extra_off_days']);
    }

    private function serviceWithDays(array $days, int $workdays = 4): PayrollAttendanceReadinessService
    {
        $dates = collect(array_keys($days));
        $report = [
            'filters' => [
                'start_date' => $dates->first(),
                'end_date' => $dates->last(),
                'departments' => [],
                'employee_niks' => ['EMP001'],
                'employee_status' => null,
            ],
            'dates' => $dates,
            'records' => collect([[
                'nik' => 'EMP001',
                'name' => 'Ayu',
                'department' => 'Operations',
                'days' => collect($days),
            ]]),
        ];
        $reportService = Mockery::mock(HrAttendanceReportService::class);
        $reportService->shouldReceive('report')->once()->andReturn($report);

        $periodService = Mockery::mock(PayrollPeriodService::class);
        $periodService->shouldReceive('workdaySummary')->once()->andReturn([
            'period_days' => $dates->count(),
            'sundays' => 0,
            'public_holidays' => 0,
            'workdays' => $workdays,
        ]);

        return new PayrollAttendanceReadinessService($reportService, $periodService);
    }

    private function filters(string $end = '2026-05-31'): array
    {
        return ['start_date' => '2026-05-25', 'end_date' => $end, 'employee_niks' => ['EMP001']];
    }

    private function day(
        string $status,
        ?string $scanIn = null,
        ?string $scanOut = null,
        bool $conflict = false,
        ?bool $hasDocument = null,
        int $overtimeMinutes = 0
    ): array {
        return [
            'status' => $status,
            'scan_in' => $scanIn,
            'scan_out' => $scanOut,
            'has_approved_absence_conflict' => $conflict,
            'has_document' => $hasDocument,
            'overtime_minutes' => $overtimeMinutes,
        ];
    }

    private function category(string $code, string $type, bool $isWorkday): AttendanceScheduleCategory
    {
        return AttendanceScheduleCategory::query()->create(compact('code', 'type') + [
            'is_workday' => $isWorkday,
        ]);
    }

    private function schedule(string $nik, string $date, AttendanceScheduleCategory $category): void
    {
        EmployeeDailySchedule::query()->create([
            'karyawan_nik' => $nik,
            'schedule_date' => $date,
            'schedule_category_id' => $category->id,
            'schedule_code' => $category->code,
        ]);
    }
}
