<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEmailTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function slipGaji(): self
    {
        return self::firstOrCreate(
            ['key' => 'slip_gaji'],
            [
                'name' => 'Slip Gaji',
                'subject' => 'Slip Gaji {periode} - {nama_karyawan}',
                'body' => "Yth. {nama_karyawan},\n\nBerikut kami sampaikan slip gaji periode {periode}.\n\nPassword PDF menggunakan tanggal lahir format ddmmyy. Jika tanggal lahir belum tersedia, gunakan NIK.\n\nTerima kasih.",
                'is_active' => true,
            ]
        );
    }

    public function renderSubject(Payroll $payroll): string
    {
        return $this->replacePlaceholders($this->subject, $payroll);
    }

    public function renderBody(Payroll $payroll): string
    {
        return $this->replacePlaceholders($this->body, $payroll);
    }

    private function replacePlaceholders(string $text, Payroll $payroll): string
    {
        $periode = optional($payroll->periode_start)->format('d M Y');

        if ($payroll->periode_start && $payroll->periode_end) {
            $periode = $payroll->periode_start->format('d M Y') . ' - ' . $payroll->periode_end->format('d M Y');
        }

        return strtr($text, [
            '{nama_karyawan}' => $payroll->karyawan?->nama_karyawan ?? '-',
            '{nik}' => $payroll->karyawan_nik ?? '-',
            '{periode}' => $periode ?? '-',
            '{total_dibayarkan}' => 'Rp ' . number_format((int) $payroll->total_dibayarkan, 0, ',', '.'),
        ]);
    }
}
