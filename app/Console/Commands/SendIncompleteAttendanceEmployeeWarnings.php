<?php

namespace App\Console\Commands;

use App\Services\IncompleteAttendanceWhatsAppReport;
use App\Services\PayrollPeriodService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SendIncompleteAttendanceEmployeeWarnings extends Command
{
    protected $signature = 'attendance:send-employee-warnings
        {--date= : Tanggal absensi format Y-m-d, default hari kemarin}
        {--preview : Tampilkan isi peringatan tanpa mengirim WhatsApp}
        {--test : Tambahkan tanda TEST pada peringatan yang dikirim}';

    protected $description = 'Kirim peringatan pribadi kepada karyawan dan atasan langsung terkait scan absensi tidak lengkap.';

    public function handle(IncompleteAttendanceWhatsAppReport $report, PayrollPeriodService $periodService): int
    {
        try {
            $date = $this->reportDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('preview') && ! app()->environment('production')) {
            $this->warn('Pengiriman WhatsApp dilewati karena environment bukan production.');

            return self::SUCCESS;
        }

        if ($this->option('preview')) {
            $notifications = $report->employeeMessagesForDate($date, (bool) $this->option('test'));
            $supervisorNotifications = $report->supervisorMessagesForDate($date, (bool) $this->option('test'));

            foreach ($notifications as $notification) {
                $target = $notification['is_redirected']
                    ? sprintf(
                        'Dialihkan ke %s (%s) / %s',
                        $notification['recipient_name'],
                        $notification['recipient_nik'],
                        $notification['phone']
                    )
                    : $notification['name'].' / '.$notification['phone'];

                $this->line('Tujuan: '.$target);
                $this->line($notification['message']);
                $this->newLine();
            }

            foreach ($supervisorNotifications as $notification) {
                $this->line('Tujuan atasan: '.$notification['recipient_name'].' / '.$notification['phone']);
                $this->line($notification['message']);
                $this->newLine();
            }

            $this->info(sprintf(
                'Preview selesai. %d pesan karyawan dan %d pesan atasan siap dikirim, tidak ada WhatsApp yang dikirim.',
                $notifications->count(),
                $supervisorNotifications->count()
            ));

            return self::SUCCESS;
        }

        $periodAppNotificationCount = $this->storePeriodAppNotifications($report, $periodService, $date);
        $result = $report->sendEmployeeWarningsForDate($date, (bool) $this->option('test'));
        $supervisorResult = $report->sendSupervisorWarningsForDate($date, (bool) $this->option('test'));

        if (! $result['ok'] || ! $supervisorResult['ok']) {
            $this->error($result['reason'] ?? $supervisorResult['reason']);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Peringatan absensi %s berhasil dikirim (%d pesan karyawan, %d pesan atasan). Dilewati tanpa nomor HP karyawan: %d.',
            $date->format('d/m/Y'),
            $result['sent_count'],
            $supervisorResult['sent_count'],
            $result['skipped_count']
        ));

        if ($periodAppNotificationCount > 0 || ($result['app_notification_count'] ?? 0) > 0) {
            $this->info(sprintf(
                'Notifikasi aplikasi tersimpan: %d temuan periode berjalan.',
                $periodAppNotificationCount + ($result['app_notification_count'] ?? 0)
            ));
        }

        return self::SUCCESS;
    }

    private function storePeriodAppNotifications(
        IncompleteAttendanceWhatsAppReport $report,
        PayrollPeriodService $periodService,
        Carbon $untilDate
    ): int {
        if ($this->option('date')) {
            return 0;
        }

        $period = $periodService->periodFor($untilDate->toDateString());
        $count = 0;

        foreach (CarbonPeriod::create($period['start_date'], $untilDate) as $date) {
            $count += $report->storeEmployeeAppNotificationsForDate($date->copy()->startOfDay(), (bool) $this->option('test'));
        }

        return $count;
    }

    private function reportDate(): Carbon
    {
        $value = $this->option('date');

        if (! $value) {
            return now()->subDay()->startOfDay();
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $value)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Tanggal laporan harus menggunakan format Y-m-d.');
        }

        if ($date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Tanggal laporan harus menggunakan format Y-m-d.');
        }

        return $date;
    }
}
