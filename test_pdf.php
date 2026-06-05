<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payroll = \App\Models\Payroll::with('items.component')->where('karyawan_nik', 'HPP25120147')->first();

echo "JHT: " . ($payroll->getItemByComponentName('Tunjangan JHT Karyawan', 'employer_contribution')->amount ?? 'NULL') . "\n";
echo "JP: " . ($payroll->getItemByComponentName('Tunjangan JP Karyawan', 'employer_contribution')->amount ?? 'NULL') . "\n";
echo "JKK: " . ($payroll->getItemByComponentName('Tunjangan JKK Karyawan', 'employer_contribution')->amount ?? 'NULL') . "\n";
echo "JKM: " . ($payroll->getItemByComponentName('Tunjangan JKM Karyawan', 'employer_contribution')->amount ?? 'NULL') . "\n";
