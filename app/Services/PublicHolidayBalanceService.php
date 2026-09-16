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
     * Sinkronisasi jatah PH yang memenuhi syarat (eligible) untuk satu karyawan
     * jika record di employee_ph_balances belum ada.
     *
     * @param  Karyawan  $employee
     * @param  User|null $user
     * @return int Jumlah record baru yang ditambahkan
     */
    public function syncMissingEligibleForEmployee(Karyawan $employee, ?\App\Models\User $user = null): int
    {
        $joinDate = $employee->join_date ? Carbon::parse($employee->join_date)->startOfDay() : null;

        // Ambil ID hari libur yang secara spesifik dikurangi oleh adjustment manual HR
        $deductedHolidayIds = DB::table('employee_ph_adjustments')
            ->where('karyawan_nik', $employee->nik)
            ->whereNotNull('public_holiday_id')
            ->where('days', '<', 0)
            ->pluck('public_holiday_id');

        // Ambil ID hari libur yang secara spesifik ditambahkan oleh adjustment manual HR
        $addedHolidayIds = DB::table('employee_ph_adjustments')
            ->where('karyawan_nik', $employee->nik)
            ->whereNotNull('public_holiday_id')
            ->where('days', '>', 0)
            ->pluck('public_holiday_id');

        // Hari libur aktif dalam jendela 90 hari terakhir
        $activeHolidays = PublicHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '<', now())
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->when($joinDate, fn ($q) => $q->whereDate('holiday_date', '>=', $joinDate))
            ->whereNotIn('id', $deductedHolidayIds)
            ->orderByDesc('holiday_date')
            ->get();

        if ($activeHolidays->isEmpty()) {
            return 0;
        }

        // Ambil tanggal scan presensi fingerspot jika punya PIN
        $attendedDates = collect();
        if ($employee->pin) {
            $attendedDates = FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereBetween('scan_date', [now()->subDays(90)->startOfDay(), now()->startOfDay()])
                ->get(['scan_date'])
                ->pluck('scan_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->unique();
        }

        $userId = $user?->id ?? $employee->user?->id;
        $granted = 0;

        foreach ($activeHolidays as $holiday) {
            $holidayDate = Carbon::parse($holiday->holiday_date);
            $holidayDateStr = $holidayDate->toDateString();
            $requiresAttendance = $holidayDate->gte(Carbon::parse(self::ATTENDANCE_REQUIRED_FROM));

            $isEligible = $addedHolidayIds->contains($holiday->id)
                || ! $requiresAttendance
                || $attendedDates->contains($holidayDateStr);

            if (! $isEligible) {
                continue;
            }

            // Cek apakah sudah ada record di employee_ph_balances
            $exists = EmployeePhBalance::query()
                ->where('karyawan_nik', $employee->nik)
                ->where('public_holiday_id', $holiday->id)
                ->exists();

            if (! $exists) {
                EmployeePhBalance::create([
                    'karyawan_nik'      => $employee->nik,
                    'user_id'           => $userId,
                    'public_holiday_id' => $holiday->id,
                    'holiday_date'      => $holidayDateStr,
                    'holiday_name'      => $holiday->name,
                    'days'              => 1,
                    'source'            => 'auto',
                    'notes'             => 'Otomatis tersinkronisasi dari kehadiran PH',
                    'created_by'        => null,
                ]);
                $granted++;
            }
        }

        return $granted;
    }

    /**
     * Rebuild / Sinkronisasi massal seluruh saldo PH dari absensi:
     * 1. Kosongkan tabel employee_ph_balances jika $fresh = true
     * 2. Ambil seluruh public holiday aktif yang jatuh dalam rentang 90 hari terakhir (dan <= hari ini)
     * 3. Ambil absensi fingerspot seluruh karyawan aktif pada tanggal merah tersebut
     * 4. Masukkan karyawan yang hadir (atau berhak) ke tabel employee_ph_balances
     * 5. Abaikan tanggal merah yang sudah lewat dari 90 hari
     *
     * @param bool $fresh
     * @return array
     */
    public function rebuildBalancesFromAttendance(bool $fresh = true): array
    {
        if ($fresh) {
            DB::table('employee_ph_balances')->truncate();
        }

        $now = Carbon::now();
        $cutoff90 = $now->copy()->subDays(90)->startOfDay();

        // 1. Ambil hari libur aktif dalam rentang 90 hari terakhir
        $activeHolidays = PublicHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '<=', $now->toDateString())
            ->whereDate('holiday_date', '>=', $cutoff90->toDateString())
            ->orderBy('holiday_date')
            ->get();

        if ($activeHolidays->isEmpty()) {
            return [
                'holidays_count'   => 0,
                'records_inserted' => 0,
                'breakdown'        => [],
            ];
        }

        // 2. Ambil karyawan aktif
        $employees = Karyawan::query()
            ->whereRaw("UPPER(TRIM(COALESCE(status_karyawan, ''))) = ?", ['AKTIF'])
            ->with('user')
            ->get();

        // 3. Batch load scan logs fingerspot dalam rentang 90 hari
        $pins = $employees->pluck('pin')->filter()->unique()->values()->all();
        $scanDatesByPin = collect();
        if (! empty($pins)) {
            $scanDatesByPin = FingerspotAttendanceLog::query()
                ->whereIn('pin', $pins)
                ->whereBetween('scan_date', [$cutoff90, $now->copy()->endOfDay()])
                ->get(['pin', 'scan_date'])
                ->groupBy('pin')
                ->map(fn ($logs) => $logs
                    ->pluck('scan_date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->unique()
                );
        }

        // 4. Batch load adjustments per karyawan
        $adjustments = DB::table('employee_ph_adjustments')
            ->whereNotNull('public_holiday_id')
            ->get();
        $addedMap = $adjustments->where('days', '>', 0)->groupBy('karyawan_nik');
        $deductedMap = $adjustments->where('days', '<', 0)->groupBy('karyawan_nik');

        $totalInserted = 0;
        $breakdown = [];

        foreach ($activeHolidays as $holiday) {
            $hDate = Carbon::parse($holiday->holiday_date);
            $hDateStr = $hDate->toDateString();
            $requiresAttendance = $hDate->gte(Carbon::parse(self::ATTENDANCE_REQUIRED_FROM));
            $insertedThisHoliday = 0;

            $recordsToInsert = [];

            foreach ($employees as $employee) {
                // Skip jika join_date setelah hari libur
                if ($employee->join_date) {
                    $joinDate = Carbon::parse($employee->join_date)->startOfDay();
                    if ($hDate->lt($joinDate)) {
                        continue;
                    }
                }

                // Cek penyesuaian manual HR (pengurangan spesifik)
                $empDeductions = $deductedMap->get($employee->nik, collect())->pluck('public_holiday_id')->all();
                if (in_array($holiday->id, $empDeductions)) {
                    continue;
                }

                // Cek penyesuaian manual HR (penambahan spesifik)
                $empAdditions = $addedMap->get($employee->nik, collect())->pluck('public_holiday_id')->all();
                $isAddedByHR = in_array($holiday->id, $empAdditions);

                // Cek presensi scan fingerspot
                $scans = $employee->pin ? $scanDatesByPin->get($employee->pin, collect()) : collect();
                $isAttended = $scans->contains($hDateStr);

                $isEligible = $isAddedByHR || ! $requiresAttendance || $isAttended;

                if ($isEligible) {
                    $recordsToInsert[] = [
                        'karyawan_nik'      => $employee->nik,
                        'user_id'           => $employee->user?->id,
                        'public_holiday_id' => $holiday->id,
                        'holiday_date'      => $hDateStr,
                        'holiday_name'      => $holiday->name,
                        'days'              => 1,
                        'source'            => $isAddedByHR ? 'adjustment' : 'attendance',
                        'notes'             => 'Otomatis dari absensi PH dalam batas 90 hari',
                        'created_by'        => null,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                    $insertedThisHoliday++;
                }
            }

            if (! empty($recordsToInsert)) {
                DB::table('employee_ph_balances')->insert($recordsToInsert);
                $totalInserted += count($recordsToInsert);
            }

            $breakdown[] = [
                'id'       => $holiday->id,
                'name'     => $holiday->name,
                'date'     => $hDateStr,
                'days_ago' => abs((int) $now->diffInDays($hDate)),
                'inserted' => $insertedThisHoliday,
            ];
        }

        Log::info("[PublicHolidayBalanceService] Rebuild balances selesai. Fresh: " . ($fresh ? 'true' : 'false') . ", Total Inserted: {$totalInserted}.");

        return [
            'holidays_count'   => $activeHolidays->count(),
            'records_inserted' => $totalInserted,
            'breakdown'        => $breakdown,
        ];
    }

    /**
     * Sinkronisasi massal seluruh karyawan aktif untuk PH eligible dalam 90 hari terakhir.
     *
     * @return int Total record baru yang ditambahkan
     */
    public function syncAllActiveEligibleHolidays(): int
    {
        $employees = Karyawan::query()
            ->whereRaw("UPPER(TRIM(COALESCE(status_karyawan, ''))) = ?", ['AKTIF'])
            ->with('user')
            ->get();

        $totalGranted = 0;
        foreach ($employees as $employee) {
            $totalGranted += $this->syncMissingEligibleForEmployee($employee, $employee->user);
        }

        Log::info("[PublicHolidayBalanceService] Mass sync selesai. Total saldo PH baru ditambahkan: {$totalGranted}.");
        return $totalGranted;
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
        $employee = \App\Models\Karyawan::where('nik', $nik)->first();
        if ($employee) {
            $this->syncMissingEligibleForEmployee($employee);
        }

        $activeHolidayIds = EmployeePhBalance::query()
            ->where('karyawan_nik', $nik)
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->whereDate('holiday_date', '<', now())
            ->pluck('public_holiday_id')
            ->filter()
            ->unique();

        $generalAdjustmentDays = (int) DB::table('employee_ph_adjustments')
            ->where('karyawan_nik', $nik)
            ->whereNull('public_holiday_id')
            ->sum('days');

        $usedCount = (int) DB::table('public_holiday_requests')
            ->where('user_id', $userId)
            ->whereIn('public_holiday_id', $activeHolidayIds)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        $totalGranted = $activeHolidayIds->count() + $generalAdjustmentDays;
        $remaining    = max($totalGranted - $usedCount, 0);

        return [
            'granted'   => max($totalGranted, 0),
            'adjusted'  => $generalAdjustmentDays,
            'used'      => $usedCount,
            'remaining' => $remaining,
        ];
    }
}
