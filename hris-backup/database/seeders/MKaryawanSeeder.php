<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MKaryawanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('m_karyawan')->insert([
            [
                'nik' => '3201010101010001',
                'nama_karyawan' => 'Fajar Arifin',
                'jabatan' => 'IT Support',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nik' => '3201010101010002',
                'nama_karyawan' => 'Rizky Ramadhan',
                'jabatan' => 'HRD Staff',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nik' => '3201010101010003',
                'nama_karyawan' => 'Dinda Putri',
                'jabatan' => 'Finance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nik' => '3201010101010004',
                'nama_karyawan' => 'Agus Saputra',
                'jabatan' => 'Supervisor Operasional',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nik' => '3201010101010005',
                'nama_karyawan' => 'Siti Aisyah',
                'jabatan' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
