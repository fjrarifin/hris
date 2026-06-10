<?php

namespace App\Notifications\Channels;

use App\Services\FirebasePushService;
use Illuminate\Support\Facades\Log;
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

        try {
            $sent = $this->pushService->sendToUser((int) $notifiable->getKey(), $data['title'] ?? 'Notifikasi HRIS', $data['message'] ?? '', [
                ...$data,
                'mobile_path' => $data['mobile_path'] ?? '/notifications',
            ]);

            if ($sent > 0 && method_exists($notifiable, 'notifications')) {
                $this->markPushSent($notifiable, $notification, $data);
            }
        } catch (\Throwable $e) {
            Log::error('Mobile push notification failed', [
                'notifiable_id' => $notifiable->getKey(),
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markPushSent(object $notifiable, Notification $notification, array $data): void
    {
        $query = $notifiable->notifications()
            ->where('type', $notification::class)
            ->latest();

        foreach (['type', 'date'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $query->where('data->'.$key, (string) $data[$key]);
            }
        }

        $databaseNotification = $query->first();

        if (! $databaseNotification) {
            return;
        }

        $databaseNotification->forceFill([
            'data' => [
                ...$databaseNotification->data,
                'mobile_push_sent_at' => now()->toIso8601String(),
            ],
        ])->save();
    }
}
