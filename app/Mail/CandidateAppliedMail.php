<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\RecruitmentCandidate;

class CandidateAppliedMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;

    public function __construct(RecruitmentCandidate $candidate)
    {
        $this->candidate = $candidate;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lamaran Diterima - ' . ($this->candidate->vacancy->title ?? 'Hompim Play'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-applied',
            with: [
                'candidate' => $this->candidate,
            ]
        );
    }
}
