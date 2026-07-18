<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\RecruitmentCandidate;

class CandidateOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;
    public string $onboardingLink;
    public string $password;

    public function __construct(RecruitmentCandidate $candidate, string $onboardingLink, string $password)
    {
        $this->candidate = $candidate;
        $this->onboardingLink = $onboardingLink;
        $this->password = $password;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Selamat Datang Karyawan Baru - ' . $this->candidate->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-onboarding',
            with: [
                'candidate' => $this->candidate,
                'onboardingLink' => $this->onboardingLink,
                'password' => $this->password,
            ]
        );
    }
}
