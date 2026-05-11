<?php

namespace App\Jobs;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\SlipGajiMail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\PayrollPdfService;

class SendSlipGajiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payroll;

    public function __construct(Payroll $payroll)
    {
        $this->payroll = $payroll;
    }

    public function handle(PayrollPdfService $pdfService)
    {
        $payroll = Payroll::with(['karyawan', 'items.component'])
            ->find($this->payroll->id);

        if (!$payroll || !$payroll->karyawan?->email) return;

        $tglLahir = $payroll->karyawan->tanggal_lahir ?? null;
        $password = $tglLahir
            ? \Carbon\Carbon::parse($tglLahir)->format('dmy')
            : $payroll->karyawan->nik;

        $pdfContent = $pdfService->generate($payroll, $password);

        Mail::to($payroll->karyawan->email)->send(
            new SlipGajiMail(
                $payroll,
                $pdfContent,
                'Slip_Gaji.pdf',
                $password
            )
        );
    }
}