<?php

namespace App\Console\Commands;

use App\Models\AttendanceCorrection;
use App\Models\EmployeePermission;
use App\Models\ExtraOffRequest;
use App\Models\LeaveAccrual;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupCancelledAttendanceCorrections extends Command
{
    protected $signature = 'attendance-corrections:cleanup-cancelled-absences
        {--nik= : Batasi cleanup untuk NIK/username tertentu}
        {--dry-run : Hanya tampilkan data yang akan dibersihkan tanpa mengubah database}';

    protected $description = 'Membersihkan koreksi absensi yang masih terhubung ke pengajuan cuti/PH/EO/izin-sakit yang sudah dibatalkan atau ditolak.';

    public function handle(): int
    {
        $nik = $this->option('nik');
        $dryRun = (bool) $this->option('dry-run');
        $absenceModels = [
            LeaveRequest::class,
            PublicHolidayRequest::class,
            ExtraOffRequest::class,
            EmployeePermission::class,
        ];

        $query = AttendanceCorrection::query()
            ->whereNotNull('absence_type')
            ->whereNotNull('absence_id')
            ->whereIn('absence_type', $absenceModels)
            ->when($nik, fn ($builder) => $builder->where('nik', $nik))
            ->orderBy('attendance_date');

        $corrections = $query->get()
            ->filter(function (AttendanceCorrection $correction): bool {
                $absence = $correction->absence_type::query()->find($correction->absence_id);

                return ! $absence || in_array($absence->status, ['cancelled', 'rejected'], true);
            })
            ->values();

        if ($corrections->isEmpty()) {
            $this->info('Tidak ada koreksi absensi cancelled/rejected yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $this->table(
            ['Correction ID', 'NIK', 'Tanggal', 'Jenis', 'Absence ID', 'Accrual ID'],
            $corrections->map(fn (AttendanceCorrection $correction): array => [
                $correction->id,
                $correction->nik,
                $correction->attendance_date?->toDateString(),
                class_basename($correction->absence_type),
                $correction->absence_id,
                $correction->leave_accrual_id ?: '-',
            ])->all()
        );

        if ($dryRun) {
            $this->warn("Dry-run aktif. {$corrections->count()} koreksi ditemukan, belum ada perubahan database.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($corrections): void {
            foreach ($corrections as $correction) {
                if ($correction->leave_accrual_id) {
                    LeaveAccrual::query()
                        ->whereKey($correction->leave_accrual_id)
                        ->update(['is_used' => false]);
                }

                $correction->delete();
            }
        });

        $this->info("{$corrections->count()} koreksi absensi berhasil dibersihkan.");

        return self::SUCCESS;
    }
}
