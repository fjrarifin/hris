<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidateUserInterview;
use Carbon\Carbon;

class CandidateUserInterviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;
    public RecruitmentCandidateUserInterview $userInterview;

    public function __construct(RecruitmentCandidate $candidate, RecruitmentCandidateUserInterview $userInterview)
    {
        $this->candidate = $candidate;
        $this->userInterview = $userInterview;
    }

    public function envelope(): Envelope
    {
        $tahap = $this->userInterview->round;
        return new Envelope(
            subject: "Undangan Wawancara User (Tahap {$tahap}) - {$this->candidate->name}",
        );
    }

    public function content(): Content
    {
        $formattedDate = $this->userInterview->interview_date;
        try {
            $formattedDate = Carbon::parse($this->userInterview->interview_date)->locale('id')->translatedFormat('l, d F Y');
        } catch (\Exception $e) {}

        $time = substr($this->userInterview->interview_time, 0, 5);
        $type = $this->userInterview->interview_type;
        $meetLink = $this->userInterview->interview_meet_link;
        $location = $this->userInterview->interview_location;

        $this->candidate->loadMissing('pic');
        $whatsappLink = '';
        if ($this->candidate->pic && $this->candidate->pic->no_hp) {
            $clean = preg_replace('/\D/', '', $this->candidate->pic->no_hp);
            if (str_starts_with($clean, '0')) {
                $clean = '62' . substr($clean, 1);
            }
            
            $locationLabel = $type === 'online' ? ($meetLink ?? '-') : ($location ?? '-');
            $waMessage = "Dear Tim Rekrutmen Hompimplay, pada tanggal {$formattedDate} jam {$time} WIB lokasi/tautan {$locationLabel}, saya {$this->candidate->name} *Bersedia / Tidak Bersedia* hadir untuk memenuhi undangan wawancara User Tahap {$this->userInterview->round}, Terima kasih.";
            
            $whatsappLink = 'https://wa.me/' . $clean . '?text=' . rawurlencode($waMessage);
        }

        return new Content(
            view: 'emails.candidate-user-interview',
            with: [
                'candidate' => $this->candidate,
                'userInterview' => $this->userInterview,
                'formattedDate' => $formattedDate,
                'time' => $time,
                'type' => $type,
                'meetLink' => $meetLink,
                'location' => $location,
                'whatsappLink' => $whatsappLink,
            ]
        );
    }
}
