<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MRelationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('m_relation')->insert([
            // 0001 terhubung ke banyak NIK
            ['nik' => '3201010101010001', 'nik_relasi' => '3201010101010002', 'created_at' => now(), 'updated_at' => now()],
            ['nik' => '3201010101010001', 'nik_relasi' => '3201010101010003', 'created_at' => now(), 'updated_at' => now()],
            ['nik' => '3201010101010001', 'nik_relasi' => '3201010101010004', 'created_at' => now(), 'updated_at' => now()],

            // 0002 terhubung ke banyak NIK
            ['nik' => '3201010101010002', 'nik_relasi' => '3201010101010001', 'created_at' => now(), 'updated_at' => now()],
            ['nik' => '3201010101010002', 'nik_relasi' => '3201010101010005', 'created_at' => now(), 'updated_at' => now()],

            // 0003 terhubung ke 0004 & 0005
            ['nik' => '3201010101010003', 'nik_relasi' => '3201010101010004', 'created_at' => now(), 'updated_at' => now()],
            ['nik' => '3201010101010003', 'nik_relasi' => '3201010101010005', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
