<?php

namespace App\Notifications;

use App\Models\HrdAuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HrdDataChangedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly HrdAuditLog $log) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Perubahan Data HRD',
            'message' => "{$this->log->module}: {$this->actionLabel()} {$this->log->subject_label}",
            'audit_log_id' => $this->log->id,
            'module' => $this->log->module,
            'action' => $this->log->action,
            'subject_label' => $this->log->subject_label,
            'actor_name' => $this->log->actor_name,
            'occurred_at' => $this->log->occurred_at?->toIso8601String(),
        ];
    }

    private function actionLabel(): string
    {
        return match ($this->log->action) {
            'created' => 'menambahkan',
            'deleted' => 'menghapus',
            default => 'mengubah',
        };
    }
}
