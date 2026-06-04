<?php

namespace App\Services;

use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollPdfService
{
    public function generate(Payroll $payroll, string $password): string
    {
        $view = $payroll->karyawan?->bpjs ? 'hr.payroll.pdf_bpjs' : 'hr.payroll.pdf';
        $pdf = Pdf::loadView($view, compact('payroll'))
            ->setPaper('a5', 'landscape');

        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();

        if (method_exists($canvas, 'setEncryption')) {
            $canvas->setEncryption($password, '', ['print', 'copy']);
        } elseif (method_exists($canvas, 'get_cpdf')) {
            $canvas->get_cpdf()->setEncryption($password, '', ['print', 'copy']);
        } elseif (isset($canvas->cpdf)) {
            $canvas->cpdf->setEncryption($password, '', ['print', 'copy']);
        }

        return $dompdf->output();
    }
}
