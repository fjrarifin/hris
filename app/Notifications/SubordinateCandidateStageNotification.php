<?php

namespace App\Notifications;

use App\Models\RecruitmentCandidate;
use App\Notifications\Channels\MobilePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubordinateCandidateStageNotification extends Notification
{
    use Queueable;

    protected RecruitmentCandidate $candidate;
    protected string $stage;

    public function __construct(RecruitmentCandidate $candidate, string $stage)
    {
        $this->candidate = $candidate;
        $this->stage = $stage;
    }

    public function via(object $notifiable): array
    {
        return ['database', MobilePushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->buildTitle(),
            'message' => $this->buildMessage(),
            'candidate_id' => $this->candidate->id,
            'stage' => $this->stage,
            'mobile_path' => '/subordinate-candidates',
        ];
    }

    private function buildTitle(): string
    {
        return 'Update Status Kandidat Bawahan';
    }

    private function buildMessage(): string
    {
        $namaKandidat = $this->candidate->name;
        $posisi = $this->candidate->vacancy?->title ?? 'posisi lowongan';

        $statusLabel = match ($this->stage) {
            'applied' => 'telah melamar',
            'screening' => 'masuk ke tahap screening',
            'interview_hr' => 'masuk ke tahap interview HR',
            'case_study' => 'masuk ke tahap case study',
            'interview_user' => 'masuk ke tahap interview user',
            'reference_check' => 'masuk ke tahap reference check',
            'offering' => 'masuk ke tahap offering letter',
            'pkb' => 'masuk ke tahap PKB',
            'hired' => 'telah diterima bekerja (Hired)',
            'rejected' => 'telah ditolak (Rejected)',
            default => 'mengalami perubahan status'
        };

        return "Kandidat calon bawahan Anda, {$namaKandidat} ({$posisi}), saat ini {$statusLabel}.";
    }
}
