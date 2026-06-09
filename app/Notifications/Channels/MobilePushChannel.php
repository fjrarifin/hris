<?php

namespace App\Notifications\Channels;

use App\Services\FirebasePushService;
use Illuminate\Notifications\Notification;

class MobilePushChannel
{
    public function __construct(private readonly FirebasePushService $pushService) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notifiable, 'getKey')) {
            return;
        }

        $data = method_exists($notification, 'toDatabase')
            ? $notification->toDatabase($notifiable)
            : (method_exists($notification, 'toArray') ? $notification->toArray($notifiable) : []);

        $this->pushService->sendToUser((int) $notifiable->getKey(), $data['title'] ?? 'Notifikasi HRIS', $data['message'] ?? '', [
            ...$data,
            'mobile_path' => $data['mobile_path'] ?? '/notifications',
        ]);
    }
}
