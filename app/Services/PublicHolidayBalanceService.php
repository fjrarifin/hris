<?php

namespace App\Services;

use App\Models\EmployeePhBalance;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicHolidayBalanceService
{
    /**
     * Tanggal mulai berlakunya aturan "karyawan harus hadir kerja di tanggal PH untuk dapat jatah PH".
     * Sebelum tanggal ini, semua karyawan yang join_date-nya sebelum hari libur otomatis berhak.
     */
    private const ATTENDANCE_REQUIRED_FROM = '2026-05-27';

    /**
     * Sync saldo PH otomatis untuk semua karyawan aktif berdasarkan periode absensi.
     *
     * Dipanggil setiap kali payroll/absensi periode tertentu di-generate.
     * Untuk setiap hari libur nasional yang jatuh dalam periode tersebut:
     *   - Cek apakah karyawan hadir (ada scan di fingerspot_attendance_logs)
     *   - Jika ya (atau hari libur tidak memerlukan presensi) → upsert ke employee_ph_balances
     *
     * @param string $periodStart Format: 'Y-m-d'
     * @param string $periodEnd   Format: 'Y-m-d'
     */
    public function syncForAttendancePeriod(string $periodStart, string $periodEnd): int
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end   = Carbon::parse($periodEnd)->endOfDay();

        // Ambil semua public holiday yang jatuh dalam periode ini
        $holidays = PublicHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        if ($holidays->isEmpty()) {
            return 0;
        }

        // Ambil semua karyawan aktif beserta PIN (untuk cek fingerspot)
        $employees = Karyawan::query()
            ->whereRaw("UPPER(TRIM(COALESCE(status_karyawan, ''))) = ?", ['AKTIF'])
            ->with('user')
            ->get(['nik', 'pin', 'join_date', 'status_karyawan']);

        if ($employees->isEmpty()) {
            return 0;
        }

        $pins = $employees->pluck('pin')->filter()->values()->all();

        // Batch load semua scan log fingerspot dalam periode ini
        $scanLogsByPin = collect();
        if (! empty($pins)) {
            $scanLogsByPin = FingerspotAttendanceLog::query()
                ->whereIn('pin', $pins)
                ->whereBetween('scan_date', [$start, $end])
                ->get(['pin', 'scan_date'])
                ->groupBy('pin')
                ->map(fn ($logs) => $logs
                    ->pluck('scan_date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->unique()
                );
        }

        $granted = 0;

        foreach ($holidays as $holiday) {
            $holidayDate    = Carbon::parse($holiday->holiday_date);
            $holidayDateStr = $holidayDate->toDateString();

            // Apakah hari libur ini memerlukan presensi?
            $requiresAttendance = $holidayDate->gte(Carbon::parse(self::ATTENDANCE_REQUIRED_FROM));

            foreach ($employees as $employee) {
                // Skip jika karyawan belum join sebelum hari libur
                if ($employee->join_date) {
                    $joinDate = Carbon::parse($employee->join_date)->startOfDay();
                    if ($holidayDate->lt($joinDate)) {
                        continue;
                    }
                }

                // Cek apakah karyawan berhak
                $isEligible = false;
                if (! $requiresAttendance) {
                    // Hari libur lama → semua karyawan aktif yang sudah join berhak
                    $isEligible = true;
                } else {
                    // Hari libur baru → cek scan presensi
                    $employeeScans = $employee->pin
                        ? $scanLogsByPin->get($employee->pin, collect())
                        : collect();
                    $isEligible = $employeeScans->contains($holidayDateStr);
                }

                if (! $isEligible) {
                    continue;
                }

                // Upsert ke employee_ph_balances
                // Jika sudah ada (dari sync sebelumnya), tidak perlu update ulang (idempotent)
                $alreadyExists = EmployeePhBalance::query()
                    ->where('karyawan_nik', $employee->nik)
                    ->where('public_holiday_id', $holiday->id)
                    ->exists();

                if (! $alreadyExists) {
                    EmployeePhBalance::create([
                        'karyawan_nik'      => $employee->nik,
                        'user_id'           => $employee->user?->id,
                        'public_holiday_id' => $holiday->id,
                        'holiday_date'      => $holidayDateStr,
                        'holiday_name'      => $holiday->name,
                        'days'              => 1,
                        'source'            => 'auto',
                        'notes'             => 'Otomatis dari generate payroll periode ' . $periodStart . ' - ' . $periodEnd,
                        'created_by'        => null,
                    ]);
                    $granted++;
                }
            }
        }

        Log::info("[PublicHolidayBalanceService] Sync selesai. Periode: {$periodStart} - {$periodEnd}. Jatah PH baru: {$granted}.");

        return $granted;
    }

    /**
     * Grant jatah PH secara manual oleh HR untuk satu karyawan pada satu hari libur tertentu.
     *
     * @param  string      $nik
     * @param  int         $publicHolidayId
     * @param  string      $notes
     * @param  int|null    $createdBy     user_id HR yang melakukan grant
     * @return EmployeePhBalance|null     null jika sudah ada (tidak duplikat)
     */
    public function grantManually(string $nik, int $publicHolidayId, string $notes, ?int $createdBy): ?EmployeePhBalance
    {
        $holiday  = PublicHoliday::findOrFail($publicHolidayId);
        $employee = Karyawan::with('user')->where('nik', $nik)->firstOrFail();

        // Cek apakah sudah ada
        $existing = EmployeePhBalance::query()
            ->where('karyawan_nik', $nik)
            ->where('public_holiday_id', $publicHolidayId)
            ->first();

        if ($existing) {
            return null; // sudah ada, tidak duplikat
        }

        return EmployeePhBalance::create([
            'karyawan_nik'      => $nik,
            'user_id'           => $employee->user?->id,
            'public_holiday_id' => $publicHolidayId,
            'holiday_date'      => Carbon::parse($holiday->holiday_date)->toDateString(),
            'holiday_name'      => $holiday->name,
            'days'              => 1,
            'source'            => 'manual',
            'notes'             => $notes,
            'created_by'        => $createdBy,
        ]);
    }

    /**
     * Hitung total saldo PH karyawan berdasarkan:
     * 1. SUM days dari employee_ph_balances
     * 2. SUM days dari employee_ph_adjustments (koreksi manual HR lama)
     * 3. Dikurangi PH yang sudah diklaim (public_holiday_requests)
     *
     * @param  string  $nik
     * @param  int     $userId
     * @return array   ['granted' => int, 'adjusted' => int, 'used' => int, 'remaining' => int]
     */
    public function getBalance(string $nik, int $userId): array
    {
        $balanceDays = (int) EmployeePhBalance::where('karyawan_nik', $nik)->sum('days');

        $adjustmentDays = (int) DB::table('employee_ph_adjustments')
            ->where('karyawan_nik', $nik)
            ->sum('days');

        $usedCount = (int) DB::table('public_holiday_requests')
            ->where('user_id', $userId)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        $totalGranted = $balanceDays + $adjustmentDays;
        $remaining    = max($totalGranted - $usedCount, 0);

        return [
            'granted'   => max($totalGranted, 0),
            'adjusted'  => $adjustmentDays,
            'used'      => $usedCount,
            'remaining' => $remaining,
        ];
    }
}
