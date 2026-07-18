<?php

namespace App\Mail;

use App\Models\RecruitmentCandidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferingLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;

    public ?string $signatureLink;

    public ?string $offeringPassword;

    public function __construct(
        RecruitmentCandidate $candidate,
        ?string $signatureLink = null,
        ?string $offeringPassword = null
    ) {
        $this->candidate = $candidate;
        $this->signatureLink = $signatureLink;
        $this->offeringPassword = $offeringPassword;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Surat Penawaran Kerja (Offering Letter) - '.$this->candidate->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-offering',
            with: [
                'candidate' => $this->candidate,
                'signatureLink' => $this->signatureLink,
                'offeringPassword' => $this->offeringPassword,
            ]
        );
    }

    public function attachments(): array
    {
        if ($this->candidate->offering_letter_path) {
            return [
                Attachment::fromStorageDisk('local', $this->candidate->offering_letter_path)
                    ->as('Offering-Letter-'.str_replace(' ', '-', $this->candidate->name).'.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
