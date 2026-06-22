<?php

namespace App\Notifications;

use App\Notifications\Channels\MobilePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BirthdayGreetingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $employeeName,
        private readonly string $date
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', MobilePushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Selamat Ulang Tahun',
            'message' => "Selamat ulang tahun, {$this->employeeName}! Semoga hari Anda dipenuhi kebahagiaan, kesehatan, dan hal-hal baik sepanjang tahun ini.",
            'type' => 'birthday_greeting',
            'date' => $this->date,
            'path' => '/staff/dashboard',
            'mobile_path' => '/tabs/home',
        ];
    }
}
