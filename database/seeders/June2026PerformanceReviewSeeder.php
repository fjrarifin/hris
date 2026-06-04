<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Services\PerformanceManagementService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

class June2026PerformanceReviewSeeder extends Seeder
{
    private array $jabatanNames = [
        'SPV IT',
        'Staff IT',
        'Graphic Designer',
        'Cleaner',
        'Teknisi MEP',
        'Cashier',
        'Staff Customer Service',
    ];

    public function run(PerformanceManagementService $service): void
    {
        $period = PerformancePeriod::query()->updateOrCreate(
            ['nama_periode' => 'Review Kinerja Juni 2026'],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'status' => 'active',
            ]
        );

        $created = 0;
        $skipped = 0;

        Karyawan::query()
            ->whereIn('jabatan', $this->jabatanNames)
            ->whereNotNull('nik')
            ->where('nik', '<>', '')
            ->orderBy('nik')
            ->get()
            ->each(function (Karyawan $employee) use ($period, $service, &$created, &$skipped): void {
                if (PerformanceReview::query()
                    ->where('employee_nik', $employee->nik)
                    ->where('performance_period_id', $period->id)
                    ->exists()) {
                    $skipped++;

                    return;
                }

                try {
                    $service->generateReview($period, $employee);
                    $created++;
                } catch (ValidationException $exception) {
                    $skipped++;
                    $this->command?->warn("Review {$employee->nik} dilewati: {$exception->getMessage()}");
                }
            });

        $this->command?->info("Periode '{$period->nama_periode}' siap. Review baru: {$created}, dilewati: {$skipped}.");
    }
}
