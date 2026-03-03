<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FaktorScoreSeeder extends Seeder
{
    public function run(): void
    {
        $faktors = DB::table('m_penilaian_faktor')->get();

        foreach ($faktors as $f) {
            for ($s = 1; $s <= 5; $s++) {
                DB::table('m_penilaian_faktor_score')->updateOrInsert(
                    ['faktor_id' => $f->id, 'score' => $s],
                    ['deskripsi' => "Deskripsi score {$s} untuk faktor {$f->nama_faktor}"]
                );
            }
        }
    }
}
