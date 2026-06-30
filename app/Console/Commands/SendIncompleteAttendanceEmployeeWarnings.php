<?php

namespace App\Console\Commands;

use App\Models\CommandServiceToggle;
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
        {--preview : Tampilkan calon notifikasi tanpa mengirim push}
        {--test : Preview mode untuk menandai notifikasi sebagai TEST}';

    protected $description = 'Kirim notifikasi push Android kepada karyawan terkait scan absensi tidak lengkap (bukan WhatsApp).';

    public function handle(IncompleteAttendanceWhatsAppReport $report, PayrollPeriodService $periodService): int
    {
        try {
            $date = $this->reportDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! CommandServiceToggle::isEnabled('attendance:send-employee-warnings')) {
            $this->warn('Layanan pengiriman peringatan absensi dinonaktifkan. Perintah dihentikan.');

            return self::SUCCESS;
        }

        if (! $this->option('preview') && ! app()->environment('production')) {
            $this->warn('Pengiriman push notification dilewati karena environment bukan production.');

            return self::SUCCESS;
        }

        if ($this->option('preview')) {
            $notifications = $report->employeeAppNotificationRecordsForDate($date);

            foreach ($notifications as $notification) {
                $this->line('Tujuan: '.$notification['name'].' / '.$notification['nik']);
                $this->line('Temuan: '.$notification['finding']);
                $this->line('Scan masuk: '.($notification['scan_in'] ? substr($notification['scan_in'], 0, 5).' WIB' : '-'));
                $this->line('Scan pulang: '.($notification['scan_out'] ? substr($notification['scan_out'], 0, 5).' WIB' : '-'));
                $this->newLine();
            }

            $this->info(sprintf(
                'Preview selesai. %d notifikasi karyawan siap dikirim melalui push Android, tidak ada notifikasi yang dikirim saat preview.',
                $notifications->count()
            ));

            return self::SUCCESS;
        }

        $appNotificationCount = $this->storeAppNotifications($report, $periodService, $date);

        $this->info(sprintf(
            'Notifikasi absensi tidak lengkap sampai %s berhasil diproses (%d notifikasi aplikasi/push baru).',
            $date->format('d/m/Y'),
            $appNotificationCount
        ));

        return self::SUCCESS;
    }

    private function storeAppNotifications(
        IncompleteAttendanceWhatsAppReport $report,
        PayrollPeriodService $periodService,
        Carbon $untilDate
    ): int {
        if ($this->option('date')) {
            return $report->storeEmployeeAppNotificationsForDate($untilDate, false);
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
