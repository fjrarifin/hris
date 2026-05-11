<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\Payroll;
use Carbon\Carbon;

class SlipGajiMail extends Mailable
{
    use Queueable, SerializesModels;

    public Payroll $payroll;
    public string  $pdfContent;
    public string  $fileName;
    public string  $password;

    public function __construct(Payroll $payroll, string $pdfContent, string $fileName, string $password)
    {
        $this->payroll    = $payroll;
        $this->pdfContent = $pdfContent;
        $this->fileName   = $fileName;
        $this->password   = $password;
    }

    public function envelope(): Envelope
    {
        $periode = Carbon::parse($this->payroll->periode_start)->translatedFormat('F Y');

        return new Envelope(
            subject: 'Slip Gaji ' . $periode . ' - ' . $this->payroll->karyawan->nama_karyawan,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.slip-gaji',
            with: [
                'payroll'  => $this->payroll,
                'password' => $this->password,
            ]
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn() => $this->pdfContent,
                $this->fileName
            )->withMime('application/pdf'),
        ];
    }
}
