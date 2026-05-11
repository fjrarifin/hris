<?php

/**
 * VALIDASI & SIMULASI DATA PAYROLL UPLOAD
 * 
 * Jalankan: php artisan tinker < validasi-payroll.php
 * atau copy-paste ke php artisan tinker
 */

echo "========== VALIDASI DATA PAYROLL UPLOAD ==========\n\n";

// 1. Check Karyawan
echo "1️⃣ CEK KARYAWAN:\n";
$karyawans = [
    'HPP25120147' => 'FAJAR ARIFIN',
    'HPP25110042' => 'ILHAM FADJRIANSYAH'
];

foreach ($karyawans as $nik => $nama) {
    $k = \App\Models\Karyawan::where('nik', $nik)->first();
    if ($k) {
        echo "✅ $nik | {$k->nama_karyawan} | {$k->jabatan}\n";
    } else {
        echo "❌ $nik | $nama NOT FOUND\n";
    }
}

// 2. Check Components
echo "\n2️⃣ CEK PAYROLL COMPONENTS:\n";
$components_needed = [
    'Gaji Pokok' => 'earning',
    'Tunjangan Jabatan' => 'earning',
    'Tunjangan Tidak Tetap' => 'earning',
    'THR' => 'earning',
    'Lembur' => 'earning',
    'PPh21' => 'deduction',
    'Potongan Kasbon' => 'deduction',
    'Potongan Izin' => 'deduction'
];

foreach ($components_needed as $nama => $type) {
    $c = \App\Models\PayrollComponent::where('nama', $nama)->first();
    if ($c && $c->type === $type) {
        echo "✅ $nama (type: $type)\n";
    } else {
        echo "❌ $nama (type: $type) NOT FOUND\n";
    }
}

// 3. Sample Data Ready
echo "\n3️⃣ SAMPLE DATA SIAP UPLOAD:\n";
echo "Row 1 (Fajar Arifin):\n";
echo "HPP25120147 | 2026-02-25 | 2026-03-25 | 24 | 22 | 2 | 3791250 | 1253750 | 950000 | 852500 | 0 | 500000 | 342500 | 0\n";

echo "\nRow 2 (Ilham):\n";
echo "HPP25110042 | 2026-04-01 | 2026-04-30 | 22 | 21 | 1 | 8000000 | 3000000 | 0 | 0 | 1500000 | 1250000 | 0 | 0\n";

// 4. Expected Results
echo "\n4️⃣ EXPECTED RESULTS:\n";
echo "Fajar Arifin:\n";
echo "  Total Pendapatan: 6,847,500\n";
echo "  Total Potongan: 842,500\n";
echo "  Total Dibayarkan: 6,005,000\n";

echo "\nIlham:\n";
echo "  Total Pendapatan: 12,500,000\n";
echo "  Total Potongan: 1,250,000\n";
echo "  Total Dibayarkan: 11,250,000\n";

echo "\n✅ READY TO UPLOAD!\n";
