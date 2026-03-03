<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class IncompleteProfileNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Lengkapi Data Profil',
            'message' => 'Email atau Nomor HP Anda belum lengkap. Harap segera melengkapi data profil Anda.',
            'url' => route('staff.profile.index'),
        ];
    }
}
