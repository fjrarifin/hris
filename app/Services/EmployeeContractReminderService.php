<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use App\Models\Karyawan;
use App\Models\User;
use App\Notifications\EmployeeContractExpiryReminderNotification;
use App\Notifications\SubordinateContractExpiryReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmployeeContractReminderService
{
    private const REMINDER_DAYS = [60, 45, 30];

    public function sendDueReminders(Carbon $today): array
    {
        $today = $today->copy()->startOfDay();
        $contracts = $this->dueContracts($today);
        $hrUsers = User::query()
            ->where('level', 2)
            ->when(Schema::hasColumn('users', 'is_active'), function ($query): void {
                $query->where(fn ($activeQuery) => $activeQuery
                    ->whereNull('is_active')
                    ->orWhere('is_active', true));
            })
            ->get();

        $notifiedInApp = 0;
        $sentWhatsApp = 0;
        $notifiedSupervisorsInApp = 0;
        $sentSupervisorsWhatsApp = 0;

        foreach ($contracts as $contract) {
            if (! $contract->employee_name) {
                continue;
            }

            foreach ($hrUsers as $hrUser) {
                if ($this->hasInAppReminder($hrUser->id, (int) $contract->id, (int) $contract->days_before, $today)) {
                    continue;
                }

                $hrUser->notify(new EmployeeContractExpiryReminderNotification(
                    $contract,
                    (object) ['nama_karyawan' => $contract->employee_name],
                    (int) $contract->days_before,
                    $today->toDateString()
                ));
                $notifiedInApp++;
            }

            if (! $this->hasWhatsAppReminder((int) $contract->id, (int) $contract->days_before, $today)) {
                $sentWhatsApp += $this->sendWhatsAppReminder($contract, $today) ? 1 : 0;
            }

            if ((int) $contract->days_before === 45) {
                $supervisorResult = $this->notifyDirectSupervisor($contract, $today);
                $notifiedSupervisorsInApp += $supervisorResult['in_app_notifications'];
                $sentSupervisorsWhatsApp += $supervisorResult['whatsapp_notifications'];
            }
        }

        return [
            'contracts' => $contracts->count(),
            'in_app_notifications' => $notifiedInApp,
            'whatsapp_notifications' => $sentWhatsApp,
            'supervisor_in_app_notifications' => $notifiedSupervisorsInApp,
            'supervisor_whatsapp_notifications' => $sentSupervisorsWhatsApp,
        ];
    }

    private function dueContracts(Carbon $today): Collection
    {
        $reminderDates = collect(self::REMINDER_DAYS)
            ->map(fn (int $days): string => $today->copy()->addDays($days)->toDateString())
            ->all();

        return DB::table('t_kontrak_karyawan as contracts')
            ->leftJoin('m_karyawan as employees', 'employees.nik', '=', 'contracts.nik')
            ->leftJoin('m_karyawan as supervisor', 'supervisor.nik', '=', 'employees.atasan_langsung_nik')
            ->where('contracts.status_kontrak', 'AKTIF')
            ->whereIn('contracts.end_date', $reminderDates)
            ->orderBy('contracts.end_date')
            ->orderBy('employees.nama_karyawan')
            ->get([
                'contracts.id',
                'contracts.nik',
                'contracts.kontrak_ke',
                'contracts.jenis_kontrak',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.status_kontrak',
                'employees.nama_karyawan as employee_name',
                'employees.jabatan',
                'employees.departement',
                'employees.atasan_langsung_nik as direct_supervisor_nik',
                'supervisor.nama_karyawan as direct_supervisor_name',
            ])
            ->map(function (object $contract) use ($today): object {
                $contract->days_before = $today->diffInDays(Carbon::parse($contract->end_date)->startOfDay());

                return $contract;
            });
    }

    private function hasInAppReminder(int $userId, int $contractId, int $daysBefore, Carbon $today): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where('data->type', 'employee_contract_expiry_reminder')
            ->where('data->contract_id', $contractId)
            ->where('data->days_before', $daysBefore)
            ->where('data->as_of_date', $today->toDateString())
            ->exists();
    }

    private function hasWhatsAppReminder(int $contractId, int $daysBefore, Carbon $today): bool
    {
        return DB::table('notifications')
            ->where('data->type', 'employee_contract_expiry_whatsapp_reminder')
            ->where('data->contract_id', $contractId)
            ->where('data->days_before', $daysBefore)
            ->where('data->as_of_date', $today->toDateString())
            ->exists();
    }

    private function sendWhatsAppReminder(object $contract, Carbon $today): bool
    {
        $groupId = config('services.whatsapp.attendance_group_id');

        if (! $groupId) {
            Log::warning('Contract expiry WhatsApp reminder skipped: group ID missing');

            return false;
        }

        $message = $this->whatsAppMessage($contract);
        $sent = app(WhatsAppService::class)->sendMessage($groupId, $message);

        if ($sent) {
            DB::table('notifications')->insert([
                'id' => (string) str()->uuid(),
                'type' => 'whatsapp',
                'notifiable_type' => 'whatsapp_group',
                'notifiable_id' => 0,
                'data' => json_encode([
                    'type' => 'employee_contract_expiry_whatsapp_reminder',
                    'contract_id' => $contract->id,
                    'nik' => $contract->nik,
                    'employee_name' => $contract->employee_name,
                    'days_before' => (int) $contract->days_before,
                    'as_of_date' => $today->toDateString(),
                    'group_id' => $groupId,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $sent;
    }

    private function notifyDirectSupervisor(object $contract, Carbon $today): array
    {
        if (! $contract->direct_supervisor_nik) {
            Log::warning('Supervisor contract reminder skipped: direct supervisor NIK missing', [
                'contract_id' => $contract->id,
                'employee_nik' => $contract->nik,
            ]);

            return ['in_app_notifications' => 0, 'whatsapp_notifications' => 0];
        }

        $supervisor = Karyawan::query()
            ->where('nik', $contract->direct_supervisor_nik)
            ->whereRaw("UPPER(TRIM(COALESCE(status_karyawan, ''))) = ?", ['AKTIF'])
            ->first();

        if (! $supervisor) {
            Log::warning('Supervisor contract reminder skipped: active supervisor not found', [
                'contract_id' => $contract->id,
                'employee_nik' => $contract->nik,
                'direct_supervisor_nik' => $contract->direct_supervisor_nik,
            ]);

            return ['in_app_notifications' => 0, 'whatsapp_notifications' => 0];
        }

        $supervisorUser = User::query()
            ->where('username', $supervisor->nik)
            ->when(Schema::hasColumn('users', 'is_active'), function ($query): void {
                $query->where(fn ($activeQuery) => $activeQuery
                    ->whereNull('is_active')
                    ->orWhere('is_active', true));
            })
            ->first();
        $inAppNotifications = 0;
        $whatsAppNotifications = 0;

        if ($supervisorUser
            && ! $this->hasSupervisorInAppReminder($supervisorUser->id, (int) $contract->id, $today)) {
            $supervisorUser->notify(new SubordinateContractExpiryReminderNotification(
                $contract,
                (object) ['nama_karyawan' => $contract->employee_name],
                $today->toDateString()
            ));
            $inAppNotifications++;
        }

        if ($supervisor->no_hp
            && ! $this->hasSupervisorWhatsAppReminder((int) $contract->id, (string) $supervisor->nik, $today)
            && $this->sendSupervisorWhatsAppReminder($contract, $supervisor, $today)) {
            $whatsAppNotifications++;
        } elseif (! $supervisor->no_hp) {
            Log::warning('Supervisor contract WhatsApp reminder skipped: phone missing', [
                'contract_id' => $contract->id,
                'supervisor_nik' => $supervisor->nik,
            ]);
        }

        return [
            'in_app_notifications' => $inAppNotifications,
            'whatsapp_notifications' => $whatsAppNotifications,
        ];
    }

    private function hasSupervisorInAppReminder(int $userId, int $contractId, Carbon $today): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where('data->type', 'subordinate_contract_expiry_reminder')
            ->where('data->contract_id', $contractId)
            ->where('data->as_of_date', $today->toDateString())
            ->exists();
    }

    private function hasSupervisorWhatsAppReminder(int $contractId, string $supervisorNik, Carbon $today): bool
    {
        return DB::table('notifications')
            ->where('data->type', 'subordinate_contract_expiry_whatsapp_reminder')
            ->where('data->contract_id', $contractId)
            ->where('data->supervisor_nik', $supervisorNik)
            ->where('data->as_of_date', $today->toDateString())
            ->exists();
    }

    private function sendSupervisorWhatsAppReminder(object $contract, Karyawan $supervisor, Carbon $today): bool
    {
        $sent = app(WhatsAppService::class)->sendMessage(
            (string) $supervisor->no_hp,
            $this->supervisorWhatsAppMessage($contract, $supervisor)
        );

        if (! $sent) {
            Log::warning('Supervisor contract WhatsApp reminder was not accepted by provider', [
                'contract_id' => $contract->id,
                'supervisor_nik' => $supervisor->nik,
            ]);

            return false;
        }

        DB::table('notifications')->insert([
            'id' => (string) str()->uuid(),
            'type' => 'whatsapp',
            'notifiable_type' => 'whatsapp_supervisor',
            'notifiable_id' => 0,
            'data' => json_encode([
                'type' => 'subordinate_contract_expiry_whatsapp_reminder',
                'contract_id' => $contract->id,
                'nik' => $contract->nik,
                'employee_name' => $contract->employee_name,
                'supervisor_nik' => $supervisor->nik,
                'supervisor_name' => $supervisor->nama_karyawan,
                'days_before' => 45,
                'as_of_date' => $today->toDateString(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function whatsAppMessage(object $contract): string
    {
        return "Reminder Kontrak Karyawan\n\n"
            ."Karyawan: {$contract->employee_name}\n"
            ."NIK: {$contract->nik}\n"
            ."Kontrak: ke-{$contract->kontrak_ke} ({$contract->jenis_kontrak})\n"
            ."Tanggal selesai: {$contract->end_date}\n"
            ."Sisa waktu: {$contract->days_before} hari\n\n"
            .'Mohon HRD menindaklanjuti perpanjangan atau pembaruan status kontrak.';
    }

    private function supervisorWhatsAppMessage(object $contract, Karyawan $supervisor): string
    {
        return "Pengingat Kontrak Bawahan\n\n"
            ."Yth. Bapak/Ibu {$supervisor->nama_karyawan},\n\n"
            ."Kontrak bawahan Anda akan berakhir 45 hari lagi.\n"
            ."Karyawan: {$contract->employee_name}\n"
            ."NIK: {$contract->nik}\n"
            ."Kontrak: ke-{$contract->kontrak_ke} ({$contract->jenis_kontrak})\n"
            ."Tanggal berakhir: {$contract->end_date}\n\n"
            .'Silakan hubungi HRD untuk proses tindak lanjut kontrak karyawan tersebut.';
    }
}
