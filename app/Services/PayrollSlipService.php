<?php

namespace App\Services;

use App\Mail\SlipGajiMail;
use App\Models\Payroll;
use App\Models\PayrollEmailLog;
use App\Models\PayrollEmailTemplate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PayrollSlipService
{
    public function __construct(
        private readonly PayrollPdfService $pdfService,
        private readonly PayrollValidationService $validationService
    ) {
    }

    public function pdf(Payroll $payroll): array
    {
        $payroll->loadMissing(['karyawan', 'items.component']);
        $password = $this->password($payroll);

        return [
            'content' => $this->pdfService->generate($payroll, $password),
            'file_name' => $this->fileName($payroll),
        ];
    }

    public function send(Payroll $payroll, ?int $userId = null): PayrollEmailLog
    {
        $payroll->loadMissing(['karyawan', 'items.component']);
        $validation = $this->validationService->validateAndStore($payroll, $userId, true);
        if (! $validation['can_send']) {
            throw ValidationException::withMessages(['payroll' => $validation['critical']]);
        }

        $unlockedApprovedCount = Payroll::query()
            ->whereDate('periode_start', $payroll->periode_start)
            ->whereDate('periode_end', $payroll->periode_end)
            ->where('approval_status', 'approved')
            ->where('is_locked', false)
            ->count();

        if ($unlockedApprovedCount > 0) {
            throw ValidationException::withMessages([
                'payroll' => 'Masih ada payroll approved yang belum dikunci. Kunci seluruh payroll approved sebelum kirim slip gaji.',
            ]);
        }

        $template = PayrollEmailTemplate::slipGaji();
        $pdf = $this->pdf($payroll);
        $password = $this->password($payroll);
        $subject = $template->renderSubject($payroll);
        $body = $template->renderBody($payroll);

        Mail::to($payroll->karyawan->email)->send(
            new SlipGajiMail($payroll, $pdf['content'], $pdf['file_name'], $password, $subject, $body)
        );

        $log = PayrollEmailLog::create([
            'payroll_id' => $payroll->id,
            'karyawan_nik' => $payroll->karyawan_nik,
            'recipient_email' => $payroll->karyawan->email,
            'subject' => $subject,
            'action' => 'send',
            'status' => 'sent',
            'attempt_no' => PayrollEmailLog::query()->where('payroll_id', $payroll->id)->count() + 1,
            'created_by' => $userId,
            'sent_at' => now(),
            'notes' => 'Email slip gaji berhasil dikirim melalui flow Vue.',
            'payload' => ['file_name' => $pdf['file_name'], 'body_preview' => $body],
        ]);

        $payroll->forceFill(['approval_status' => 'sent'])->save();

        return $log;
    }

    private function password(Payroll $payroll): string
    {
        return $payroll->karyawan?->tanggal_lahir
            ? $payroll->karyawan->tanggal_lahir->format('dmy')
            : ($payroll->karyawan_nik ?: '123456');
    }

    private function fileName(Payroll $payroll): string
    {
        $name = str_replace(' ', '_', $payroll->karyawan?->nama_karyawan ?? $payroll->karyawan_nik);

        return "Slip_Gaji_{$name}_{$payroll->periode_start->format('M_Y')}.pdf";
    }
}
