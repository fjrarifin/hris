<?php

namespace App\Jobs;

use App\Models\EventAbsen;
use App\Services\EventAbsenWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEventAbsenInitialWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, string>|null  $onlyNiks
     */
    public function __construct(
        public int $eventAbsenId,
        public ?array $onlyNiks = null,
    ) {}

    public function handle(EventAbsenWhatsAppService $whatsAppService): void
    {
        $event = EventAbsen::find($this->eventAbsenId);
        if (! $event || $event->status !== 'aktif') {
            return;
        }

        $whatsAppService->sendInitialInvitations($event, $this->onlyNiks);
    }
}
