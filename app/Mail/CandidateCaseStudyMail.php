<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\RecruitmentCandidate;

class CandidateCaseStudyMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;
    public ?string $caseStudyLink;
    public ?string $documentName;
    public ?string $uploadLink;
    public ?string $pin;

    public function __construct(
        RecruitmentCandidate $candidate,
        ?string $caseStudyLink = null,
        ?string $documentName = null,
        ?string $uploadLink = null,
        ?string $pin = null
    ) {
        $this->candidate = $candidate;
        $this->caseStudyLink = $caseStudyLink;
        $this->documentName = $documentName;
        $this->uploadLink = $uploadLink;
        $this->pin = $pin;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Instruksi Case Study Rekrutmen - ' . $this->candidate->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-case-study',
            with: [
                'candidate' => $this->candidate,
                'caseStudyLink' => $this->caseStudyLink,
                'documentName' => $this->documentName,
                'uploadLink' => $this->uploadLink,
                'pin' => $this->pin,
            ]
        );
    }

    public function attachments(): array
    {
        if ($this->candidate->case_study_document_path) {
            return [
                \Illuminate\Mail\Mailables\Attachment::fromStorageDisk('local', $this->candidate->case_study_document_path)
                    ->as($this->documentName ?? 'soal-case-study.pdf')
            ];
        }
        return [];
    }
}
