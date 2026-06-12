<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use App\Models\AttendanceCorrection;
use App\Models\EmployeeDailySchedule;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\User;
use App\Notifications\IncompleteAttendanceNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IncompleteAttendanceWhatsAppReport
{
    private const MAX_MESSAGE_LENGTH = 3500;

    public function recordsForDate(Carbon $date, bool $includeResolved = false): Collection
    {
        $scheduledNiks = EmployeeDailySchedule::query()
            ->join(
                'attendance_schedule_categories as categories',
                'categories.id',
                '=',
                'employee_daily_schedules.schedule_category_id'
            )
            ->whereDate('employee_daily_schedules.schedule_date', $date)
            ->where('categories.is_workday', true)
            ->pluck('employee_daily_schedules.karyawan_nik')
            ->unique();

        $logsByPin = FingerspotAttendanceLog::query()
            ->whereDate('scan_date', $date)
            ->orderBy('scan_date')
            ->get(['pin', 'scan_date', 'status_scan'])
            ->groupBy(fn (FingerspotAttendanceLog $log) => (string) $log->pin);

        $loggedEmployeeNiks = Karyawan::query()
            ->whereIn('pin', $logsByPin->keys())
            ->pluck('nik');
        $corrections = AttendanceCorrection::query()
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('nik');
        $employees = Karyawan::query()
            ->whereIn('nik', $scheduledNiks->merge($loggedEmployeeNiks)->merge($corrections->keys())->unique())
            ->orderBy('departement')
            ->orderBy('nama_karyawan')
            ->get();

        return $employees
            ->map(function (Karyawan $employee) use ($logsByPin, $corrections, $includeResolved): ?array {
                $rawScans = $this->scanSummary(
                    $logsByPin->get((string) $employee->pin, collect())
                );
                $correction = $corrections->get($employee->nik);

                if ((bool) $rawScans['scan_in'] === (bool) $rawScans['scan_out']) {
                    return null;
                }

                $scans = [
                    'scan_in' => $correction?->corrected_scan_in ?: $rawScans['scan_in'],
                    'scan_out' => $correction?->corrected_scan_out ?: $rawScans['scan_out'],
                ];

                if (! $includeResolved && $scans['scan_in'] && $scans['scan_out']) {
                    return null;
                }

                return [
                    'nik' => $employee->nik,
                    'name' => $employee->nama_karyawan,
                    'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
                    'department' => $employee->departement ?: ($employee->divisi ?: '-'),
                    'phone' => trim((string) $employee->no_hp),
                    'direct_supervisor_name' => trim((string) $employee->nama_atasan_langsung),
                    'raw_scan_in' => $rawScans['scan_in'],
                    'raw_scan_out' => $rawScans['scan_out'],
                    'scan_in' => $scans['scan_in'],
                    'scan_out' => $scans['scan_out'],
                    'finding' => $this->finding($rawScans),
                    'is_resolved' => (bool) $scans['scan_in'] && (bool) $scans['scan_out'],
                    'correction' => $correction ? [
                        'id' => $correction->id,
                        'corrected_scan_in' => $correction->corrected_scan_in,
                        'corrected_scan_out' => $correction->corrected_scan_out,
                        'has_missing_attendance_form' => $correction->has_missing_attendance_form,
                        'notes' => $correction->notes,
                        'updated_at' => $correction->updated_at?->toIso8601String(),
                    ] : null,
                ];
            })
            ->filter()
            ->values();
    }

    public function messagesForDate(Carbon $date, bool $test = false): array
    {
        $records = $this->recordsForDate($date);
        $title = $test ? '*[TEST] LAPORAN ABSENSI BELUM LENGKAP*' : '*LAPORAN ABSENSI BELUM LENGKAP*';
        $header = implode("\n", [
            $title,
            'Tanggal absensi: '.$date->format('d/m/Y'),
            'Total temuan: '.$records->count().' karyawan',
            '',
        ]);

        if ($records->isEmpty()) {
            return [$header.'Tidak ada karyawan terjadwal kerja dengan scan masuk/pulang yang tidak lengkap.'];
        }

        $messages = [];
        $message = $header;

        foreach ($records as $index => $record) {
            $item = $this->recordMessage($record, $index + 1);

            if (strlen($message."\n".$item) > self::MAX_MESSAGE_LENGTH && $message !== $header) {
                $messages[] = trim($message);
                $message = $header;
            }

            $message .= $item;
        }

        $messages[] = trim($message)."\n\nMohon HRD melakukan pengecekan dan koreksi jika diperlukan.";

        if (count($messages) > 1) {
            foreach ($messages as $index => $part) {
                $messages[$index] = $title.' (Bagian '.($index + 1).'/'.count($messages).')'
                    .substr($part, strlen($title));
            }
        }

        return $messages;
    }

    public function sendForDate(Carbon $date, bool $test = false): array
    {
        $messages = $this->messagesForDate($date, $test);
        $groupId = trim((string) config('services.whatsapp.attendance_group_id'));

        if ($groupId === '' || ! config('services.whatsapp.url') || ! config('services.whatsapp.device_id')) {
            Log::warning('Incomplete attendance WhatsApp report skipped: WhatsApp config incomplete', [
                'date' => $date->toDateString(),
            ]);

            return [
                'ok' => false,
                'messages' => $messages,
                'reason' => 'Konfigurasi WhatsApp belum lengkap.',
            ];
        }

        $ok = true;
        foreach ($messages as $message) {
            $ok = app(WhatsAppService::class)->sendMessage($groupId, $message) && $ok;
        }

        return [
            'ok' => $ok,
            'messages' => $messages,
            'reason' => $ok ? null : 'Pengiriman WhatsApp gagal.',
        ];
    }

    public function employeeMessagesForDate(Carbon $date, bool $test = false): Collection
    {
        $overrideRecipient = $this->employeeWarningOverrideRecipient();

        return $this->recordsForDate($date)
            ->filter(fn (array $record): bool => $this->shouldReceiveIncompleteAttendanceWarning($record))
            ->filter(fn (array $record): bool => $overrideRecipient !== null || $record['phone'] !== '')
            ->map(fn (array $record): array => [
                'nik' => $record['nik'],
                'name' => $record['name'],
                'phone' => $overrideRecipient['phone'] ?? $record['phone'],
                'recipient_nik' => $overrideRecipient['nik'] ?? $record['nik'],
                'recipient_name' => $overrideRecipient['name'] ?? $record['name'],
                'is_redirected' => $overrideRecipient !== null,
                'record' => $record,
                'message' => $this->employeeWarningMessage($record, $date, $test),
            ])
            ->values();
    }

    public function sendEmployeeWarningsForDate(Carbon $date, bool $test = false): array
    {
        $notifications = $this->employeeMessagesForDate($date, $test);
        $appNotificationCount = $this->storeEmployeeAppNotificationsForDate($date, $test);

        if (! config('services.whatsapp.url') || ! config('services.whatsapp.device_id')) {
            return [
                'ok' => false,
                'sent_count' => 0,
                'skipped_count' => $notifications->count(),
                'app_notification_count' => $appNotificationCount,
                'notifications' => $notifications,
                'reason' => 'Konfigurasi WhatsApp belum lengkap.',
            ];
        }

        $ok = true;
        foreach ($notifications as $notification) {
            $ok = app(WhatsAppService::class)->sendMessage(
                $notification['phone'],
                $notification['message']
            ) && $ok;
        }

        return [
            'ok' => $ok,
            'sent_count' => $notifications->count(),
            'skipped_count' => $this->recordsForDate($date)->count() - $notifications->count(),
            'app_notification_count' => $appNotificationCount,
            'notifications' => $notifications,
            'reason' => $ok ? null : 'Pengiriman WhatsApp pribadi gagal.',
        ];
    }

    public function storeEmployeeAppNotificationsForDate(Carbon $date, bool $test = false): int
    {
        if ($test || trim((string) config('services.whatsapp.attendance_warning_override_nik')) !== '') {
            return 0;
        }

        $count = 0;

        foreach ($this->recordsForDate($date) as $record) {
            if (! $this->shouldReceiveIncompleteAttendanceWarning($record)) {
                continue;
            }

            $count += $this->storeEmployeeAppNotification($record, $date);
        }

        return $count;
    }

    private function storeEmployeeAppNotification(array $record, Carbon $date): int
    {
        $user = User::query()
            ->where('username', $record['nik'])
            ->first();

        if (! $user) {
            return 0;
        }

        $alreadyExists = $user->notifications()
            ->where('type', IncompleteAttendanceNotification::class)
            ->where('data->date', $date->toDateString())
            ->first();

        if ($alreadyExists) {
            $this->retryEmployeeAppPush($user, $alreadyExists, $record, $date);

            return 0;
        }

        $user->notify(new IncompleteAttendanceNotification($record, $date));

        return 1;
    }

    private function retryEmployeeAppPush(User $user, object $notification, array $record, Carbon $date): void
    {
        if ($notification->read_at) {
            return;
        }

        $data = $notification->data ?? [];

        if (! empty($data['mobile_push_sent_at'])) {
            return;
        }

        $payload = [
            ...(new IncompleteAttendanceNotification($record, $date))->toArray($user),
            'mobile_path' => $data['mobile_path'] ?? '/tabs/attendance?start_date='.$date->toDateString().'&end_date='.$date->toDateString(),
        ];

        $sent = app(FirebasePushService::class)->sendToUser(
            (int) $user->id,
            $payload['title'] ?? 'Absensi Belum Lengkap',
            $payload['message'] ?? '',
            $payload
        );

        if ($sent < 1) {
            return;
        }

        $notification->forceFill([
            'data' => [
                ...$data,
                'mobile_push_sent_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    public function supervisorMessagesForDate(Carbon $date, bool $test = false): Collection
    {
        $overrideRecipient = $this->employeeWarningOverrideRecipient();
        $records = $this->recordsForDate($date)
            ->filter(fn (array $record): bool => $this->shouldReceiveIncompleteAttendanceWarning($record))
            ->filter(fn (array $record): bool => $record['direct_supervisor_name'] !== '');

        $supervisors = Karyawan::query()
            ->whereIn('nama_karyawan', $records->pluck('direct_supervisor_name')->unique())
            ->get(['nik', 'nama_karyawan', 'no_hp'])
            ->keyBy('nama_karyawan');

        return $records
            ->groupBy('direct_supervisor_name')
            ->map(function (Collection $findings, string $supervisorName) use ($supervisors, $overrideRecipient, $date, $test): ?array {
                $supervisor = $supervisors->get($supervisorName);
                $phone = $overrideRecipient['phone'] ?? trim((string) $supervisor?->no_hp);

                if ($phone === '') {
                    return null;
                }

                return [
                    'supervisor_nik' => $supervisor?->nik,
                    'supervisor_name' => $supervisor?->nama_karyawan ?? $supervisorName,
                    'phone' => $phone,
                    'recipient_nik' => $overrideRecipient['nik'] ?? $supervisor?->nik,
                    'recipient_name' => $overrideRecipient['name'] ?? ($supervisor?->nama_karyawan ?? $supervisorName),
                    'is_redirected' => $overrideRecipient !== null,
                    'employee_count' => $findings->count(),
                    'message' => $this->supervisorWarningMessage($findings, $date, $test),
                ];
            })
            ->filter()
            ->values();
    }

    public function sendSupervisorWarningsForDate(Carbon $date, bool $test = false): array
    {
        $notifications = $this->supervisorMessagesForDate($date, $test);

        if (! config('services.whatsapp.url') || ! config('services.whatsapp.device_id')) {
            return [
                'ok' => false,
                'sent_count' => 0,
                'notifications' => $notifications,
                'reason' => 'Konfigurasi WhatsApp belum lengkap.',
            ];
        }

        $ok = true;
        foreach ($notifications as $notification) {
            $ok = app(WhatsAppService::class)->sendMessage(
                $notification['phone'],
                $notification['message']
            ) && $ok;
        }

        return [
            'ok' => $ok,
            'sent_count' => $notifications->count(),
            'notifications' => $notifications,
            'reason' => $ok ? null : 'Pengiriman WhatsApp atasan gagal.',
        ];
    }

    private function employeeWarningOverrideRecipient(): ?array
    {
        $nik = trim((string) config('services.whatsapp.attendance_warning_override_nik'));

        if ($nik === '') {
            return null;
        }

        $employee = Karyawan::query()
            ->where('nik', $nik)
            ->first(['nik', 'nama_karyawan', 'no_hp']);

        if (! $employee || trim((string) $employee->no_hp) === '') {
            throw new \RuntimeException(
                'Nomor WhatsApp tujuan pengujian notifikasi absensi belum ditemukan untuk NIK '.$nik.'.'
            );
        }

        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'phone' => trim((string) $employee->no_hp),
        ];
    }

    private function shouldReceiveIncompleteAttendanceWarning(array $record): bool
    {
        return strtoupper(trim((string) ($record['position'] ?? ''))) !== 'MANAGER';
    }

    private function recordMessage(array $record, int $number): string
    {
        return implode("\n", [
            $number.'. *'.$record['name'].'*',
            '   NIK: '.$record['nik'],
            '   Jabatan: '.$record['position'],
            '   Departemen: '.$record['department'],
            '   Temuan: '.$record['finding'],
            '   Scan masuk: '.$this->scanTime($record['scan_in']),
            '   Scan pulang: '.$this->scanTime($record['scan_out']),
            '',
        ]);
    }

    private function employeeWarningMessage(array $record, Carbon $date, bool $test): string
    {
        $title = $test ? '*[TEST] PERINGATAN ABSENSI*' : '*PERINGATAN ABSENSI*';

        return implode("\n", [
            $title,
            '',
            'Halo, *'.$record['name'].'*.',
            'Pada absensi tanggal '.$date->format('d/m/Y').' tercatat: *'.$record['finding'].'*.',
            '',
            'Scan masuk: '.$this->scanTime($record['scan_in']),
            'Scan pulang: '.$this->scanTime($record['scan_out']),
            '',
            'Harap segera melapor kepada HRD untuk pengecekan atau koreksi absensi.',

            'Jika Anda sudah melakukan koreksi atau melapor ke HRD, mohon abaikan pesan ini. Terima kasih.',
            'Pesan ini dikirim secara otomatis oleh sistem absensi perusahaan. Jika Anda merasa ada kesalahan, silakan hubungi HRD.',
        ]);
    }

    private function supervisorWarningMessage(Collection $records, Carbon $date, bool $test): string
    {
        $title = $test ? '*[TEST] KONFIRMASI ABSENSI BAWAHAN*' : '*KONFIRMASI ABSENSI BAWAHAN*';
        $items = $records->values()->map(
            fn (array $record, int $index): string => ($index + 1).'. '.$record['name'].' ('.$record['nik'].') - '.$record['finding']
        )->implode("\n");

        return implode("\n", [
            $title,
            '',
            'Pada absensi tanggal '.$date->format('d/m/Y').' terdapat bawahan Anda dengan data scan belum lengkap:',
            $items,
            '',
            'Mohon informasikan kepada karyawan terkait untuk memvalidasi datanya dan konfirmasikan kepada HRD paling lambat hari ini.',
            'Pesan ini dikirim otomatis oleh sistem absensi perusahaan.',
        ]);
    }

    private function finding(array $scans): string
    {
        return $scans['scan_in'] ? 'Tidak scan pulang' : 'Tidak scan masuk';
    }

    private function scanTime(?string $time): string
    {
        return $time ? substr($time, 0, 5).' WIB' : '-';
    }

    private function scanSummary(Collection $logs): array
    {
        $hasStatusCodes = $logs->contains(
            fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['0', '1'], true)
        );

        if ($hasStatusCodes) {
            $scanIn = $logs->first(
                fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '0'
            );
            $scanOut = $logs->reverse()->first(
                fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '1'
            );
        } else {
            $scanIn = $logs->first();
            $scanOut = $logs->count() > 1 ? $logs->last() : null;
        }

        if (! $scanOut && $logs->count() > 1) {
            $lastScan = $logs->last();
            if (! $scanIn || $lastScan->scan_date->gt($scanIn->scan_date)) {
                $scanOut = $lastScan;
            }
        }

        return [
            'scan_in' => $scanIn?->scan_date?->format('H:i:s'),
            'scan_out' => $scanOut?->scan_date?->format('H:i:s'),
        ];
    }
}
