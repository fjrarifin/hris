<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ItBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $broadcastId,
        private readonly string $title,
        private readonly string $message,
        private readonly string $mobilePath = '/notifications'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'it_broadcast',
            'broadcast_id' => $this->broadcastId,
            'mobile_path' => $this->mobilePath,
        ];
    }
}
