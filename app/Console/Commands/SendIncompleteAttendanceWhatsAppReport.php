<?php

namespace App\Console\Commands;

use App\Services\IncompleteAttendanceWhatsAppReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SendIncompleteAttendanceWhatsAppReport extends Command
{
    protected $signature = 'attendance:send-incomplete-report
        {--date= : Tanggal absensi format Y-m-d, default hari kemarin}
        {--preview : Tampilkan isi laporan tanpa mengirim WhatsApp}
        {--test : Tambahkan tanda TEST pada laporan yang dikirim}';

    protected $description = 'Kirim laporan scan masuk/pulang tidak lengkap ke grup WhatsApp attendance.';

    public function handle(IncompleteAttendanceWhatsAppReport $report): int
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
            foreach ($report->messagesForDate($date, (bool) $this->option('test')) as $message) {
                $this->line($message);
                $this->newLine();
            }

            $this->info('Preview selesai. Tidak ada pesan WhatsApp yang dikirim.');

            return self::SUCCESS;
        }

        $result = $report->sendForDate($date, (bool) $this->option('test'));

        if (! $result['ok']) {
            $this->error($result['reason']);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Laporan absensi %s berhasil dikirim ke grup attendance (%d pesan).',
            $date->format('d/m/Y'),
            count($result['messages'])
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
