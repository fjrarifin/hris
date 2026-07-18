<?php

namespace App\Mail;

use App\Models\RecruitmentCandidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CandidateReferenceCheckMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;

    public string $referenceLink;

    public string $referencePassword;

    public int $requiredReferenceCount;

    public function __construct(
        RecruitmentCandidate $candidate,
        string $referenceLink,
        string $referencePassword,
        int $requiredReferenceCount
    ) {
        $this->candidate = $candidate;
        $this->referenceLink = $referenceLink;
        $this->referencePassword = $referencePassword;
        $this->requiredReferenceCount = $requiredReferenceCount;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Permintaan Referensi Kerja Rekrutmen - '.$this->candidate->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-reference-check',
            with: [
                'candidate' => $this->candidate,
                'referenceLink' => $this->referenceLink,
                'referencePassword' => $this->referencePassword,
                'requiredReferenceCount' => $this->requiredReferenceCount,
            ]
        );
    }
}
