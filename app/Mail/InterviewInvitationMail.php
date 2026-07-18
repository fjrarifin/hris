<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\RecruitmentCandidate;
use Carbon\Carbon;

class InterviewInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;
    public bool $isHr;

    public function __construct(RecruitmentCandidate $candidate, bool $isHr = false)
    {
        $this->candidate = $candidate;
        $this->isHr = $isHr;
    }

    public function envelope(): Envelope
    {
        $isReschedule = $this->isHr && !empty($this->candidate->interview_hr_prev_date);
        $subject = $this->isHr 
            ? ($isReschedule ? 'Revisi Undangan Wawancara HR' : 'Undangan Wawancara HR') 
            : 'Undangan Interview Rekrutmen';
        return new Envelope(
            subject: $subject . ' - ' . $this->candidate->name,
        );
    }

    public function content(): Content
    {
        $date = $this->isHr ? $this->candidate->interview_hr_date : $this->candidate->interview_date;
        $time = $this->isHr ? $this->candidate->interview_hr_time : $this->candidate->interview_time;
        $type = $this->isHr ? $this->candidate->interview_hr_type : $this->candidate->interview_type;
        $meetLink = $this->isHr ? $this->candidate->interview_hr_meet_link : $this->candidate->interview_meet_link;
        $location = $this->isHr ? $this->candidate->interview_hr_location : $this->candidate->interview_location;

        $formattedDate = $date;
        try {
            $formattedDate = Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y');
        } catch (\Exception $e) {}

        $this->candidate->loadMissing('pic');
        $whatsappLink = '';
        if ($this->candidate->pic && $this->candidate->pic->no_hp) {
            $clean = preg_replace('/\D/', '', $this->candidate->pic->no_hp);
            if (str_starts_with($clean, '0')) {
                $clean = '62' . substr($clean, 1);
            }
            $locationLabel = $type === 'online' ? ($meetLink ?? '-') : ($location ?? '-');
            $timeFormatted = substr($time, 0, 5);
            $waMessage = "Dear Tim Rekrutmen Hompimplay, pada tanggal {$formattedDate} jam {$timeFormatted} WIB lokasi/tautan {$locationLabel}, saya {$this->candidate->name} *Bersedia / Tidak Bersedia* hadir untuk memenuhi undangan interview HR, Terima kasih.";
            
            $whatsappLink = 'https://wa.me/' . $clean . '?text=' . rawurlencode($waMessage);
        }

        $isReschedule = false;
        $prevFormattedDate = '';
        $prevTime = '';

        if ($this->isHr && !empty($this->candidate->interview_hr_prev_date)) {
            $isReschedule = true;
            try {
                $prevFormattedDate = Carbon::parse($this->candidate->interview_hr_prev_date)->locale('id')->translatedFormat('l, d F Y');
            } catch (\Exception $e) {}
            $prevTime = substr($this->candidate->interview_hr_prev_time, 0, 5);
        }

        return new Content(
            view: 'emails.candidate-interview',
            with: [
                'candidate' => $this->candidate,
                'formattedDate' => $formattedDate,
                'time' => substr($time, 0, 5),
                'type' => $type,
                'meetLink' => $meetLink,
                'location' => $location,
                'whatsappLink' => $whatsappLink,
                'isReschedule' => $isReschedule,
                'prevFormattedDate' => $prevFormattedDate,
                'prevTime' => $prevTime,
            ]
        );
    }
}
