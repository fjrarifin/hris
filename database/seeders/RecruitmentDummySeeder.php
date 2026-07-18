<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Alias kompatibilitas untuk perintah seeder recruitment yang lama.
 */
class RecruitmentDummySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RecruitmentDashboardSeeder::class);
    }
}
