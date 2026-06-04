<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run()
    {
        $this->call(PublicHolidaysSeeder::class);
        $this->call(CashierSupervisorHierarchySeeder::class);
        $this->call(CommonTalentJobdeskKpiSeeder::class);
        $this->call(June2026PerformanceReviewSeeder::class);
    }
}
