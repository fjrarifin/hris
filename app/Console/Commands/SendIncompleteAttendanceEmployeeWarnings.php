<?php

namespace App\Console\Commands;

use App\Services\IncompleteAttendanceWhatsAppReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SendIncompleteAttendanceEmployeeWarnings extends Command
{
    protected $signature = 'attendance:send-employee-warnings
        {--date= : Tanggal absensi format Y-m-d, default hari kemarin}
        {--preview : Tampilkan isi peringatan tanpa mengirim WhatsApp}
        {--test : Tambahkan tanda TEST pada peringatan yang dikirim}';

    protected $description = 'Kirim peringatan pribadi kepada karyawan yang scan absensinya tidak lengkap.';

    public function handle(IncompleteAttendanceWhatsAppReport $report): int
    {
        try {
            $date = $this->reportDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('preview')) {
            $notifications = $report->employeeMessagesForDate($date, (bool) $this->option('test'));

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

            $this->info(sprintf(
                'Preview selesai. %d pesan siap dikirim, tidak ada WhatsApp yang dikirim.',
                $notifications->count()
            ));

            return self::SUCCESS;
        }

        $result = $report->sendEmployeeWarningsForDate($date, (bool) $this->option('test'));

        if (! $result['ok']) {
            $this->error($result['reason']);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Peringatan absensi %s berhasil dikirim (%d pesan). Dilewati tanpa nomor HP: %d.',
            $date->format('d/m/Y'),
            $result['sent_count'],
            $result['skipped_count']
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
