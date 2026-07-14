<?php

namespace App\Console\Commands;

use App\Http\Services\WhatsAppService;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use App\Notifications\ApprovedAbsenceAutoCancelledNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCancelApprovedAbsenceWithAttendance extends Command
{
    protected $signature = 'absence:auto-cancel-attended {--date= : Tanggal absensi yang dicek, default kemarin (Y-m-d)}';

    protected $description = 'Batalkan cuti/PH yang sudah disetujui jika karyawan tercatat masuk pada tanggal pengajuan.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $cancelled = 0;
        $cancelled += $this->cancelLeaves($date);
        $cancelled += $this->cancelPublicHolidayRequests($date);

        $this->info("Pemeriksaan {$date->toDateString()} selesai. {$cancelled} pengajuan dibatalkan.");

        return self::SUCCESS;
    }

    private function cancelLeaves(Carbon $date): int
    {
        $requests = LeaveRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('manager_approved_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        $cancelled = 0;
        foreach ($requests as $leave) {
            $employee = $leave->user?->karyawan;
            if (! $this->hasAttendance($employee, $date)) {
                continue;
            }

            DB::transaction(function () use ($leave, $employee, $date): void {
                $leave->update([
                    'status' => 'cancelled',
                    'reject_reason' => 'Otomatis dibatalkan karena karyawan tercatat masuk kerja pada tanggal cuti.',
                ]);

                $this->notifySupervisor($leave, 'CUTI', $employee, $date);
            });

            $cancelled++;
        }

        return $cancelled;
    }

    private function cancelPublicHolidayRequests(Carbon $date): int
    {
        $requests = PublicHolidayRequest::query()
            ->with(['user.karyawan', 'holiday'])
            ->where('status', 'approved')
            ->whereNotNull('manager_approved_at')
            ->whereDate('claim_date', $date)
            ->get();

        $cancelled = 0;
        foreach ($requests as $request) {
            $employee = $request->user?->karyawan;
            if (! $this->hasAttendance($employee, $date)) {
                continue;
            }

            DB::transaction(function () use ($request, $employee, $date): void {
                $request->update([
                    'status' => 'cancelled',
                    'reject_reason' => 'Otomatis dibatalkan karena karyawan tercatat masuk kerja pada tanggal pengambilan PH.',
                ]);

                $this->notifySupervisor($request, 'PH', $employee, $date);
            });

            $cancelled++;
        }

        return $cancelled;
    }

    private function hasAttendance(?Karyawan $employee, Carbon $date): bool
    {
        return filled($employee?->pin)
            && FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereDate('scan_date', $date)
                ->exists();
    }

    private function notifySupervisor(object $request, string $type, ?Karyawan $employee, Carbon $date): void
    {
        if (! $employee || blank($employee->atasan_langsung_nik)) {
            return;
        }

        $supervisorEmployee = Karyawan::query()
            ->where('nik', $employee->atasan_langsung_nik)
            ->first();
        $supervisorUser = $supervisorEmployee
            ? User::query()->where('username', $supervisorEmployee->nik)->first()
            : null;

        $label = $type === 'PH' ? 'PH' : 'cuti';
        $formattedDate = $date->format('d/m/Y');
        $message = "Pengajuan {$label} {$employee->nama_karyawan} tanggal {$formattedDate} otomatis dibatalkan karena karyawan tercatat masuk kerja pada tanggal tersebut. Jatah pengajuan dikembalikan mengikuti status pembatalan ini.";

        $supervisorUser?->notify(new ApprovedAbsenceAutoCancelledNotification(
            $request,
            $type,
            $employee->nama_karyawan,
            $formattedDate
        ));

        if (blank($supervisorEmployee?->no_hp) || ! config('services.whatsapp.url') || ! config('services.whatsapp.device_id')) {
            return;
        }

        try {
            app(WhatsAppService::class)->sendMessage(
                $this->normalizePhone($supervisorEmployee->no_hp),
                "*PENGAJUAN {$type} OTOMATIS DIBATALKAN*\n\n{$message}\n\nPesan ini dikirim otomatis oleh HRIS."
            );
        } catch (\Throwable $exception) {
            Log::warning('Gagal mengirim notifikasi pembatalan pengajuan ke atasan.', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'supervisor_nik' => $supervisorEmployee->nik,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        return str_starts_with($phone, '62') ? $phone : '62'.$phone;
    }
}
