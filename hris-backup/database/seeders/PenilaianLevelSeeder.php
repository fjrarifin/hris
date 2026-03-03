<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PenilaianLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            // GM = 6 indikator
            ['nama_level' => 'GM', 'indikator_total' => 6],

            // Manager = 5 indikator
            ['nama_level' => 'Sr. Manager', 'indikator_total' => 5],
            ['nama_level' => 'Md. Manager', 'indikator_total' => 5],
            ['nama_level' => 'Jr. Manager', 'indikator_total' => 5],

            // Asst Manager = 6 indikator
            ['nama_level' => 'Sr. Asst. Manager', 'indikator_total' => 6],
            ['nama_level' => 'Jr. Asst. Manager', 'indikator_total' => 6],

            // Supervisor = 5 indikator
            ['nama_level' => 'Sr. Supervisor', 'indikator_total' => 5],
            ['nama_level' => 'Jr. Supervisor', 'indikator_total' => 5],

            // Staff = 6 indikator
            ['nama_level' => 'Sr. Staff', 'indikator_total' => 6],
            ['nama_level' => 'Jr. Staff', 'indikator_total' => 6],

            // Operator = 5 indikator
            ['nama_level' => 'Sr. Operator', 'indikator_total' => 5],
            ['nama_level' => 'Md. Operator', 'indikator_total' => 5],
            ['nama_level' => 'Jr. Operator', 'indikator_total' => 5],
        ];

        foreach ($levels as $row) {
            DB::table('m_penilaian_level')->updateOrInsert(
                ['nama_level' => $row['nama_level']],
                [
                    'indikator_total' => $row['indikator_total'],
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
