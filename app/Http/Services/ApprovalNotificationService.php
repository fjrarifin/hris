<?php

namespace App\Http\Services;

use App\Models\EmployeePermission;
use App\Models\ExtraOffRequest;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use App\Notifications\ApprovalReminderNotification;
use App\Notifications\DirectManagerDecisionNotification;
use App\Notifications\HrCancellationRequestNotification;
use App\Notifications\ShortNoticeApprovalNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApprovalNotificationService
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function notifyManager($request, $type)
    {
        try {

            $user = $request->user;

            $karyawan = Karyawan::where('nik', $user->username)->first();

            if (! $karyawan || ! $karyawan->nama_atasan_langsung) {
                Log::warning('Approval notification skipped: employee or direct supervisor name missing', [
                    'type' => $type,
                    'request_id' => $request->id ?? null,
                    'user_id' => $user->id ?? null,
                    'username' => $user->username ?? null,
                    'employee_found' => (bool) $karyawan,
                    'direct_supervisor_name' => $karyawan?->nama_atasan_langsung,
                ]);

                return;
            }

            $atasan = Karyawan::where('nama_karyawan', $karyawan->nama_atasan_langsung)->first();

            if (! $atasan) {
                Log::warning('Approval notification skipped: direct supervisor employee not found', [
                    'type' => $type,
                    'request_id' => $request->id ?? null,
                    'employee_nik' => $karyawan->nik,
                    'employee_name' => $karyawan->nama_karyawan,
                    'direct_supervisor_name' => $karyawan->nama_atasan_langsung,
                ]);

                return;
            }

            $atasanUser = User::where('username', $atasan->nik)->first();

            if (! $this->isActiveSupervisor($atasan, $atasanUser)) {
                $this->routeToHrBecauseSupervisorInactive($request, $type, $karyawan, $atasan);

                return;
            }

            if ($atasanUser) {
                $atasanUser->notify(
                    new \App\Notifications\ApprovalRequestNotification($request, $type)
                );
            }

            if ($atasan->no_hp) {

                $message = $this->buildMessage($request, $karyawan);

                $sent = $this->whatsAppService->sendMessage(
                    $this->normalizePhone($atasan->no_hp),
                    $message
                );

                if (! $sent) {
                    Log::warning('Approval WhatsApp notification was not accepted by provider', [
                        'type' => $type,
                        'request_id' => $request->id ?? null,
                        'employee_nik' => $karyawan->nik,
                        'supervisor_nik' => $atasan->nik,
                        'supervisor_phone' => $atasan->no_hp,
                    ]);
                }

                return;
            }

            Log::warning('Approval WhatsApp notification skipped: direct supervisor phone missing', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'employee_nik' => $karyawan->nik,
                'supervisor_nik' => $atasan->nik,
                'supervisor_name' => $atasan->nama_karyawan,
            ]);
        } catch (\Throwable $e) {
            Log::error('Approval notification failed', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildMessage($request, $karyawan): string
    {
        $link = $this->publicApprovalUrl($request->approval_token);
        $header = "━━━━━━━━━━━━━━━━━━━━━━━\n🏢 *HomPim Play*\n━━━━━━━━━━━━━━━━━━━━━━━";
        $footer = "_Pesan ini dikirim otomatis oleh sistem HomPim Play._\n_Harap tidak membalas pesan ini._\n━━━━━━━━━━━━━━━━━━━━━━━";

        if ($request instanceof LeaveRequest) {
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            $duration = $start->diffInDays($end) + 1;

            return <<<MSG
            {$header}

            Yth. Bapak/Ibu,

            Terdapat *pengajuan cuti* baru yang memerlukan persetujuan Anda.

            👤 *Karyawan*
            {$karyawan->nama_karyawan}

            📅 *Periode Cuti*
            {$start->format('d M Y')} → {$end->format('d M Y')}
            ⏳ Durasi: {$duration} hari kerja

            Mohon segera ditindaklanjuti melalui tautan berikut:
            👇
            {$link}

            {$footer}
            MSG;
        }

        if ($request instanceof PublicHolidayRequest) {
            $holiday = Carbon::parse($request->holiday->holiday_date)->format('d M Y');
            $claim = Carbon::parse($request->claim_date)->format('d M Y');

            return <<<MSG
            {$header}

            Yth. Bapak/Ibu,

            Terdapat *pengajuan Public Holiday* baru yang memerlukan persetujuan Anda.

            👤 *Karyawan*
            {$karyawan->nama_karyawan}

            🗓️ *Tanggal Hari Libur*
            {$holiday}

            📅 *Tanggal Pengganti*
            {$claim}

            Mohon segera ditindaklanjuti melalui tautan berikut:
            👇
            {$link}

            {$footer}
            MSG;
        }

        if ($request instanceof ExtraOffRequest) {
            $claim = Carbon::parse($request->claim_date)->format('d M Y');
            $periodStart = Carbon::parse($request->source_period_start)->format('d M Y');
            $periodEnd = Carbon::parse($request->source_period_end)->format('d M Y');

            return "Pengajuan Extra Off baru membutuhkan persetujuan Anda.\n\n"
                ."Nama: {$karyawan->nama_karyawan}\n"
                ."Sumber EO: {$periodStart} - {$periodEnd}\n"
                ."Tanggal Pengambilan: {$claim}\n\n"
                ."Silakan proses melalui tautan berikut:\n{$link}";
        }

        if ($request instanceof EmployeePermission) {
            $date = Carbon::parse($request->date)->format('d M Y');
            $type = $request->type === 'sakit' ? 'Sakit' : 'Izin Tidak Masuk';
            $reason = $request->reason ?: '-';

            return <<<MSG
            {$header}

            Yth. Bapak/Ibu,

            Terdapat *pengajuan {$type}* baru yang memerlukan persetujuan Anda.

            ðŸ‘¤ *Karyawan*
            {$karyawan->nama_karyawan}

            ðŸ“… *Tanggal*
            {$date}

            ðŸ“ *Alasan*
            {$reason}

            Mohon segera ditindaklanjuti melalui tautan berikut:
            ðŸ‘‡
            {$link}

            {$footer}
            MSG;
        }

        if ($request instanceof OvertimeRequest) {
            $date = Carbon::parse($request->date)->format('d M Y');

            return <<<MSG
            {$header}

            Yth. Bapak/Ibu,

            Terdapat *pengajuan lembur* baru yang memerlukan persetujuan Anda.

            ðŸ‘¤ *Karyawan*
            {$karyawan->nama_karyawan}

            ðŸ“… *Tanggal*
            {$date}

            â° *Jam*
            {$request->start_time} - {$request->end_time}

            ðŸ“ *Pekerjaan/Alasan*
            {$request->reason}

            Mohon segera ditindaklanjuti melalui tautan berikut:
            ðŸ‘‡
            {$link}

            {$footer}
            MSG;
        }

        return "━━━━━━━━━━━━━━━━━━━━━━━\n🏢 *HomPim Play*\n━━━━━━━━━━━━━━━━━━━━━━━\n\nYth. Bapak/Ibu,\n\nTerdapat pengajuan baru yang memerlukan persetujuan Anda.\n\n👇\n{$link}\n\n{$footer}";
    }

    public function notifySecondManager($request)
    {
        $user = $request->user;

        $karyawan = Karyawan::where('nik', $user->username)->first();

        if (! $karyawan || ! $karyawan->atasan_tidak_langsung) {
            return;
        }

        $atasan = Karyawan::where('nama_karyawan', $karyawan->atasan_tidak_langsung)->first();

        if (! $atasan) {
            return;
        }

        if ($atasan->no_hp) {

            $message = "📢 APPROVAL LEVEL 2

            Pengajuan {$karyawan->nama_karyawan} sudah disetujui atasan langsung.

            Silakan lakukan approval tahap kedua.

            🔗 ".$this->publicApprovalUrl($request->approval_token);

            $this->whatsAppService->sendMessage(
                $atasan->no_hp,
                $message
            );
        }
    }

    public function notifyIndirectManagerOfDirectManagerDecision($request, string $type, string $status): void
    {
        try {
            $user = $request->user;
            $karyawan = Karyawan::where('nik', $user->username)->first();

            if (! $karyawan || ! $karyawan->atasan_tidak_langsung) {
                return;
            }

            $atasan = Karyawan::where('nama_karyawan', $karyawan->atasan_tidak_langsung)->first();

            if (! $atasan) {
                return;
            }

            $atasanUser = User::where('username', $atasan->nik)->first();

            if ($atasanUser) {
                $atasanUser->notify(
                    new DirectManagerDecisionNotification($request, $type, $status)
                );
            }

            if ($atasan->no_hp) {
                $message = $this->buildDirectManagerDecisionMessage($request, $karyawan, $type, $status);

                $this->whatsAppService->sendMessage(
                    $this->normalizePhone($atasan->no_hp),
                    $message
                );
            }
        } catch (\Throwable $e) {
            Log::error('Indirect manager decision notification failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyHrGroups($request, string $type): void
    {
        try {
            $this->notifyHrUsers($request, $type);
            $groups = $this->hrGroupIds($type);

            if (empty($groups)) {
                return;
            }

            $message = $this->buildHrGroupMessage($request, $type);

            foreach ($groups as $groupId) {
                $this->whatsAppService->sendMessage($groupId, $message);
            }
        } catch (\Throwable $e) {
            Log::error('HR group notification failed', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyShortNoticeToHr(object $request, string $type): void
    {
        try {
            $request->loadMissing('user.karyawan');

            User::query()
                ->where('level', 2)
                ->get()
                ->each(fn (User $hr) => $hr->notify(new ShortNoticeApprovalNotification($request, $type)));

            $groups = $this->hrGroupIds($type);
            if (empty($groups)) {
                return;
            }

            $message = $this->buildShortNoticeHrMessage($request, $type);
            foreach ($groups as $groupId) {
                $this->whatsAppService->sendMessage($groupId, $message);
            }
        } catch (\Throwable $e) {
            Log::error('Short notice HR notification failed', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyManagerReminder(object $request, string $type, int $reminderNumber): bool
    {
        $cacheKey = 'approval-reminder:'.$this->requestKey($request, $type).':'.$reminderNumber.':'.now()->toDateString();
        if (! Cache::add($cacheKey, true, now()->addDays(2))) {
            return false;
        }

        try {
            $request->loadMissing('user.karyawan');
            $employee = $request->user?->karyawan;
            $manager = $employee ? $this->directManagerFor($employee) : null;

            if (! $employee || ! $manager) {
                return false;
            }

            $managerUser = User::query()->where('username', $manager->nik)->first();
            if (! $this->isActiveSupervisor($manager, $managerUser)) {
                $this->routeToHrBecauseSupervisorInactive($request, $type, $employee, $manager);

                return true;
            }

            if ($managerUser) {
                $managerUser->notify(new ApprovalReminderNotification($request, $type, $reminderNumber));
            }

            if (filled($manager->no_hp)) {
                $this->whatsAppService->sendMessage(
                    $this->normalizePhone($manager->no_hp),
                    $this->buildManagerReminderMessage($request, $type, $employee, $reminderNumber)
                );
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Approval manager reminder failed', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'reminder' => $reminderNumber,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyHrUsers($request, string $type): void
    {
        $request->loadMissing('user');

        User::query()
            ->where('level', 2)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $hr) => $hr->notify(
                new \App\Notifications\ApprovalRequestNotification($request, $type)
            ));
    }

    public function notifyHrCancellationRequest(
        object $request,
        string $type,
        Karyawan $employee,
        User $supervisor,
        string $reason
    ): void {
        try {
            $request->loadMissing('user.karyawan');

            User::query()
                ->where('level', 2)
                ->get()
                ->each(fn (User $hr) => $hr->notify(
                    new HrCancellationRequestNotification($request, $type, $employee, $supervisor, $reason)
                ));

            $groups = $this->hrGroupIds($type);
            if (empty($groups)) {
                return;
            }

            $message = $this->buildHrCancellationMessage($request, $type, $employee, $supervisor, $reason);
            foreach ($groups as $groupId) {
                $this->whatsAppService->sendMessage($groupId, $message);
            }
        } catch (\Throwable $e) {
            Log::error('HR cancellation request notification failed', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'employee_nik' => $employee->nik,
                'supervisor_id' => $supervisor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function hrGroupIds(string $type): array
    {
        return match (strtoupper($type)) {
            'PH' => ['120363425559804944@g.us'],
            'CUTI' => [
                '120363426186027080@g.us',
            ],
            'LEMBUR' => [
                '120363426538856642@g.us',
            ],
            'EO' => array_filter([
                trim((string) config('services.whatsapp.hr_extra_off_group_id')),
            ]),
            'IZIN', 'SAKIT' => array_filter([
                trim((string) config('services.whatsapp.hr_permission_group_id')),
            ]),
            default => [],
        };
    }

    private function routeToHrBecauseSupervisorInactive(
        object $request,
        string $type,
        Karyawan $employee,
        Karyawan $supervisor
    ): void {
        $this->markAsWaitingHr($request);
        $this->notifyHrUsers($request, $type);

        $groupId = trim((string) config('services.whatsapp.attendance_group_id'));
        if ($groupId !== '') {
            $this->whatsAppService->sendMessage(
                $groupId,
                $this->buildInactiveSupervisorHrMessage($request, $type, $employee, $supervisor)
            );
        }

        Log::warning('Approval routed to HR because direct supervisor is inactive', [
            'type' => $type,
            'request_id' => $request->id ?? null,
            'employee_nik' => $employee->nik,
            'supervisor_nik' => $supervisor->nik,
            'supervisor_status' => $supervisor->status_karyawan,
        ]);
    }

    private function markAsWaitingHr(object $request): void
    {
        if (! method_exists($request, 'forceFill')) {
            return;
        }

        $request->forceFill([
            'manager_approved_at' => $request->manager_approved_at ?: now(),
            'manager_approved_by' => $request->manager_approved_by,
            'approval_token' => null,
            'approval_token_expires_at' => null,
        ])->save();
    }

    private function isActiveSupervisor(Karyawan $supervisor, ?User $supervisorUser = null): bool
    {
        $employeeActive = strtoupper(trim((string) $supervisor->status_karyawan)) === 'AKTIF';

        return $employeeActive && (! $supervisorUser || $supervisorUser->is_active);
    }

    private function buildInactiveSupervisorHrMessage(
        object $request,
        string $type,
        Karyawan $employee,
        Karyawan $supervisor
    ): string {
        return "*APPROVAL DIALIHKAN KE HRD*\n\n"
            ."Atasan langsung karyawan tidak aktif, sehingga pengajuan dialihkan ke HRD.\n\n"
            ."Atasan langsung: {$supervisor->nama_karyawan} ({$supervisor->nik})\n"
            ."Status atasan: ".($supervisor->status_karyawan ?: '-')."\n\n"
            .$this->approvalSummary($request, $type, $employee)."\n\n"
            .'Menu HRD: '.$this->hrApprovalUrl($type);
    }

    private function buildShortNoticeHrMessage(object $request, string $type): string
    {
        return "*PENGAJUAN KURANG DARI 12 JAM*\n\n"
            ."Pengajuan berikut dibuat kurang dari 12 jam sebelum tanggal pengajuan.\n"
            ."Mohon HRD koordinasikan ke IT jika approval perlu dibantu agar field approval terisi lengkap.\n\n"
            .$this->approvalSummary($request, $type)."\n\n"
            .'Menu HRD: '.$this->hrApprovalUrl($type);
    }

    private function buildManagerReminderMessage(object $request, string $type, Karyawan $employee, int $reminderNumber): string
    {
        $expiresAt = $request->approval_token_expires_at
            ? Carbon::parse($request->approval_token_expires_at)->format('d M Y H:i')
            : '-';

        return "*REMINDER APPROVAL #{$reminderNumber}*\n\n"
            ."Pengajuan bawahan Anda belum di-approve dan akan expired jika tidak segera diproses.\n\n"
            .$this->approvalSummary($request, $type, $employee)."\n\n"
            ."Expired: {$expiresAt} WIB\n"
            ."Link approval:\n".$this->publicApprovalUrl($request->approval_token);
    }

    private function approvalSummary(object $request, string $type, ?Karyawan $employee = null): string
    {
        $request->loadMissing('user.karyawan');
        $employee ??= $request->user?->karyawan;
        $employeeName = $employee?->nama_karyawan ?? $request->user?->name ?? '-';
        $employeeNik = $employee?->nik ?? $request->user?->username ?? '-';
        $label = match (strtoupper($type)) {
            'CUTI' => 'Cuti',
            'PH' => 'Public Holiday',
            'EO' => 'Extra Off',
            'SAKIT' => 'Sakit',
            'IZIN' => 'Izin',
            default => $type,
        };

        $detail = match (true) {
            $request instanceof LeaveRequest => 'Periode: '.Carbon::parse($request->start_date)->format('d M Y').' - '.Carbon::parse($request->end_date)->format('d M Y')."\nAlasan: ".($request->reason ?: '-'),
            $request instanceof PublicHolidayRequest => 'Tanggal Pengambilan: '.Carbon::parse($request->claim_date)->format('d M Y')."\nPH: ".(optional($request->holiday)->name ?: '-'),
            $request instanceof ExtraOffRequest => 'Tanggal Pengambilan: '.Carbon::parse($request->claim_date)->format('d M Y')."\nSumber EO: ".Carbon::parse($request->source_period_start)->format('d M Y').' - '.Carbon::parse($request->source_period_end)->format('d M Y'),
            $request instanceof EmployeePermission => 'Tanggal: '.Carbon::parse($request->date)->format('d M Y').(($request->end_date && ! $request->end_date->isSameDay($request->date)) ? ' - '.$request->end_date->format('d M Y') : '')."\nAlasan: ".($request->reason ?: '-'),
            default => '',
        };

        return "Jenis: {$label}\n"
            ."Nama: {$employeeName}\n"
            ."NIK: {$employeeNik}\n"
            .$detail;
    }

    private function directManagerFor(Karyawan $employee): ?Karyawan
    {
        if (! $employee->nama_atasan_langsung) {
            return null;
        }

        return Karyawan::query()
            ->where('nama_karyawan', $employee->nama_atasan_langsung)
            ->first();
    }

    private function requestKey(object $request, string $type): string
    {
        return strtolower($type).':'.class_basename($request).':'.$request->id;
    }

    private function hrApprovalUrl(string $type): string
    {
        $approvalType = match (strtoupper($type)) {
            'CUTI' => 'leave',
            'PH' => 'ph',
            'EO' => 'extra-off',
            'IZIN', 'SAKIT' => 'permission',
            default => strtolower($type),
        };

        return rtrim((string) config('services.frontend.base_url'), '/').'/hr/approvals/'.$approvalType;
    }

    private function buildHrGroupMessage($request, string $type): string
    {
        $request->loadMissing('user.karyawan');
        $employeeName = $request->user->karyawan->nama_karyawan ?? $request->user->name ?? '-';
        $employeeNik = $request->user->username ?? '-';
        $approvalType = match (strtoupper($type)) {
            'CUTI' => 'leave',
            'PH' => 'ph',
            'EO' => 'extra-off',
            'IZIN' => 'permission',
            'SAKIT' => 'permission',
            'LEMBUR' => 'overtime',
            default => strtolower($type),
        };
        $link = rtrim((string) config('services.frontend.base_url'), '/').'/hr/approvals/'.$approvalType;
        $label = match (strtoupper($type)) {
            'CUTI' => 'Cuti',
            'PH' => 'Public Holiday',
            'EO' => 'Extra Off',
            'IZIN' => 'Izin/Sakit',
            'SAKIT' => 'Sakit',
            'LEMBUR' => 'Lembur',
            default => $type,
        };

        $detail = '';

        if ($request instanceof LeaveRequest) {
            $start = Carbon::parse($request->start_date)->format('d M Y');
            $end = Carbon::parse($request->end_date)->format('d M Y');
            $detail = "Periode: {$start} - {$end}\nAlasan: ".($request->reason ?: '-');
        } elseif ($request instanceof PublicHolidayRequest) {
            $claim = Carbon::parse($request->claim_date)->format('d M Y');
            $holiday = optional($request->holiday)->name ?: 'Hari Libur';
            $detail = "PH: {$holiday}\nTanggal Pengambilan: {$claim}";
        } elseif ($request instanceof ExtraOffRequest) {
            $claim = Carbon::parse($request->claim_date)->format('d M Y');
            $periodStart = Carbon::parse($request->source_period_start)->format('d M Y');
            $periodEnd = Carbon::parse($request->source_period_end)->format('d M Y');
            $detail = "Sumber EO: {$periodStart} - {$periodEnd}\nTanggal Pengambilan: {$claim}";
        } elseif ($request instanceof EmployeePermission) {
            $date = Carbon::parse($request->date)->format('d M Y');
            $kind = $request->type === 'sakit' ? 'Sakit' : 'Izin Tidak Masuk';
            $detail = "Jenis: {$kind}\nTanggal: {$date}\nAlasan: ".($request->reason ?: '-');
        } elseif ($request instanceof OvertimeRequest) {
            $date = Carbon::parse($request->date)->format('d M Y');
            $detail = "Tanggal: {$date}\nJam: {$request->start_time} - {$request->end_time}\nAlasan: {$request->reason}";
        }

        return "Pengajuan {$label} membutuhkan approval HR.\n\n"
            ."Nama: {$employeeName}\n"
            ."NIK: {$employeeNik}\n"
            ."{$detail}\n\n"
            ."Silakan proses di aplikasi:\n{$link}";
    }

    private function buildHrCancellationMessage(
        object $request,
        string $type,
        Karyawan $employee,
        User $supervisor,
        string $reason
    ): string {
        $label = match (strtoupper($type)) {
            'PH' => 'PH',
            'EO' => 'Extra Off',
            'IZIN', 'SAKIT' => 'Izin/Sakit',
            default => 'Cuti',
        };
        $dateLabel = '-';

        if ($request instanceof PublicHolidayRequest) {
            $dateLabel = Carbon::parse($request->claim_date)->format('d M Y');
        } elseif ($request instanceof ExtraOffRequest) {
            $dateLabel = Carbon::parse($request->claim_date)->format('d M Y');
        } elseif ($request instanceof EmployeePermission) {
            $dateLabel = Carbon::parse($request->date)->format('d M Y');
        } elseif ($request instanceof LeaveRequest) {
            $start = Carbon::parse($request->start_date)->format('d M Y');
            $end = Carbon::parse($request->end_date)->format('d M Y');
            $dateLabel = "{$start} - {$end}";
        }

        return "*PERMINTAAN PEMBATALAN {$label}*\n\n"
            ."Atasan meminta HRD membatalkan pengajuan {$label} berikut:\n\n"
            ."Nama: {$employee->nama_karyawan}\n"
            ."NIK: {$employee->nik}\n"
            ."Tanggal: {$dateLabel}\n"
            ."Atasan: {$supervisor->name}\n"
            ."Alasan: {$reason}\n\n"
            .'Silakan proses pembatalan di menu Approval HRD.';
    }

    private function buildDirectManagerDecisionMessage($request, Karyawan $karyawan, string $type, string $status): string
    {
        $statusLabel = $status === 'approved' ? 'disetujui' : 'ditolak';

        if ($request instanceof LeaveRequest) {
            $start = Carbon::parse($request->start_date)->format('d M Y');
            $end = Carbon::parse($request->end_date)->format('d M Y');

            return "📌 *Keputusan Cuti dari Atasan Langsung*\n\n"
                ."Nama: {$karyawan->nama_karyawan}\n"
                ."Periode: {$start} - {$end}\n\n"
                ."Status: *{$statusLabel}* oleh atasan langsung.";
        }

        if ($request instanceof PublicHolidayRequest) {
            $holiday = optional($request->holiday)->name ?: 'Hari Libur';
            $claim = Carbon::parse($request->claim_date)->format('d M Y');

            return "📌 *Keputusan PH dari Atasan Langsung*\n\n"
                ."Nama: {$karyawan->nama_karyawan}\n"
                ."PH: {$holiday}\n"
                ."Tanggal Pengambilan: {$claim}\n\n"
                ."Status: *{$statusLabel}* oleh atasan langsung.";
        }

        if ($request instanceof EmployeePermission) {
            $date = Carbon::parse($request->date)->format('d M Y');
            $type = $request->type === 'sakit' ? 'Sakit' : 'Izin Tidak Masuk';

            return "ðŸ“Œ *Keputusan {$type} dari Atasan Langsung*\n\n"
                ."Nama: {$karyawan->nama_karyawan}\n"
                ."Tanggal: {$date}\n\n"
                ."Status: *{$statusLabel}* oleh atasan langsung.";
        }

        if ($request instanceof OvertimeRequest) {
            $date = Carbon::parse($request->date)->format('d M Y');

            return "ðŸ“Œ *Keputusan Lembur dari Atasan Langsung*\n\n"
                ."Nama: {$karyawan->nama_karyawan}\n"
                ."Tanggal: {$date}\n"
                ."Jam: {$request->start_time} - {$request->end_time}\n\n"
                ."Status: *{$statusLabel}* oleh atasan langsung.";
        }

        return "Pengajuan {$type} {$karyawan->nama_karyawan} telah {$statusLabel} oleh atasan langsung.";
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        return $phone;
    }

    private function publicApprovalUrl(string $token): string
    {
        return rtrim((string) config('services.public_approval.base_url'), '/').'/approval/'.$token;
    }
}
