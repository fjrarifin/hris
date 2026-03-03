<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PenilaianTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $levels = DB::table('m_penilaian_level')->pluck('id', 'nama_level');
        $faktor = DB::table('m_penilaian_faktor')->pluck('id', 'kode');

        // helper insert
        $insertTemplate = function (string $levelName, array $kodeFaktors) use ($levels, $faktor) {
            $levelId = $levels[$levelName] ?? null;
            if (! $levelId) {
                return;
            }

            $urutan = 1;
            foreach ($kodeFaktors as $kode) {
                DB::table('m_penilaian_template')->updateOrInsert(
                    [
                        'level_id' => $levelId,
                        'faktor_id' => $faktor[$kode],
                    ],
                    [
                        'urutan' => $urutan++,
                        'bobot' => 1.00,
                        'is_active' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        };

        // ✅ GM = 6 indikator
        $gm = [
            'LEADERSHIP',
            'DECISION',
            'COMMUNICATION',
            'INNOVATION',
            'RESPONSIBILITY',
            'PERFORMANCE',
        ];
        $insertTemplate('GM', $gm);

        // ✅ MANAGER = 5 indikator
        $manager = [
            'LEADERSHIP',
            'COMMUNICATION',
            'RESPONSIBILITY',
            'PERFORMANCE',
            'TEAMWORK',
        ];
        $insertTemplate('Sr. Manager', $manager);
        $insertTemplate('Md. Manager', $manager);
        $insertTemplate('Jr. Manager', $manager);

        // ✅ ASST MANAGER = 6 indikator
        $asst = [
            'LEADERSHIP',
            'DISCIPLINE',
            'COMMUNICATION',
            'RESPONSIBILITY',
            'TEAMWORK',
            'PERFORMANCE',
        ];
        $insertTemplate('Sr. Asst. Manager', $asst);
        $insertTemplate('Jr. Asst. Manager', $asst);

        // ✅ SUPERVISOR = 5 indikator
        $spv = [
            'DISCIPLINE',
            'TEAMWORK',
            'RESPONSIBILITY',
            'COMMUNICATION',
            'PERFORMANCE',
        ];
        $insertTemplate('Sr. Supervisor', $spv);
        $insertTemplate('Jr. Supervisor', $spv);

        // ✅ STAFF = 6 indikator
        $staff = [
            'DISCIPLINE',
            'ATTITUDE',
            'TEAMWORK',
            'COMMUNICATION',
            'RESPONSIBILITY',
            'PERFORMANCE',
        ];
        $insertTemplate('Sr. Staff', $staff);
        $insertTemplate('Jr. Staff', $staff);

        // ✅ OPERATOR = 5 indikator
        $opr = [
            'DISCIPLINE',
            'ATTITUDE',
            'RESPONSIBILITY',
            'PERFORMANCE',
            'CUSTOMER',
        ];
        $insertTemplate('Sr. Operator', $opr);
        $insertTemplate('Md. Operator', $opr);
        $insertTemplate('Jr. Operator', $opr);
    }
}
