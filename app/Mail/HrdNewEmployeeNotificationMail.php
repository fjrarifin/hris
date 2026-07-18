<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\RecruitmentCandidate;
use App\Models\Karyawan;

class HrdNewEmployeeNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitmentCandidate $candidate;
    public Karyawan $employee;

    public function __construct(RecruitmentCandidate $candidate, Karyawan $employee)
    {
        $this->candidate = $candidate;
        $this->employee = $employee;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notifikasi Karyawan Baru Selesai Onboarding - ' . $this->employee->nama_karyawan,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hrd-new-employee',
            with: [
                'candidate' => $this->candidate,
                'employee' => $this->employee,
            ]
        );
    }
}
