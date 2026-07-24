<?php

namespace App\Services;

use App\Models\Payroll;

class PayrollValidationService
{
    private const REQUIRED_COMPONENTS = [
        'Gaji Pokok',
    ];

    private const WARNING_TOTAL_LIMIT = 50000000;

    public function validate(Payroll $payroll, bool $forSending = false, bool $requireLock = true): array
    {
        $payroll->loadMissing(['karyawan', 'items.component']);

        $critical = [];
        $warnings = [];

        if (!$payroll->karyawan) {
            $critical[] = 'Data karyawan tidak ditemukan.';
        }

        if ($forSending && !$payroll->karyawan?->email) {
            $critical[] = 'Email karyawan belum tersedia.';
        }

        if ($forSending && $payroll->approval_status !== 'approved') {
            $critical[] = 'Payroll belum disetujui.';
        }

        if ($forSending && $requireLock && !$payroll->is_locked) {
            $critical[] = 'Payroll belum dikunci.';
        }

        if (!$payroll->periode_start || !$payroll->periode_end) {
            $critical[] = 'Periode payroll belum lengkap.';
        }

        if (! $payroll->formula_version) {
            $warnings[] = 'Formula payroll belum memiliki versi snapshot.';
        }

        if ((int) $payroll->basic_salary <= 0) {
            $warnings[] = 'Basic salary belum tersedia.';
        }

        if ((int) $payroll->bruto_man_power <= 0) {
            $critical[] = 'Bruto man power belum tersedia.';
        }

        if ((int) $payroll->total_hari_masuk === 0) {
            $warnings[] = 'Total hari masuk bernilai nol.';
        }

        if ((int) $payroll->total_dibayarkan < 0) {
            $critical[] = 'Total dibayarkan bernilai minus.';
        }

        if ((int) $payroll->total_pendapatan < 0) {
            $critical[] = 'Total pendapatan bernilai minus.';
        }

        if ((int) $payroll->total_potongan < 0) {
            $critical[] = 'Total potongan bernilai minus.';
        }

        if ((int) $payroll->total_dibayarkan === 0) {
            $warnings[] = 'Total dibayarkan masih kosong atau nol.';
        }

        if ((int) $payroll->total_pendapatan === 0) {
            $warnings[] = 'Total pendapatan masih kosong atau nol.';
        }

        if ((int) $payroll->total_dibayarkan > self::WARNING_TOTAL_LIMIT) {
            $warnings[] = 'Total dibayarkan terlihat tidak normal, lebih dari Rp ' . number_format(self::WARNING_TOTAL_LIMIT, 0, ',', '.') . '.';
        }

        if (!$payroll->karyawan?->bank || !$payroll->karyawan?->no_rekening) {
            $warnings[] = 'Data bank atau nomor rekening karyawan belum lengkap.';
        }

        foreach (self::REQUIRED_COMPONENTS as $componentName) {
            $item = $payroll->getItemByComponentName($componentName);

            if (!$item) {
                $warnings[] = "Komponen {$componentName} belum ada.";
                continue;
            }

            if ((int) $item->amount === 0) {
                $warnings[] = "Komponen {$componentName} bernilai nol.";
            }
        }

        $tttItem = $payroll->getItemByComponentName('Tunjangan Tidak Tetap');
        if (! $tttItem || (int) $tttItem->amount <= 0) {
            $warnings[] = 'Komponen Tunjangan Tidak Tetap belum diisi pada Adjustment Payroll.';
        }

        foreach ($payroll->items as $item) {
            $name = $item->component?->nama ?? $item->nama_item ?? 'Komponen payroll';

            if ($item->amount === null) {
                $warnings[] = "{$name} kosong.";
                continue;
            }

            if ((int) $item->amount < 0) {
                $critical[] = "{$name} bernilai minus.";
            }

            if ((int) $item->amount > self::WARNING_TOTAL_LIMIT) {
                $warnings[] = "{$name} terlihat tidak normal, lebih dari Rp " . number_format(self::WARNING_TOTAL_LIMIT, 0, ',', '.') . '.';
            }
        }

        $status = empty($critical)
            ? (empty($warnings) ? 'valid' : 'warning')
            : 'invalid';

        return [
            'status' => $status,
            'critical' => array_values(array_unique($critical)),
            'warnings' => array_values(array_unique($warnings)),
            'can_send' => empty($critical),
        ];
    }

    public function validateAndStore(Payroll $payroll, ?int $userId = null, bool $forSending = false, bool $requireLock = true): array
    {
        $result = $this->validate($payroll, $forSending, $requireLock);

        $payroll->forceFill([
            'validation_status' => $result['status'],
            'validation_warnings' => [
                'critical' => $result['critical'],
                'warnings' => $result['warnings'],
            ],
            'validated_by' => $userId,
            'validated_at' => now(),
        ])->save();

        return $result;
    }
}
