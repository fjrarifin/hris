<?php

namespace App\Console\Commands;

use App\Models\CommandServiceToggle;
use App\Services\IncompleteAttendanceWhatsAppReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SendIncompleteAttendanceWhatsAppReport extends Command
{
    protected $signature = 'attendance:send-incomplete-report
        {--date= : Tanggal absensi format Y-m-d, default hari kemarin}
        {--preview : Tampilkan isi laporan tanpa mengirim push}
        {--test : Preview mode untuk menandai laporan sebagai TEST}';

    protected $description = 'Proses notifikasi aplikasi/push Android untuk scan masuk/pulang tidak lengkap.';

    public function handle(IncompleteAttendanceWhatsAppReport $report): int
    {
        try {
            $date = $this->reportDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! CommandServiceToggle::isEnabled('attendance:send-incomplete-report')) {
            $this->warn('Layanan laporan absensi tidak lengkap dinonaktifkan. Perintah dihentikan.');

            return self::SUCCESS;
        }

        if (! $this->option('preview') && ! app()->environment('production')) {
            $this->warn('Pengiriman push notification dilewati karena environment bukan production.');

            return self::SUCCESS;
        }

        if ($this->option('preview')) {
            foreach ($report->messagesForDate($date, (bool) $this->option('test')) as $message) {
                $this->line($message);
                $this->newLine();
            }

            $this->info('Preview selesai. Tidak ada push notification yang dikirim.');

            return self::SUCCESS;
        }

        $notificationCount = $report->storeEmployeeAppNotificationsForDate($date, false);

        $this->info(sprintf(
            'Notifikasi absensi tidak lengkap %s berhasil diproses (%d notifikasi aplikasi/push baru).',
            $date->format('d/m/Y'),
            $notificationCount
        ));

        return self::SUCCESS;
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
