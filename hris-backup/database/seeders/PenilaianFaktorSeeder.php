<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PenilaianFaktorSeeder extends Seeder
{
    public function run(): void
    {
        $faktors = [
            ['kode' => 'DISCIPLINE', 'nama_faktor' => 'Kedisiplinan', 'deskripsi' => 'Kehadiran tepat waktu, kepatuhan aturan, konsistensi.'],
            ['kode' => 'TEAMWORK', 'nama_faktor' => 'Kerjasama Tim', 'deskripsi' => 'Kolaborasi, membantu tim, menjaga kekompakan.'],
            ['kode' => 'RESPONSIBILITY', 'nama_faktor' => 'Tanggung Jawab', 'deskripsi' => 'Menyelesaikan tugas tepat waktu dan sesuai standar.'],
            ['kode' => 'COMMUNICATION', 'nama_faktor' => 'Komunikasi', 'deskripsi' => 'Menyampaikan info jelas, koordinasi, responsif.'],
            ['kode' => 'PERFORMANCE', 'nama_faktor' => 'Kinerja / Hasil Kerja', 'deskripsi' => 'Kualitas output kerja, ketepatan, produktivitas.'],
            ['kode' => 'LEADERSHIP', 'nama_faktor' => 'Leadership', 'deskripsi' => 'Kemampuan memimpin, memberi arahan, jadi panutan.'],
            ['kode' => 'DECISION', 'nama_faktor' => 'Pengambilan Keputusan', 'deskripsi' => 'Cepat, tepat, dan bertanggung jawab dalam keputusan.'],
            ['kode' => 'INNOVATION', 'nama_faktor' => 'Inovasi & Improvement', 'deskripsi' => 'Inisiatif perbaikan proses dan ide baru.'],
            ['kode' => 'CUSTOMER', 'nama_faktor' => 'Orientasi Pelayanan', 'deskripsi' => 'Fokus pada kepuasan pelanggan/internal user.'],
            ['kode' => 'ATTITUDE', 'nama_faktor' => 'Sikap & Etika Kerja', 'deskripsi' => 'Attitude positif, integritas, sopan santun.'],
        ];

        foreach ($faktors as $f) {
            DB::table('m_penilaian_faktor')->updateOrInsert(
                ['kode' => $f['kode']],
                [
                    'nama_faktor' => $f['nama_faktor'],
                    'deskripsi' => $f['deskripsi'],
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
