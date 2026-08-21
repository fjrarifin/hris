<?php

namespace Tests\Feature;

use App\Http\Controllers\LeaveAccrualService;
use App\Models\Karyawan;
use App\Models\LeaveAccrual;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveAccrualRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_under_one_year_gets_no_leave_accrual(): void
    {
        // Join date 6 months ago
        Carbon::setTestNow('2026-08-21');

        $user = User::factory()->create();
        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nik' => 'EMP991',
            'nama_karyawan' => 'Employee New',
            'join_date' => '2026-02-15',
            'departement' => 'IT',
            'divisi' => 'Software',
            'jabatan' => 'Developer',
        ]);

        DB::table('t_kontrak_karyawan')->insert([
            'nik' => 'EMP991',
            'start_date' => '2026-02-15',
            'end_date' => '2027-02-14',
            'status_kontrak' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new LeaveAccrualService();
        $service->generateMonthly($user);

        $this->assertEquals(0, LeaveAccrual::where('user_id', $user->id)->count());
        $this->assertEquals(0, $service->getBalance($user));
    }

    public function test_employee_over_one_year_gets_monthly_accruals_matching_active_contract_expiry(): void
    {
        // Joined 2025-01-15. 1 year anniversary is 2026-01-15.
        // Today is 2026-08-21.
        // Months accrued: Feb (2026-02-15), Mar, Apr, May, Jun, Jul, Aug (2026-08-15) = 7 months.
        Carbon::setTestNow('2026-08-21');

        $user = User::factory()->create();
        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nik' => 'EMP992',
            'nama_karyawan' => 'Employee Senior',
            'join_date' => '2025-01-15',
            'departement' => 'IT',
            'divisi' => 'Software',
            'jabatan' => 'Developer',
        ]);

        // Active contract 2nd period ends 2027-06-30
        DB::table('t_kontrak_karyawan')->insert([
            'nik' => 'EMP992',
            'start_date' => '2026-01-15',
            'end_date' => '2027-06-30',
            'status_kontrak' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new LeaveAccrualService();
        $service->generateMonthly($user);

        $accruals = LeaveAccrual::where('user_id', $user->id)->orderBy('accrued_at')->get();
        $this->assertCount(7, $accruals);

        // Check months: 2, 3, 4, 5, 6, 7, 8
        $this->assertEquals([2, 3, 4, 5, 6, 7, 8], $accruals->pluck('month')->all());

        // Check that expired_at for all accruals equals active contract end_date (2027-06-30)
        foreach ($accruals as $acc) {
            $this->assertEquals('2027-06-30', Carbon::parse($acc->expired_at)->toDateString());
        }

        $this->assertEquals(7, $service->getBalance($user));
    }
}
