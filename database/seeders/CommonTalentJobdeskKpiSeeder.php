<?php

namespace Database\Seeders;

use App\Models\Jobdesk;
use App\Models\KpiTemplate;
use App\Models\MasterJabatan;
use App\Services\PerformanceManagementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommonTalentJobdeskKpiSeeder extends Seeder
{
    public function run(PerformanceManagementService $service): void
    {
        foreach ($this->templates() as $jabatanName => $template) {
            $jabatan = MasterJabatan::query()->where('nama_jabatan', $jabatanName)->first();

            if (! $jabatan) {
                $this->command?->warn("Master jabatan '{$jabatanName}' tidak ditemukan. Data dilewati.");

                continue;
            }

            DB::transaction(function () use ($jabatan, $template, $service): void {
                $jobdesks = collect($template['jobdesks'])->mapWithKeys(function (array $payload) use ($jabatan): array {
                    $jobdesk = Jobdesk::query()->updateOrCreate(
                        [
                            'master_jabatan_id' => $jabatan->id,
                            'kategori' => $payload['kategori'],
                            'deskripsi' => $payload['deskripsi'],
                        ],
                        [
                            'tipe_tugas' => $payload['tipe_tugas'],
                            'is_active' => true,
                        ]
                    );

                    return [$payload['key'] => $jobdesk];
                });

                $seededKpiIds = collect($template['kpis'])->map(function (array $payload) use ($jabatan, $jobdesks): int {
                    return KpiTemplate::query()->updateOrCreate(
                        [
                            'master_jabatan_id' => $jabatan->id,
                            'nama_kpi' => $payload['nama_kpi'],
                        ],
                        [
                            'jobdesk_id' => $jobdesks[$payload['jobdesk_key']]->id,
                            'deskripsi' => $payload['deskripsi'],
                            'target' => $payload['target'],
                            'satuan' => $payload['satuan'],
                            'bobot' => $payload['bobot'],
                            'formula_penilaian' => $payload['formula_penilaian'],
                        ]
                    )->id;
                });

                $hasOtherActiveKpis = KpiTemplate::query()
                    ->where('master_jabatan_id', $jabatan->id)
                    ->where('is_active', true)
                    ->whereNotIn('id', $seededKpiIds)
                    ->exists();

                if ($hasOtherActiveKpis) {
                    $this->command?->warn("KPI '{$jabatan->nama_jabatan}' disimpan, tetapi tidak diaktifkan karena terdapat KPI aktif lain.");

                    return;
                }

                $service->syncActiveKpis($jabatan, $seededKpiIds->all());
            });

            $this->command?->info("Jobdesk dan KPI '{$jabatanName}' berhasil disiapkan.");
        }
    }

    private function templates(): array
    {
        return [
            'SPV IT' => [
                'jobdesks' => [
                    $this->jobdesk('operasional', 'Operasional Infrastruktur', 'Memastikan jaringan, server, perangkat kerja, dan layanan TI utama tersedia dengan baik.', 'harian'),
                    $this->jobdesk('support', 'Pengelolaan Dukungan TI', 'Mengatur prioritas, eskalasi, dan penyelesaian tiket atau kendala pengguna.', 'mingguan'),
                    $this->jobdesk('security', 'Keamanan dan Backup', 'Memastikan backup, akses pengguna, pembaruan sistem, dan tindak lanjut risiko keamanan berjalan.', 'mingguan'),
                    $this->jobdesk('planning', 'Perencanaan TI', 'Menyusun evaluasi kebutuhan, anggaran, vendor, dan rencana peningkatan layanan TI.', 'bulanan'),
                ],
                'kpis' => [
                    $this->kpi('operasional', 'Ketersediaan layanan TI utama', 'Persentase ketersediaan jaringan dan layanan TI utama.', '99', 'persen', 30, '(jam layanan tersedia / jam layanan terjadwal) x 100'),
                    $this->kpi('support', 'Penyelesaian tiket sesuai SLA tim', 'Persentase tiket tim yang selesai sesuai SLA.', '90', 'persen', 25, '(tiket selesai sesuai SLA / tiket selesai) x 100'),
                    $this->kpi('security', 'Kepatuhan backup dan pemeriksaan keamanan', 'Persentase agenda backup dan pemeriksaan keamanan yang terlaksana.', '100', 'persen', 25, '(agenda terlaksana / agenda terjadwal) x 100'),
                    $this->kpi('planning', 'Realisasi rencana kerja TI bulanan', 'Persentase rencana kerja prioritas TI yang selesai.', '90', 'persen', 20, '(rencana selesai / rencana disepakati) x 100'),
                ],
            ],
            'Staff IT' => [
                'jobdesks' => [
                    $this->jobdesk('support', 'Helpdesk Pengguna', 'Menangani kendala perangkat, aplikasi, akun, dan konektivitas pengguna.', 'harian'),
                    $this->jobdesk('maintenance', 'Pemeliharaan Perangkat TI', 'Melakukan pemeriksaan, perawatan, dan pembaruan perangkat serta aplikasi kerja.', 'mingguan'),
                    $this->jobdesk('inventory', 'Administrasi Aset TI', 'Memperbarui pencatatan aset, lisensi, perangkat pinjaman, dan riwayat perbaikan.', 'mingguan'),
                    $this->jobdesk('documentation', 'Dokumentasi Teknis', 'Menyusun dokumentasi solusi, konfigurasi, dan aktivitas dukungan TI.', 'bulanan'),
                ],
                'kpis' => [
                    $this->kpi('support', 'Penyelesaian tiket sesuai SLA', 'Persentase tiket yang diselesaikan sesuai SLA.', '90', 'persen', 35, '(tiket selesai sesuai SLA / tiket selesai) x 100'),
                    $this->kpi('maintenance', 'Realisasi preventive maintenance TI', 'Persentase agenda pemeliharaan perangkat yang terlaksana.', '100', 'persen', 25, '(agenda terlaksana / agenda terjadwal) x 100'),
                    $this->kpi('inventory', 'Akurasi data aset TI', 'Persentase aset sampel yang sesuai dengan catatan inventaris.', '98', 'persen', 20, '(aset sesuai / aset diperiksa) x 100'),
                    $this->kpi('documentation', 'Pembaruan dokumentasi teknis', 'Jumlah dokumentasi solusi atau konfigurasi yang diperbarui.', '4', 'dokumen', 20, '(realisasi / target) x 100, maksimal 100'),
                ],
            ],
            'Graphic Designer' => [
                'jobdesks' => [
                    $this->jobdesk('design', 'Produksi Materi Desain', 'Membuat materi visual sesuai brief untuk kebutuhan promosi, operasional, dan komunikasi perusahaan.', 'harian'),
                    $this->jobdesk('revision', 'Revisi dan Finalisasi', 'Menindaklanjuti masukan, menyiapkan file final, dan memastikan spesifikasi output sesuai kebutuhan.', 'harian'),
                    $this->jobdesk('branding', 'Konsistensi Brand', 'Menjaga penggunaan elemen visual agar sesuai pedoman brand perusahaan.', 'mingguan'),
                    $this->jobdesk('asset', 'Pengelolaan Aset Desain', 'Menata file sumber, hasil akhir, dan aset desain agar mudah ditemukan kembali.', 'mingguan'),
                ],
                'kpis' => [
                    $this->kpi('design', 'Penyelesaian desain sesuai deadline', 'Persentase permintaan desain selesai sesuai tenggat.', '90', 'persen', 35, '(desain tepat waktu / desain selesai) x 100'),
                    $this->kpi('revision', 'Persetujuan desain maksimal dua revisi', 'Persentase desain yang disetujui maksimal dalam dua putaran revisi.', '85', 'persen', 25, '(desain disetujui maksimal dua revisi / desain selesai) x 100'),
                    $this->kpi('branding', 'Kepatuhan desain terhadap brand guideline', 'Persentase desain sampel yang memenuhi pedoman brand.', '95', 'persen', 25, '(desain sesuai guideline / desain diperiksa) x 100'),
                    $this->kpi('asset', 'Kerapian arsip aset desain', 'Persentase file proyek yang tersimpan sesuai struktur arsip.', '95', 'persen', 15, '(file sesuai struktur / file diperiksa) x 100'),
                ],
            ],
            'Cleaner' => [
                'jobdesks' => [
                    $this->jobdesk('cleaning', 'Kebersihan Area', 'Membersihkan area kerja, fasilitas umum, dan titik layanan sesuai pembagian area.', 'harian'),
                    $this->jobdesk('inspection', 'Pemeriksaan Kebersihan', 'Melakukan pemeriksaan berkala dan menindaklanjuti temuan kebersihan.', 'harian'),
                    $this->jobdesk('supplies', 'Ketersediaan Perlengkapan', 'Memastikan perlengkapan kebersihan dan kebutuhan fasilitas tersedia.', 'harian'),
                    $this->jobdesk('reporting', 'Pelaporan Temuan Fasilitas', 'Melaporkan kerusakan atau kondisi fasilitas yang membutuhkan tindak lanjut.', 'insidental'),
                ],
                'kpis' => [
                    $this->kpi('cleaning', 'Kepatuhan checklist kebersihan', 'Persentase checklist kebersihan yang diselesaikan.', '100', 'persen', 40, '(checklist selesai / checklist terjadwal) x 100'),
                    $this->kpi('inspection', 'Skor inspeksi kebersihan area', 'Rata-rata skor inspeksi kebersihan area.', '90', 'skor', 30, 'rata-rata skor inspeksi'),
                    $this->kpi('supplies', 'Ketersediaan perlengkapan kebersihan', 'Persentase pemeriksaan tanpa kekosongan perlengkapan utama.', '95', 'persen', 15, '(pemeriksaan tanpa kekosongan / total pemeriksaan) x 100'),
                    $this->kpi('reporting', 'Ketepatan pelaporan temuan fasilitas', 'Persentase temuan fasilitas yang dilaporkan pada hari yang sama.', '95', 'persen', 15, '(temuan dilaporkan tepat waktu / total temuan) x 100'),
                ],
            ],
            'Teknisi MEP' => [
                'jobdesks' => [
                    $this->jobdesk('inspection', 'Inspeksi Utilitas', 'Memeriksa sistem mekanikal, elektrikal, dan plumbing sesuai checklist.', 'harian'),
                    $this->jobdesk('maintenance', 'Preventive Maintenance', 'Melaksanakan perawatan terjadwal untuk peralatan dan utilitas gedung.', 'mingguan'),
                    $this->jobdesk('repair', 'Penanganan Gangguan', 'Menindaklanjuti gangguan teknis, melakukan perbaikan, dan mencatat hasilnya.', 'insidental'),
                    $this->jobdesk('reporting', 'Pelaporan Teknis', 'Memperbarui log pekerjaan, penggunaan spare part, dan temuan teknis.', 'harian'),
                ],
                'kpis' => [
                    $this->kpi('inspection', 'Kepatuhan checklist inspeksi MEP', 'Persentase checklist inspeksi yang diselesaikan.', '100', 'persen', 25, '(checklist selesai / checklist terjadwal) x 100'),
                    $this->kpi('maintenance', 'Realisasi preventive maintenance MEP', 'Persentase agenda preventive maintenance yang terlaksana.', '95', 'persen', 30, '(agenda terlaksana / agenda terjadwal) x 100'),
                    $this->kpi('repair', 'Penyelesaian gangguan sesuai SLA', 'Persentase gangguan teknis yang selesai sesuai SLA.', '90', 'persen', 30, '(gangguan selesai sesuai SLA / gangguan selesai) x 100'),
                    $this->kpi('reporting', 'Kelengkapan log pekerjaan teknis', 'Persentase pekerjaan teknis dengan log lengkap.', '100', 'persen', 15, '(log lengkap / pekerjaan selesai) x 100'),
                ],
            ],
            'Cashier' => [
                'jobdesks' => [
                    $this->jobdesk('transaction', 'Pelayanan Transaksi', 'Memproses transaksi pelanggan dengan cepat, tepat, dan ramah.', 'harian'),
                    $this->jobdesk('cash', 'Pengelolaan Kas', 'Memastikan uang tunai, pembayaran non-tunai, dan bukti transaksi tercatat dengan benar.', 'harian'),
                    $this->jobdesk('closing', 'Closing Kasir', 'Melakukan rekonsiliasi dan menyerahkan laporan closing sesuai prosedur.', 'harian'),
                    $this->jobdesk('service', 'Pelayanan Pelanggan', 'Menjawab pertanyaan dasar pelanggan dan mengarahkan eskalasi bila diperlukan.', 'harian'),
                ],
                'kpis' => [
                    $this->kpi('cash', 'Akurasi transaksi kasir', 'Persentase transaksi tanpa koreksi atau selisih.', '99', 'persen', 35, '(transaksi akurat / total transaksi) x 100'),
                    $this->kpi('closing', 'Closing tanpa selisih kas', 'Persentase hari kerja tanpa selisih kas.', '100', 'persen', 30, '(hari tanpa selisih / hari kerja) x 100'),
                    $this->kpi('transaction', 'Kepatuhan prosedur transaksi', 'Persentase hasil audit transaksi yang sesuai SOP.', '95', 'persen', 20, '(transaksi sesuai SOP / transaksi diperiksa) x 100'),
                    $this->kpi('service', 'Skor pelayanan pelanggan kasir', 'Rata-rata skor pelayanan pelanggan atau observasi atasan.', '90', 'skor', 15, 'rata-rata skor pelayanan'),
                ],
            ],
            'Staff Customer Service' => [
                'jobdesks' => [
                    $this->jobdesk('service', 'Pelayanan Informasi Pelanggan', 'Memberikan informasi yang akurat, ramah, dan mudah dipahami kepada pelanggan.', 'harian'),
                    $this->jobdesk('complaint', 'Penanganan Keluhan', 'Mencatat, menindaklanjuti, dan mengeskalasi keluhan pelanggan sesuai prosedur.', 'harian'),
                    $this->jobdesk('followup', 'Tindak Lanjut Pelanggan', 'Memastikan permintaan atau keluhan pelanggan mendapatkan penyelesaian dan konfirmasi.', 'harian'),
                    $this->jobdesk('reporting', 'Pelaporan Layanan', 'Menyusun rekap pertanyaan, keluhan, dan penyelesaian layanan pelanggan.', 'mingguan'),
                ],
                'kpis' => [
                    $this->kpi('complaint', 'Penyelesaian keluhan sesuai SLA', 'Persentase keluhan yang diselesaikan sesuai SLA.', '90', 'persen', 35, '(keluhan selesai sesuai SLA / keluhan selesai) x 100'),
                    $this->kpi('followup', 'Ketepatan tindak lanjut pelanggan', 'Persentase permintaan pelanggan yang ditindaklanjuti tepat waktu.', '95', 'persen', 25, '(tindak lanjut tepat waktu / total tindak lanjut) x 100'),
                    $this->kpi('service', 'Skor kepuasan pelanggan', 'Rata-rata skor kepuasan atau observasi kualitas pelayanan.', '90', 'skor', 25, 'rata-rata skor kepuasan pelanggan'),
                    $this->kpi('reporting', 'Kelengkapan pencatatan layanan', 'Persentase interaksi prioritas yang tercatat lengkap.', '98', 'persen', 15, '(catatan lengkap / catatan diperiksa) x 100'),
                ],
            ],
        ];
    }

    private function jobdesk(string $key, string $kategori, string $deskripsi, string $tipeTugas): array
    {
        return compact('key', 'kategori', 'deskripsi') + ['tipe_tugas' => $tipeTugas];
    }

    private function kpi(string $jobdeskKey, string $namaKpi, string $deskripsi, string $target, string $satuan, int $bobot, string $formulaPenilaian): array
    {
        return [
            'jobdesk_key' => $jobdeskKey,
            'nama_kpi' => $namaKpi,
            'deskripsi' => $deskripsi,
            'target' => $target,
            'satuan' => $satuan,
            'bobot' => $bobot,
            'formula_penilaian' => $formulaPenilaian,
        ];
    }
}
