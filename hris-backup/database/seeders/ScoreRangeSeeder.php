<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScoreRangeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('m_score_range')->truncate();

        DB::table('m_score_range')->insert([
            [
                'min_score' => 1.00,
                'max_score' => 1.86,
                'label' => 'Sangat Kurang',
                'score_final' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'min_score' => 1.87,
                'max_score' => 2.86,
                'label' => 'Kurang',
                'score_final' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'min_score' => 2.87,
                'max_score' => 3.70,
                'label' => 'Cukup',
                'score_final' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'min_score' => 3.71,
                'max_score' => 4.70,
                'label' => 'Baik',
                'score_final' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'min_score' => 4.71,
                'max_score' => 5.00,
                'label' => 'Sangat Baik',
                'score_final' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
