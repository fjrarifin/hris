<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use App\Models\KpiTemplate;
use App\Models\KpiTemplateAssignment;
use App\Models\KpiTemplateItem;
use App\Models\MasterJabatan;
use App\Models\PerformancePeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KpiDepartmentTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $defaultUser = User::query()->first();
        $hrUser = User::query()->where('level', 2)->first() ?: $defaultUser;
        $fajarUser = User::query()->where('username', 'HPP25120147')->first() ?: $defaultUser;
        $gustiUser = User::query()->where('username', 'HPP25120097')->first() ?: $defaultUser;
        $syifaUser = User::query()->where('username', 'HPP22970012')->first() ?: $defaultUser;
        $kikiUser = User::query()->where('username', 'HPP19100006')->first() ?: $defaultUser;
        $sadamUser = User::query()->where('username', 'HPP21110010')->first() ?: $defaultUser;
        $andrianUser = User::query()->where('username', 'HPP25120113')->first() ?: $defaultUser;

        $templatesData = [
            // 1. Information Technology
            [
                'nama_template' => 'KPI Lead IT Support & Point of Sales (POS)',
                'deskripsi' => 'Pengawasan stabilitas infrastruktur IT, jaringan internet, ketersediaan mesin POS tiket, dan dukungan operasional harian.',
                'departemen' => 'Information Technology',
                'jabatan_name' => 'SPV IT',
                'creator_nik' => 'HPP25120147',
                'creator_id' => $fajarUser?->id,
                'supervisor_nik' => 'HPP25090039',
                'status' => 'approved',
                'hrd_approved_by' => $hrUser?->id,
                'hrd_approved_at' => now(),
                'supervisor_approved_at' => now()->subDay(),
                'items' => [
                    [
                        'nama_kpi' => 'Ketersediaan Jaringan & Sistem POS Outlet',
                        'deskripsi' => 'Persentase waktu sistem POS tiket dan jaringan operasional aktif tanpa gangguan (Uptime).',
                        'target' => '99.8',
                        'satuan' => 'persen',
                        'bobot' => 35,
                        'formula_penilaian' => '(jam sistem aktif / jam operasional terjadwal) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kecepatan Resolusi Tiket Gangguan IT (SLA < 15 Menit)',
                        'deskripsi' => 'Penyelesaian insiden teknis kasir dan operasional sesuai Service Level Agreement.',
                        'target' => '95',
                        'satuan' => 'persen',
                        'bobot' => 30,
                        'formula_penilaian' => '(tiket selesai tepat waktu / total tiket gangguan) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kepatuhan Jadwal Backup Data & Audit Keamanan',
                        'deskripsi' => 'Pelaksanaan backup berkala database penjualan dan pemeliharaan antivirus/keamanan server.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '(jadwal backup terlaksana / total jadwal wajib) x 100',
                    ],
                    [
                        'nama_kpi' => 'Dokumentasi Log Troubleshooting & Inventaris Hardware IT',
                        'deskripsi' => 'Kerapihan pencatatan logbook perbaikan dan kartu stok aset perangkat keras IT.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 15,
                        'formula_penilaian' => 'Evaluasi kelengkapan dokumen inventaris dan log insiden bulanan',
                    ],
                ],
                'assign_niks' => ['HPP26030194', 'HPP00000002'],
            ],

            // 2. Marketing
            [
                'nama_template' => 'KPI Digital Marketing & Social Media Campaign',
                'deskripsi' => 'Pengelolaan kampanye pemasaran digital, peningkatan impresi media sosial, dan konversi promosi wahana.',
                'departemen' => 'Marketing',
                'jabatan_name' => 'Graphic Designer',
                'creator_nik' => 'HPP25120097',
                'creator_id' => $gustiUser?->id,
                'supervisor_nik' => 'HPP25110042',
                'status' => 'approved',
                'hrd_approved_by' => $hrUser?->id,
                'hrd_approved_at' => now(),
                'supervisor_approved_at' => now()->subDay(),
                'items' => [
                    [
                        'nama_kpi' => 'Pertumbuhan Follower & Organic Reach Media Sosial',
                        'deskripsi' => 'Peningkatan jangkauan akun resmi Instagram, TikTok, dan media promosi perusahaan.',
                        'target' => '25',
                        'satuan' => 'persen',
                        'bobot' => 30,
                        'formula_penilaian' => '((reach bulan ini - reach bulan lalu) / reach bulan lalu) x 100',
                    ],
                    [
                        'nama_kpi' => 'Tingkat Konversi Kampanye Digital ke Pembelian Tiket',
                        'deskripsi' => 'Persentase audiens kampanye online yang melakukan penukaran voucher / transaksi tiket.',
                        'target' => '15',
                        'satuan' => 'persen',
                        'bobot' => 30,
                        'formula_penilaian' => '(tiket terjual via promo digital / total kunjungan campaign) x 100',
                    ],
                    [
                        'nama_kpi' => 'Ketepatan Rilis Konten Sesuai Kalender Kampanye',
                        'deskripsi' => 'Kepatuhan jadwal upload postingan, reels, dan materi promosi event tematik.',
                        'target' => '95',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '(konten tepat jadwal / total rencana kalender konten) x 100',
                    ],
                    [
                        'nama_kpi' => 'Efisiensi Biaya Iklan Digital (ROAS Min. 4x)',
                        'deskripsi' => 'Return on Ad Spend untuk iklan berbayar (Meta Ads / TikTok Ads).',
                        'target' => '4',
                        'satuan' => 'skor',
                        'bobot' => 20,
                        'formula_penilaian' => 'Total pendapatan penjualan tiket dari iklan / Total biaya iklan',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 3. Sales
            [
                'nama_template' => 'KPI Sales B2B, Corporate & School Partnership',
                'deskripsi' => 'Pencapaian target penjualan rombongan, kemitraan sekolah, paket ulang tahun, dan program gathering perusahaan.',
                'departemen' => 'Sales',
                'jabatan_name' => 'Cashier',
                'creator_nik' => 'HPP22970012',
                'creator_id' => $syifaUser?->id,
                'supervisor_nik' => 'HPP25080035',
                'status' => 'approved',
                'hrd_approved_by' => $hrUser?->id,
                'hrd_approved_at' => now(),
                'supervisor_approved_at' => now()->subDay(),
                'items' => [
                    [
                        'nama_kpi' => 'Realisasi Target Pendapatan B2B & Rombongan',
                        'deskripsi' => 'Total nominal pendapatan dari tiket rombongan, sekolah, dan corporate gathering.',
                        'target' => '120000000',
                        'satuan' => 'rupiah',
                        'bobot' => 40,
                        'formula_penilaian' => '(realisasi omzet B2B / target omzet 120 Juta) x 100',
                    ],
                    [
                        'nama_kpi' => 'Akuisisi Klien / Sekolah Baru',
                        'deskripsi' => 'Jumlah institusi, sekolah, atau komunitas baru yang menandatangani pemesanan paket rombongan.',
                        'target' => '6',
                        'satuan' => 'jumlah',
                        'bobot' => 25,
                        'formula_penilaian' => '(jumlah klien baru tercapai / target 6 institusi) x 100',
                    ],
                    [
                        'nama_kpi' => 'Rasio Closing dari Total Penawaran Prospek',
                        'deskripsi' => 'Efektivitas konversi proposal penawaran menjadi transaksi definitif.',
                        'target' => '35',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '(penawaran yang deal / total penawaran dikirim) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kelengkapan Berkas SPK & Pelunasan Tepat Waktu',
                        'deskripsi' => 'Ketertiban dokumen surat perjanjian kerjasama dan kepatuhan pembayaran tepat waktu.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 15,
                        'formula_penilaian' => '(transaksi lunas ber-SPK lengkap / total transaksi B2B) x 100',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 4. Finance
            [
                'nama_template' => 'KPI Supervisor Keuangan, Kas & Rekonsiliasi Kasir',
                'deskripsi' => 'Pengawasan tata kelola kas kecil, rekonsiliasi setoran kasir harian, verifikasi transaksi non-tunai, dan pelaporan keuangan.',
                'departemen' => 'Finance',
                'jabatan_name' => 'Staff Customer Service',
                'creator_nik' => 'HPP19100006',
                'creator_id' => $kikiUser?->id,
                'supervisor_nik' => 'HPP21020020',
                'status' => 'approved',
                'hrd_approved_by' => $hrUser?->id,
                'hrd_approved_at' => now(),
                'supervisor_approved_at' => now()->subDay(),
                'items' => [
                    [
                        'nama_kpi' => 'Akurasi Rekonsiliasi Kas & Penjualan POS Harian',
                        'deskripsi' => 'Pencocokan mutasi kas fisik, EDC, QRIS, dan transfer bank terhadap laporan penjualan harian.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 35,
                        'formula_penilaian' => 'Toleransi selisih kas 0% dan tepat waktu penyelesaian harian',
                    ],
                    [
                        'nama_kpi' => 'Kecepatan Penyelesaian Laporan Keuangan Bulanan',
                        'deskripsi' => 'Penyusunan laporan laba rugi dan neraca operasional sebelum tanggal 5 tiap bulan.',
                        'target' => '5',
                        'satuan' => 'hari',
                        'bobot' => 25,
                        'formula_penilaian' => 'Maksimal diserahkan pada tanggal 5 hari kerja awal bulan',
                    ],
                    [
                        'nama_kpi' => 'Ketertiban Pengarsipan Faktur, Pajak & Bukti Kas',
                        'deskripsi' => 'Kelengkapan bukti transaksi pembayaran supplier, pengeluaran kasbon, dan arsip faktur.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '(bukti kas lengkap terverifikasi / total pengeluaran) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kepatuhan Realisasi Anggaran Operasional (Budget Variance < 3%)',
                        'deskripsi' => 'Pengendalian pengeluaran rutin agar tidak melebihi alokasi anggaran yang telah disetujui.',
                        'target' => '97',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '100 - (|realisasi - budget| / budget * 100)',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 5. Activity
            [
                'nama_template' => 'KPI Supervisor Operasional Wahana, Atraksi & Games',
                'deskripsi' => 'Standar pengoperasian arena permainan, pencegahan kecelakaan, pelayanan pemandu wahana, dan kepuasan pengunjung.',
                'departemen' => 'Activity',
                'jabatan_name' => null,
                'creator_nik' => 'HPP21110010',
                'creator_id' => $sadamUser?->id,
                'supervisor_nik' => 'HPP25070034',
                'status' => 'approved',
                'hrd_approved_by' => $hrUser?->id,
                'hrd_approved_at' => now(),
                'supervisor_approved_at' => now()->subDay(),
                'items' => [
                    [
                        'nama_kpi' => 'Zero Accident & Kepatuhan Prosedur Keselamatan (SOP K3)',
                        'deskripsi' => 'Peniadaan insiden cedera atau kecelakaan pengunjung pada seluruh wahana bermain.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 35,
                        'formula_penilaian' => 'Nol insiden kecelakaan akibat kelalaian operasional kru wahana',
                    ],
                    [
                        'nama_kpi' => 'Tingkat Kepuasan Pengunjung Terhadap Keramahan Kru (CSAT)',
                        'deskripsi' => 'Hasil skor survei kepuasan pelanggan terkait keramahan dan kesigapan kru wahana.',
                        'target' => '4.8',
                        'satuan' => 'skor',
                        'bobot' => 25,
                        'formula_penilaian' => 'Rata-rata skor ulasan pengunjung (skala 1 - 5)',
                    ],
                    [
                        'nama_kpi' => 'Kesiapan Waktu Buka Wahana Sesuai Jadwal Operasional',
                        'deskripsi' => 'Wahana telah siap beroperasi 15 menit sebelum jam buka resmi arena.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '(hari buka tepat waktu / total hari operasional) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kepatuhan Checklist Sanitasi & Kebersihan Area Bermain',
                        'deskripsi' => 'Pelaksanaan desinfeksi berkala bola mainan, matras, dan peralatan wahana anak.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '(inspeksi sanitasi tuntas / total checklist harian) x 100',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 6. General Affair
            [
                'nama_template' => 'KPI Pengawasan Fasilitas Umum, Sanitasi & Keamanan',
                'deskripsi' => 'Pemeliharaan kebersihan toilet/ruang publik, respon perbaikan fasilitas gedung, dan koordinasi keamanan arena.',
                'departemen' => 'General Affair',
                'jabatan_name' => 'Cleaner',
                'creator_nik' => 'HPP25120113',
                'creator_id' => $andrianUser?->id,
                'supervisor_nik' => 'HPP22010004',
                'status' => 'approved',
                'hrd_approved_by' => $hrUser?->id,
                'hrd_approved_at' => now(),
                'supervisor_approved_at' => now()->subDay(),
                'items' => [
                    [
                        'nama_kpi' => 'Kecepatan Respon Perbaikan Fasilitas Publik (SLA < 2 Jam)',
                        'deskripsi' => 'Penanganan laporan kerusakan AC, lampu, pintu, atau plumbing area pengunjung.',
                        'target' => '95',
                        'satuan' => 'persen',
                        'bobot' => 30,
                        'formula_penilaian' => '(tiket fasilitas selesai < 2 jam / total laporan) x 100',
                    ],
                    [
                        'nama_kpi' => 'Skor Audit Kebersihan & Kerapihan Area Pengunjung',
                        'deskripsi' => 'Hasil penilaian berkala kebersihan toilet, koridor, ruang tunggu, dan food corner.',
                        'target' => '92',
                        'satuan' => 'skor',
                        'bobot' => 25,
                        'formula_penilaian' => 'Nilai rata-rata audit kebersihan mingguan (skala 0 - 100)',
                    ],
                    [
                        'nama_kpi' => 'Efisiensi Biaya Utilitas Listrik & Penggunaan ATK',
                        'deskripsi' => 'Pengendalian pemborosan energi dan efisiensi konsumsi perlengkapan kantor.',
                        'target' => '10',
                        'satuan' => 'persen',
                        'bobot' => 25,
                        'formula_penilaian' => 'Penghematan biaya utilitas dibanding baseline bulan sebelumnya',
                    ],
                    [
                        'nama_kpi' => 'Kinerja Pengawasan Vendor Outsourcing (Cleaning & Security)',
                        'deskripsi' => 'Evaluasi disiplin kerja, jumlah personil hadir, dan kepatuhan SOP pihak ketiga.',
                        'target' => '90',
                        'satuan' => 'skor',
                        'bobot' => 20,
                        'formula_penilaian' => 'Skor KPI bulanan evaluasi vendor outsourcing',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 7. Hompim Store
            [
                'nama_template' => 'KPI Operasional Toko Merchandise & Visual Merchandising',
                'deskripsi' => 'Pencapaian penjualan cenderamata, pengelolaan stok barang dagangan, dan display produk toko ritel.',
                'departemen' => 'Hompim Store',
                'jabatan_name' => null,
                'creator_nik' => 'HPP25120147',
                'creator_id' => $fajarUser?->id,
                'supervisor_nik' => 'HPP25090039',
                'status' => 'approved',
                'hrd_approved_by' => $hrUser?->id,
                'hrd_approved_at' => now(),
                'supervisor_approved_at' => now()->subDay(),
                'items' => [
                    [
                        'nama_kpi' => 'Pencapaian Target Omzet Penjualan Retail & Merchandise',
                        'deskripsi' => 'Realisasi total omzet penjualan souvenir, baju, mainan, dan pernak-pernik toko.',
                        'target' => '75000000',
                        'satuan' => 'rupiah',
                        'bobot' => 35,
                        'formula_penilaian' => '(omzet riil / target 75 Juta) x 100',
                    ],
                    [
                        'nama_kpi' => 'Akurasi Stock Opname Fisik vs Sistem POS (Selisih < 0.5%)',
                        'deskripsi' => 'Tingkat kesesuaian jumlah barang di rak dan gudang dengan data inventori POS.',
                        'target' => '99.5',
                        'satuan' => 'persen',
                        'bobot' => 30,
                        'formula_penilaian' => '(1 - (|selisih stok| / total unit stok)) x 100',
                    ],
                    [
                        'nama_kpi' => 'Standar Display Produk & Visual Merchandising',
                        'deskripsi' => 'Kerapihan susunan produk, label harga jelas, dan rotasi produk unggulan di etalase.',
                        'target' => '90',
                        'satuan' => 'skor',
                        'bobot' => 20,
                        'formula_penilaian' => 'Skor audit visual merchandising toko berkala',
                    ],
                    [
                        'nama_kpi' => 'Kecepatan Input Penerimaan Barang Masuk (GRN) < 24 Jam',
                        'deskripsi' => 'Pencatatan barang dari supplier ke sistem inventori tepat waktu setelah bongkar muat.',
                        'target' => '24',
                        'satuan' => 'hari',
                        'bobot' => 15,
                        'formula_penilaian' => 'Maksimal 24 jam setelah barang diverifikasi di gudang',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 8. HRBP - Menunggu Verifikasi HRD (waiting_hrd)
            [
                'nama_template' => 'KPI Talent Acquisition, Onboarding & People Relations',
                'deskripsi' => 'Pengelolaan siklus rekrutmen karyawan baru, masa orientasi kerja, dan pemeliharaan kepuasan hubungan kerja internal.',
                'departemen' => 'HRBP',
                'jabatan_name' => null,
                'creator_nik' => 'HPP11030007',
                'creator_id' => $defaultUser?->id,
                'supervisor_nik' => 'HPP25120030',
                'status' => 'waiting_hrd',
                'hrd_approved_by' => null,
                'hrd_approved_at' => null,
                'supervisor_approved_at' => now()->subHours(6),
                'supervisor_notes' => 'Sudah direview. Butir KPI selaras dengan rencana rekrutmen cabang baru tahun ini.',
                'items' => [
                    [
                        'nama_kpi' => 'Kecepatan Pemenuhan Lowongan Karyawan Kritis (Time-to-Fill < 21 Hari)',
                        'deskripsi' => 'Durasi waktu dari penerbitan permintaan rekrutmen hingga kandidat menandatangani offering.',
                        'target' => '21',
                        'satuan' => 'hari',
                        'bobot' => 35,
                        'formula_penilaian' => 'Rata-rata hari pemenuhan rekrutmen posisi staff operasional',
                    ],
                    [
                        'nama_kpi' => 'Kelengkapan Berkas Onboarding & Pelatihan Awal Karyawan Baru',
                        'deskripsi' => 'Penyelesaian berkas identitas, NPWP, BPJS, PKB, dan orientasi pengenalan nilai perusahaan.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 30,
                        'formula_penilaian' => '(karyawan baru ber-dokumen lengkap / total masuk kerja) x 100',
                    ],
                    [
                        'nama_kpi' => 'Tingkat Kelulusan Masa Percobaan 3 Bulan Pertama',
                        'deskripsi' => 'Persentase karyawan baru yang berhasil mencapai standar evaluasi probation.',
                        'target' => '85',
                        'satuan' => 'persen',
                        'bobot' => 20,
                        'formula_penilaian' => '(kandidat lolos probation / total rekrutan) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kecepatan Penanganan Konseling Kasus Hubungan Kerja',
                        'deskripsi' => 'Penyelesaian mediasi keluhan internal dan penerbitan surat peringatan (SP) sesuai aturan.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 15,
                        'formula_penilaian' => '(kasus tuntas sesuai regulasi / total kasus masuk) x 100',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 9. Proyek - Menunggu Persetujuan Atasan (waiting_supervisor)
            [
                'nama_template' => 'KPI Supervisor Pemeliharaan Mekanikal, Elektrikal & Proyek',
                'deskripsi' => 'Pengawasan instalasi listrik, pendingin udara, generator set, dan perbaikan sarana fisik wahana.',
                'departemen' => 'Proyek',
                'jabatan_name' => 'Teknisi MEP',
                'creator_nik' => 'HPP25120147',
                'creator_id' => $fajarUser?->id,
                'supervisor_nik' => 'HPP25090039',
                'status' => 'waiting_supervisor',
                'hrd_approved_by' => null,
                'hrd_approved_at' => null,
                'supervisor_approved_at' => null,
                'items' => [
                    [
                        'nama_kpi' => 'Realisasi Jadwal Pemeliharaan Preventif Mesin Wahana (Preventive Maintenance)',
                        'deskripsi' => 'Kepatuhan pelaksanaan inspeksi berkala motor listrik, hidrolik, dan sistem darurat wahana.',
                        'target' => '100',
                        'satuan' => 'persen',
                        'bobot' => 35,
                        'formula_penilaian' => '(inspeksi preventif tuntas / total jadwal pemeliharaan) x 100',
                    ],
                    [
                        'nama_kpi' => 'Ketepatan Waktu Proyek Peremajaan & Modifikasi Wahana',
                        'deskripsi' => 'Penyelesaian pekerjaan renovasi atau pemasangan wahana baru sesuai timeline proyek.',
                        'target' => '95',
                        'satuan' => 'persen',
                        'bobot' => 30,
                        'formula_penilaian' => '(pekerjaan tuntas tepat jadwal / target tahapan proyek) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kepatuhan Standar Keselamatan Kerja Proyek & APD Lapangan',
                        'deskripsi' => 'Ketaatan tim teknisi dan kontraktor dalam penggunaan alat pelindung diri (Helm, Sepatu, Harness).',
                        'target' => '100',
                        'satuan' => 'skor',
                        'bobot' => 20,
                        'formula_penilaian' => 'Nol pelanggaran keselamatan kerja K3 selama pengerjaan proyek',
                    ],
                    [
                        'nama_kpi' => 'Kelengkapan Berita Acara Pemeriksaan (BAP) Pekerjaan Rekanan',
                        'deskripsi' => 'Pengesahan dokumen serah terima hasil pekerjaan vendor MEP secara tuntas dan rapi.',
                        'target' => '100',
                        'satuan' => 'dokumen',
                        'bobot' => 15,
                        'formula_penilaian' => '(berkas BAP lengkap bertanda tangan / total pekerjaan rekanan) x 100',
                    ],
                ],
                'assign_niks' => [],
            ],

            // 10. GM - Status: Draft
            [
                'nama_template' => 'KPI Standar Pelayanan Tamu, Hospitality & Service Recovery',
                'deskripsi' => 'Indikator kepuasan pelanggan tingkat enterprise, penanganan keluhan cepat, dan inisiatif hospitality di seluruh titik sentuh pengunjung.',
                'departemen' => 'GM',
                'jabatan_name' => null,
                'creator_nik' => 'HPP25120147',
                'creator_id' => $fajarUser?->id,
                'supervisor_nik' => 'HPP25090039',
                'status' => 'draft',
                'hrd_approved_by' => null,
                'hrd_approved_at' => null,
                'supervisor_approved_at' => null,
                'items' => [
                    [
                        'nama_kpi' => 'Penyelesaian Keluhan Pengunjung Cepat & Service Recovery',
                        'deskripsi' => 'Tingkat kepuasan pengunjung atas solusi pemulihan pelayanan saat terjadi komplain.',
                        'target' => '90',
                        'satuan' => 'persen',
                        'bobot' => 35,
                        'formula_penilaian' => '(keluhan tuntas dan pengunjung puas / total komplain masuk) x 100',
                    ],
                    [
                        'nama_kpi' => 'Kepatuhan Standar Grooming, Greeting & Hospitality Frontliner',
                        'deskripsi' => 'Hasil evaluasi mystery guest terhadap etika penyambutan dan kerapihan seragam staf.',
                        'target' => '95',
                        'satuan' => 'skor',
                        'bobot' => 30,
                        'formula_penilaian' => 'Skor rata-rata inspeksi misteri dan kunjungan mendadak',
                    ],
                    [
                        'nama_kpi' => 'Inisiatif Program Kejutan Pelanggan (Surprise & Delight Program)',
                        'deskripsi' => 'Pelaksanaan program kejutan apresiasi bagi pengunjung berulang atau anak yang berulang tahun.',
                        'target' => '4',
                        'satuan' => 'jumlah',
                        'bobot' => 20,
                        'formula_penilaian' => 'Jumlah momen apresiasi tamu yang terdokumentasi per bulan',
                    ],
                    [
                        'nama_kpi' => 'Kecepatan Respon Pertanyaan Informasi via WhatsApp Center',
                        'deskripsi' => 'Waktu tanggap admin layanan pelanggan terhadap pertanyaan jam buka, harga, dan event.',
                        'target' => '5',
                        'satuan' => 'hari',
                        'bobot' => 15,
                        'formula_penilaian' => 'Rata-rata respon chat masuk < 5 menit pada jam operasional',
                    ],
                ],
                'assign_niks' => [],
            ],
        ];

        $period = PerformancePeriod::query()->where('status', 'active')->first()
            ?: PerformancePeriod::query()->first();

        foreach ($templatesData as $tData) {
            $masterJabatanId = null;
            if (! empty($tData['jabatan_name'])) {
                $masterJabatanId = MasterJabatan::query()
                    ->where('nama_jabatan', $tData['jabatan_name'])
                    ->value('id');
            }

            $template = KpiTemplate::query()->updateOrCreate(
                ['nama_template' => $tData['nama_template']],
                [
                    'deskripsi' => $tData['deskripsi'],
                    'departemen' => $tData['departemen'],
                    'master_jabatan_id' => $masterJabatanId,
                    'created_by' => $tData['creator_id'] ?: $defaultUser?->id,
                    'updated_by' => $tData['creator_id'] ?: $defaultUser?->id,
                    'creator_nik' => $tData['creator_nik'],
                    'supervisor_nik' => $tData['supervisor_nik'],
                    'status' => $tData['status'],
                    'hrd_approved_by' => $tData['hrd_approved_by'],
                    'hrd_approved_at' => $tData['hrd_approved_at'],
                    'supervisor_approved_at' => $tData['supervisor_approved_at'],
                    'supervisor_notes' => $tData['supervisor_notes'] ?? null,
                    'total_bobot' => collect($tData['items'])->sum('bobot'),
                    'is_active' => true,
                ]
            );

            // Re-create items for this template
            $template->items()->delete();
            foreach ($tData['items'] as $index => $itemData) {
                $template->items()->create([
                    'master_jabatan_id' => $masterJabatanId,
                    'nama_kpi' => $itemData['nama_kpi'],
                    'deskripsi' => $itemData['deskripsi'],
                    'target' => $itemData['target'],
                    'satuan' => $itemData['satuan'],
                    'bobot' => $itemData['bobot'],
                    'formula_penilaian' => $itemData['formula_penilaian'] ?? null,
                    'urutan' => $index + 1,
                    'is_active' => true,
                    'created_by' => $tData['creator_id'] ?: $defaultUser?->id,
                    'updated_by' => $tData['creator_id'] ?: $defaultUser?->id,
                ]);
            }

            // Create assignments if template is approved and has assign_niks
            if ($template->status === 'approved' && ! empty($tData['assign_niks'])) {
                foreach ($tData['assign_niks'] as $empNik) {
                    if (Karyawan::query()->where('nik', $empNik)->exists()) {
                        KpiTemplateAssignment::query()->updateOrCreate(
                            [
                                'kpi_template_id' => $template->id,
                                'employee_nik' => $empNik,
                            ],
                            [
                                'performance_period_id' => $period?->id,
                                'assigned_by' => $template->created_by,
                                'assigned_at' => now(),
                                'status' => 'active',
                                'weight_percentage' => 100.00,
                                'notes' => 'Penugasan template KPI otomatis melalui seeder.',
                            ]
                        );
                    }
                }
            }
        }
    }
}
