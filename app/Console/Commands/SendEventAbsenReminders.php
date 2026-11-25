<?php

namespace App\Console\Commands;

use App\Services\EventAbsenWhatsAppService;
use Illuminate\Console\Command;

class SendEventAbsenReminders extends Command
{
    protected $signature = 'event-absen:send-reminders';

    protected $description = 'Kirim pengingat WhatsApp via Nova Agent untuk event absensi yang memasuki waktu reminder.';

    public function handle(EventAbsenWhatsAppService $whatsAppService): int
    {
        $summary = $whatsAppService->sendDueReminders();

        $this->info(sprintf(
            'Event absen reminder selesai: %d event diproses, %d terkirim, %d sudah absen, %d tanpa no HP, %d gagal.',
            $summary['events_processed'],
            $summary['sent'],
            $summary['skipped_attended'],
            $summary['skipped_no_phone'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
