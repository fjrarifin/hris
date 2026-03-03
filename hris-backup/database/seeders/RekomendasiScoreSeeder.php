<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RekomendasiScoreSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('m_rekomendasi_score')->truncate();

        DB::table('m_rekomendasi_score')->insert([
            [
                'score' => 1,
                'label' => 'Sangat Kurang',
                'deskripsi_umum' => 'Tidak menunjukkan kompetensi, sangat di bawah ekspektasi.',
                'rekomendasi_pengembangan' => implode("\n", [
                    '- Coaching intensif 1-on-1 dari atasan langsung',
                    '- Program pelatihan dasar',
                    '- Penugasan bertahap dengan monitoring ketat',
                    '- Evaluasi berkala mingguan',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'score' => 2,
                'label' => 'Kurang',
                'deskripsi_umum' => 'Belum memadai, terlihat usaha tapi masih banyak kekurangan.',
                'rekomendasi_pengembangan' => implode("\n", [
                    '- Workshop penguatan kompetensi',
                    '- Partner kerja sebagai mentor (buddy system)',
                    '- Tugas proyek kecil untuk melatih keterampilan',
                    '- Review kinerja bulanan',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'score' => 3,
                'label' => 'Cukup',
                'deskripsi_umum' => 'Mencapai standar minimum, namun belum stabil.',
                'rekomendasi_pengembangan' => implode("\n", [
                    '- Pelatihan lanjutan (intermediate)',
                    '- Umpan balik berkala dari atasan',
                    '- Penugasan lintas fungsi sederhana untuk meningkatkan wawasan',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'score' => 4,
                'label' => 'Baik',
                'deskripsi_umum' => 'Konsisten menunjukkan kompetensi sesuai harapan.',
                'rekomendasi_pengembangan' => implode("\n", [
                    '- Dilibatkan dalam proyek tim',
                    '- Diberikan tanggung jawab lebih besar secara bertahap',
                    '- Didukung untuk membimbing rekan yang kurang kompeten',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'score' => 5,
                'label' => 'Sangat Baik',
                'deskripsi_umum' => 'Melampaui ekspektasi, menjadi panutan bagi orang lain.',
                'rekomendasi_pengembangan' => implode("\n", [
                    '- Dipersiapkan untuk promosi / jalur karier berikutnya',
                    '- Dilibatkan sebagai mentor atau trainer internal',
                    '- Diikutsertakan dalam program talent pool perusahaan',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
