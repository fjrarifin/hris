<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use App\Models\User;
use App\Notifications\EmployeeContractExpiryReminderNotification;
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
                $query->whereNull('is_active')->orWhere('is_active', true);
            })
            ->get();

        $notifiedInApp = 0;
        $sentWhatsApp = 0;

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
        }

        return [
            'contracts' => $contracts->count(),
            'in_app_notifications' => $notifiedInApp,
            'whatsapp_notifications' => $sentWhatsApp,
        ];
    }

    private function dueContracts(Carbon $today): Collection
    {
        $reminderDates = collect(self::REMINDER_DAYS)
            ->map(fn (int $days): string => $today->copy()->addDays($days)->toDateString())
            ->all();

        return DB::table('t_kontrak_karyawan as contracts')
            ->leftJoin('m_karyawan as employees', 'employees.nik', '=', 'contracts.nik')
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
}
