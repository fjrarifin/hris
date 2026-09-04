<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HrisDatabaseQueryAgent
{
    public function __construct(
        private readonly AiEngineManager $aiEngine
    ) {}

    /**
     * Skema ringkas tabel database HRIS yang aman untuk di-query AI.
     */
    public function getSchemaContext(): string
    {
        return <<<SCHEMA
=== SKEMA TABEL DATABASE HRIS HOMPIMPLAY (MySQL) ===

1. Tabel: m_karyawan (Data profil seluruh karyawan kantor)
   - nik (VARCHAR, Primary Key)
   - nama_karyawan (VARCHAR)
   - jabatan (VARCHAR)
   - departement (VARCHAR)
   - status_karyawan (VARCHAR, contoh: 'Tetap', 'Kontrak', 'Probation', 'Resign')
   - join_date (DATE, tanggal bergabung)
   - pin (VARCHAR, PIN mesin fingerspot presensi)
   - atasan_langsung_nik (VARCHAR, NIK atasan langsung -> relasi ke m_karyawan.nik)
   - atasan_tidak_langsung_nik (VARCHAR, NIK atasan tidak langsung -> relasi ke m_karyawan.nik)
   - jenis_kelamin (VARCHAR, 'Laki-laki' / 'Perempuan')
   CATATAN: "bawahan saya" = semua baris m_karyawan WHERE atasan_langsung_nik = NIK atasan OR atasan_tidak_langsung_nik = NIK atasan.
   "atasan saya" = SELECT atasan_langsung_nik FROM m_karyawan WHERE nik = NIK saya, lalu JOIN balik.

2. Tabel: users (Data akun user login portal HRIS)
   - id (BIGINT, Primary Key)
   - username (VARCHAR, NIK karyawan -> relasi ke m_karyawan.nik)
   - name (VARCHAR, nama pengguna)
   - phone (VARCHAR, nomor telepon WhatsApp)
   - level (INT: 0 = IT Admin/Superadmin, 1 = Direksi, 2 = HRD, 3 = Staff)
   - allow_mobile_attendance (TINYINT: 1 = diizinkan absen mandiri via HP, 0 = tidak diizinkan / wajib fingerspot kantor)
   - is_active (TINYINT: 1 = akun aktif, 0 = nonaktif)

3. Tabel: t_kontrak_karyawan (Histori & masa aktif kontrak kerja)
   - nik (VARCHAR, relasi ke m_karyawan.nik)
   - start_date (DATE)
   - end_date (DATE)
   - status_kontrak (VARCHAR, contoh: 'AKTIF', 'Kontrak 1', 'Kontrak 2')
   CATATAN: Kontrak AKTIF SAAT INI = baris dengan start_date <= hari_ini <= end_date.
   Ambil baris terbaru per NIK (ORDER BY end_date DESC LIMIT 1). "Sisa masa kontrak" = DATEDIFF(end_date, hari_ini).

4. Tabel: employee_daily_schedules (Jadwal shift kerja harian karyawan)
   - karyawan_nik (VARCHAR, relasi ke m_karyawan.nik)
   - schedule_date (DATE, format YYYY-MM-DD)
   - schedule_category_id (BIGINT, relasi ke attendance_schedule_categories.id)
   - schedule_code (VARCHAR, contoh: 'OFFICE', 'OFF', 'PAGI', 'SIANG')
   - notes (TEXT)

5. Tabel: attendance_schedule_categories (Master kategori shift/jadwal kerja)
   - id (INT)
   - name (VARCHAR, nama shift/jadwal misal: 'Office', 'Shift Pagi', 'Shift Siang', 'Libur')
   - code (VARCHAR)

6. Tabel: fingerspot_attendance_logs (Log scan presensi aktual dari mesin fingerspot)
   - pin (VARCHAR, relasi ke m_karyawan.pin)
   - scan_date (DATETIME, waktu scan absensi)
   - status_scan (INT, 0 = Scan Masuk, 1 = Scan Pulang)
   CATATAN PENTING SOAL PERHITUNGAN:
   - "Sudah absen masuk hari ini?" = cek ada baris status_scan=0 pada DATE(scan_date)=hari_ini.
   - "Total kehadiran periode ini" = COUNT(DISTINCT DATE(scan_date)) WHERE status_scan = 0
     (dihitung dari hari yang ADA scan masuk dalam rentang tanggal periode berjalan).
   - PERIODE HRIS HOMPIMPLAY berjalan dari TANGGAL 25 s/d TANGGAL 24 bulan berikutnya.

7. Tabel: attendance_corrections (Pengajuan koreksi absensi)
   - karyawan_nik (VARCHAR)
   - correction_date (DATE)
   - corrected_scan_in (TIME)
   - corrected_scan_out (TIME)
   - reason (TEXT)
   - status (VARCHAR: 'pending', 'approved', 'rejected')

8. Tabel: leave_accruals (Master Akumulasi & Saldo Cuti Tahunan Karyawan)
   - id (BIGINT, Primary Key)
   - user_id (BIGINT, relasi ke users.id)
   - nik (VARCHAR, relasi ke m_karyawan.nik)
   - days (INT, jumlah hari cuti yang di-accrue, default 1)
   - accrued_at (DATE, tanggal penambahan saldo)
   - expired_at (DATE, tanggal kedaluwarsa saldo cuti -> mengikuti end_date kontrak aktif)
   - is_used (TINYINT: 0 = belum dipakai / aktif, 1 = sudah terpakai)
   - notes (TEXT)
   CATATAN: Saldo cuti aktif yang bisa diambil = (SELECT COALESCE(SUM(days), 0) FROM leave_accruals WHERE user_id = u.id AND expired_at >= hari_ini AND is_used = 0) - (SELECT COALESCE(SUM(total_days), 0) FROM leave_requests WHERE user_id = u.id AND status IN ('approved', 'pending')).

9. Tabel: leave_requests (Pengajuan cuti tahunan / cuti khusus)
   - user_id (INT, relasi ke users.id di mana users.username = m_karyawan.nik)
   - start_date (DATE)
   - end_date (DATE)
   - total_days (INT)
   - reason (TEXT)
   - status (VARCHAR: 'pending', 'approved', 'rejected', 'cancelled')
   CATATAN: "Riwayat pengajuan cuti" = list start_date-end_date WHERE status IN ('pending','approved') diurutkan terbaru.

10. Tabel: employee_permissions (Pengajuan izin / sakit / dispensasi)
    - user_id (BIGINT, relasi ke users.id)
    - date (DATE, tanggal mulai)
    - end_date (DATE, tanggal selesai)
    - type (VARCHAR: 'sakit', 'izin', 'dispensasi')
    - reason (TEXT, alasan permohonan)
    - status (VARCHAR: 'pending', 'approved', 'rejected')

11. Tabel: overtime_requests (Pengajuan lembur / Surat Perintah Lembur / SPL)
    - id (BIGINT, Primary Key)
    - user_id (BIGINT, ID user karyawan yang ditugaskan lembur -> relasi ke users.id -> users.username = m_karyawan.nik)
    - requested_by_user_id (BIGINT, ID user atasan/supervisor yang mengajukan lembur -> relasi ke users.id -> users.username = m_karyawan.nik)
    - date (DATE, tanggal lembur)
    - start_time (TIME, jam mulai lembur)
    - end_time (TIME, jam selesai lembur)
    - reason (TEXT, uraian tugas / alasan lembur)
    - status (VARCHAR: 'pending', 'approved', 'rejected')
    - reject_reason (TEXT)
    CATATAN: "Total jam lembur periode ini" = ROUND(SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time)))/3600, 2)
    HANYA untuk status = 'approved', dalam rentang tanggal periode berjalan.

12. Tabel: public_holidays (Daftar Hari Libur Nasional)
    - id (INT, Primary Key)
    - name (VARCHAR, nama hari libur)
    - holiday_date (DATE)
    - is_active (TINYINT: 1 = aktif)

13. Tabel: public_holiday_requests (Klaim libur pengganti Public Holiday / PH)
    - user_id (INT, relasi ke users.id)
    - public_holiday_id (INT, relasi ke public_holidays.id)
    - claim_date (DATE, tanggal libur pengganti yang diambil)
    - status (VARCHAR: 'pending', 'approved', 'rejected')

14. Tabel: employee_ph_adjustments (Penyesuaian Saldo PH manual oleh HRD)
    - karyawan_nik (VARCHAR, relasi ke m_karyawan.nik)
    - public_holiday_id (BIGINT, nullable)
    - days (INT, jumlah penyesuaian: positif = tambah, negatif = potong)
    - notes (TEXT)

15. Tabel: employee_extra_offs (Saldo Extra Off karyawan per periode kompensasi)
    - karyawan_nik (VARCHAR, relasi ke m_karyawan.nik)
    - periode_start (DATE)
    - periode_end (DATE)
    - days (INT, jumlah hari EO yang didapat pada periode tsb)
    - notes (TEXT)
    CATATAN: Masa berlaku EO = periode_end + 3 bulan (90 hari). Sisa EO = total days - total extra_off_requests approved.

16. Tabel: extra_off_requests (Klaim libur Extra Off / EO)
    - user_id (INT, relasi ke users.id)
    - claim_date (DATE)
    - source_period_start (DATE)
    - source_period_end (DATE)
    - status (VARCHAR: 'pending', 'approved', 'rejected')

17. Tabel: gate_qr_usage_logs (Riwayat pembukaan QR Code Gate / Turnstile masuk kantor)
    - user_id (BIGINT, relasi ke users.id)
    - nik (VARCHAR)
    - employee_name (VARCHAR)
    - reason (TEXT, alasan membuka QR gate misal kartu RFID tertinggal)
    - used_at (DATETIME)

18. Tabel: master_departments (Master Departemen kantor)
    - id (INT)
    - name (VARCHAR, nama departemen)

19. Tabel: master_divisions (Master Divisi kantor)
    - id (INT)
    - name (VARCHAR, nama divisi)

20. Tabel: master_jabatans (Master Jabatan kantor)
    - id (INT)
    - name (VARCHAR, nama jabatan)

21. Tabel: event_absen (Master Event Kantor untuk absensi acara/kegiatan khusus)
    - id (INT)
    - nama_event (VARCHAR)
    - tgl_event (DATE)
    - waktu_mulai (TIME)
    - waktu_selesai (TIME)
    - lokasi (VARCHAR)

22. Tabel: absensi_event (Data kehadiran karyawan pada event acara kantor)
    - event_id (INT, relasi ke event_absen.id)
    - karyawan_nik (VARCHAR)
    - scan_time (DATETIME)
    - status (VARCHAR)
SCHEMA;
    }

    /**
     * Basis pengetahuan SOP & alur bisnis resmi HRIS HomPim Play.
     */
    public function getBusinessRulesContext(): string
    {
        $kbPath = resource_path('knowledge/hris_employee_manual.md');
        if (file_exists($kbPath)) {
            return Cache::remember('hris_employee_manual_kb', 3600, function () use ($kbPath) {
                return file_get_contents($kbPath);
            });
        }

        return <<<RULES
=== SOP & ALUR BISNIS HRIS HOMPIM PLAY ===

1. PERIODE ABSENSI & CUTOFF PAYROLL
   - Periode berjalan setiap tanggal 25 bulan sebelumnya s/d tanggal 24 bulan ini (BUKAN kalender 1-30/31).
   - Semua perhitungan "kehadiran bulan ini", "total jam lembur bulan ini", dan rekapitulasi penggajian mengacu pada rentang cutoff 25 s/d 24.

2. ALUR PENGAJUAN CUTI TAHUNAN & CUTI KHUSUS
   - Diajukan mandiri oleh staf via portal https://hr.hompimplay.id pada menu "Pengajuan Cuti".
   - Alur Approval: Diajukan Karyawan -> Disetujui Atasan Langsung (Supervisor/Manager) -> Diverifikasi & Disahkan HRD.
   - Status pengajuan: pending (menunggu approval), approved (disetujui), rejected (ditolak), cancelled (dibatalkan).
   - Hak cuti tahunan: Baru mulai aktif setelah genap 1 tahun (12 bulan) masa kerja dari join_date. Setelah 1 tahun, saldo bertambah otomatis +1 hari per bulan (tercatat di tabel leave_accruals).
   - Tanggal Kedaluwarsa (Expired): Seluruh saldo cuti tahunan aktif memiliki masa berlaku mengikuti tanggal berakhir kontrak kerja aktif (end_date kontrak).

3. ALUR PUBLIC HOLIDAY (PH)
   - Hak PH diperoleh jika karyawan masuk bekerja (ada log scan fingerspot) pada hari libur nasional resmi kalender kantor (public_holidays).
   - Kompensasi: 1 hari masuk di tanggal merah menghasilkan 1 hari hak libur pengganti PH yang berlaku selama 90 hari (3 bulan).
   - Klaim libur PH diajukan di portal HRIS pada menu "Pengajuan PH" dan disetujui Atasan Langsung & HRD.

4. ALUR EXTRA OFF (EO)
   - Saldo Extra Off diberikan oleh HRD dari akumulasi kelebihan jam kerja bulanan (tercatat di employee_extra_offs).
   - Masa berlaku saldo Extra Off adalah 3 bulan sejak akhir periode pemberian.
   - Klaim libur Extra Off diajukan melalui menu "Pengajuan Extra Off" di portal HRIS.

5. ALUR PENGAJUAN LEMBUR (SPL / SURAT PERINTAH LEMBUR)
   - Lembur HANYA bisa diajukan oleh ATASAN LANGSUNG (Supervisor/Manager) untuk menugaskan bawahannya melalui menu "Atasan -> Pengajuan Lembur".
   - Staf biasa tidak memiliki menu pengajuan lembur mandiri (harus ditugaskan atasan).
   - Durasi lembur yang diizinkan sistem adalah 1 sampai 4 jam per hari.
   - Status SPL diverifikasi dan disetujui oleh tim HRD. Hanya lembur berstatus 'approved' yang masuk hitungan lembur resmi.

6. ABSENSI MANDIRI VIA HANDPHONE (MOBILE ATTENDANCE)
   - Syarat: Karyawan harus memiliki izin akses dari admin HR/IT (kolom users.allow_mobile_attendance = 1).
   - Jika allow_mobile_attendance = 0: Karyawan WAJIB melakukan presensi tap RFID / sidik jari di mesin Fingerspot kantor.
   - Langkah Absen HP: Login ke https://hr.hompimplay.id lewat browser HP -> Buka menu "Presensi Mobile" -> Izinkan akses Kamera dan Lokasi (GPS) -> Ambil foto selfie di lokasi kerja -> Klik tombol "Kirim Absensi".

7. AKSES QR CODE UNTUK GATE TURNSTILE / PINTU MASUK
   - Digunakan sebagai alternatif darurat jika kartu RFID fisik karyawan tertinggal atau rusak.
   - Langkah Akses: Login ke portal HRIS -> Klik menu "QR Gate / Barcode Gate" di dashboard/header -> Wajib mengisi alasan singkat penggunaan (contoh: "Kartu RFID tertinggal di rumah", minimal 5 karakter) -> Klik "Generate QR Gate" -> Arahkan layar QR Code ke scanner barcode gate turnstile.
   - Notifikasi penggunaan QR gate akan otomatis terkirim secara real-time ke Tim HRD.

8. KONTRAK KERJA & EVALUASI
   - Kontrak kerja tercatat di tabel t_kontrak_karyawan. Kontrak aktif ditandai dengan tanggal hari ini berada di antara start_date dan end_date.
   - Karyawan dan atasan dapat memantau sisa hari kontrak untuk persiapan evaluasi perpanjangan kontrak kerja.

9. HIERARKI ATASAN & BAWAHAN
   - "Tim saya" / "Bawahan saya" = Karyawan yang kolom atasan_langsung_nik atau atasan_tidak_langsung_nik berisi NIK atasan yang bertanya.
   - Atasan memiliki akses untuk memonitor kehadiran harian tim, rekapitulasi lembur bawahan, dan menyetujui pengajuan cuti/PH/EO timnya.
RULES;
    }

    /**
     * Hitung batas periode HRIS (tanggal 25 s/d tanggal 24 bulan berikutnya)
     * berdasarkan tanggal hari ini. Dihitung di PHP supaya presisi.
     *
     * @return array{start: string, end: string, label: string}
     */
    private function computePeriod(string $today): array
    {
        $date = \Carbon\Carbon::parse($today);

        if ((int) $date->format('d') >= 25) {
            $start = $date->copy()->startOfMonth()->addDays(24); // tanggal 25 bulan ini
        } else {
            $start = $date->copy()->subMonthNoOverflow()->startOfMonth()->addDays(24); // tanggal 25 bulan lalu
        }

        $end = $start->copy()->addMonthNoOverflow()->subDay(); // tanggal 24 bulan berikutnya

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $start->locale('id')->translatedFormat('d M Y') . ' - ' . $end->locale('id')->translatedFormat('d M Y'),
        ];
    }

    /**
     * Memproses pertanyaan bebas user, menghasilkan SQL SELECT yang aman, mengeksekusi, dan merangkum jawaban.
     */
    public function queryAndAnswer(string $question, ?Karyawan $karyawan, array $history = [], int $userLevel = 3): ?string
    {
        $today = now()->toDateString();
        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";
        $periode = $this->computePeriod($today);

        // 1. Minta AI menghasilkan query SQL SELECT
        $sql = $this->generateSql($question, $karyawan, $today, $periode, $history, $userLevel);

        // 1b. Kalau AI bilang NONE (pertanyaan SOP/prosedural, tidak butuh query data),
        //     jawab langsung menggunakan basis pengetahuan SOP resmi.
        if ($sql === null || trim($sql) === '' || strtoupper(trim($sql)) === 'NONE') {
            return $this->answerFromKnowledgeBase($question, $karyawan, $sapaan, $userLevel);
        }

        // 2. Validasi Keamanan SQL (Strict Read-Only & Privacy Guard)
        $securityCheck = $this->validateSqlSafety($sql, $karyawan?->nik ?? '', $userLevel);
        if (! $securityCheck['safe']) {
            Log::warning("SQL Security Guard Blocked Query: {$sql}", ['reason' => $securityCheck['reason']]);
            return $this->answerFromKnowledgeBase($question, $karyawan, $sapaan, $userLevel);
        }

        $cleanSql = $securityCheck['sql'];

        // 3. Eksekusi Query ke Database (Read-Only)
        try {
            $results = DB::select($cleanSql);
        } catch (Throwable $e) {
            Log::warning("Gagal eksekusi Read-Only SQL AI: {$cleanSql}", ['error' => $e->getMessage()]);
            return $this->answerFromKnowledgeBase($question, $karyawan, $sapaan, $userLevel);
        }

        // 4. Minta AI merangkum hasil data ke bahasa WhatsApp yang ramah dan profesional
        return $this->summarizeResult($question, $cleanSql, $results, $karyawan, $sapaan, $userLevel, $periode);
    }

    private function generateSql(string $question, ?Karyawan $karyawan, string $today, array $periode, array $history = [], int $userLevel = 3): ?string
    {
        $schema = $this->getSchemaContext();
        $nik = $karyawan?->nik ?? '';
        $pin = $karyawan?->pin ?? '';
        $nama = $karyawan?->nama_karyawan ?? '';
        $periodeStart = $periode['start'];
        $periodeEnd = $periode['end'];

        $roleDescription = match ($userLevel) {
            0 => "Level 0: IT Administrator / Superadmin (Akses Penuh Seluruh Data Database & Seluruh Karyawan)",
            1 => "Level 1: Direksi / Top Management (Akses Penuh Seluruh Data Perusahaan)",
            2 => "Level 2: HRD / HRGA Management (Akses Seluruh Data Kepegawaian & Seluruh Karyawan Kantor)",
            default => "Level 3: Karyawan / Staff (Akses Data Diri Sendiri & Data Tim Bawahannya)",
        };

        $prompt = "Kamu adalah Database Text-to-SQL Engine untuk sistem HRIS HomPim Play.\n";
        $prompt .= "Tugasmu: Buat 1 query MySQL SELECT yang tepat, efisien, dan valid untuk menjawab pertanyaan pengguna.\n\n";
        $prompt .= "{$schema}\n\n";
        $prompt .= "Identitas & Wewenang Pengguna yang Bertanya:\n";
        $prompt .= "- NIK Pengguna: {$nik}\n";
        $prompt .= "- Nama: {$nama}\n";
        $prompt .= "- PIN Fingerspot: {$pin}\n";
        $prompt .= "- Hak Akses / Privilege: {$roleDescription}\n";
        $prompt .= "- Tanggal Hari Ini: {$today}\n";
        $prompt .= "- Periode HRIS Berjalan (tanggal 25 s/d 24): {$periodeStart} s/d {$periodeEnd} (Gunakan literal ini untuk query periode berjalan)\n\n";

        $prompt .= "Aturan SQL Generator & Hak Akses (RBAC):\n";
        $prompt .= "1. HANYA buat query SELECT (Dilarang keras INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE).\n";
        if ($userLevel <= 2) {
            $prompt .= "2. HAK AKSES TINGGI (Admin / HRD):\n";
            $prompt .= "   - Jika menanyakan data global kantor (total hadir hari ini, siapa yang telat, rekap divisi), buat query global tanpa terkunci NIK pribadi.\n";
            $prompt .= "   - Jika menanyakan karyawan tertentu ('cek absensi Feriansyah', 'sisa cuti Rendra'), cari NIK/PIN orang tersebut via JOIN atau WHERE nama_karyawan LIKE.\n";
            $prompt .= "   - Jika menanyakan data pribadi ('jadwal saya', 'absen saya'), filter WHERE nik = '{$nik}' atau WHERE pin = '{$pin}'.\n";
        } else {
            $prompt .= "2. HAK AKSES STAFF BIASA (Level 3):\n";
            $prompt .= "   - Data pribadi: WAJIB mengunci data diri sendiri (WHERE nik = '{$nik}' atau WHERE pin = '{$pin}').\n";
            $prompt .= "   - Data tim/bawahan: JIKA menanyakan 'tim saya' / 'bawahan saya' / nama bawahan tertentu, BOLEH query karyawan lain TAPI WAJIB mengunci WHERE atasan_langsung_nik = '{$nik}' OR atasan_tidak_langsung_nik = '{$nik}'.\n";
            $prompt .= "   - DILARANG membuka data karyawan yang bukan bawahan langsungnya, atau rekapitulasi seluruh kantor.\n";
        }
        $prompt .= "3. Jangan pernah query kolom sensitif seperti password/token.\n";
        $prompt .= "4. Output HARUS murni berupa string query SQL saja tanpa penjelasan, tanpa markdown codeblock (tanpa ```sql).\n";
        $prompt .= "5. Jika pertanyaan adalah SOP/prosedural murni yang tidak memerlukan query data (misal: 'cara akses QR gate gimana', 'syarat dapat cuti apa'), jawab tepat satu kata: NONE\n\n";

        $prompt .= "Contoh Pola Query Berdasarkan Domain Pertanyaan:\n\n";

        $prompt .= "[1. Presensi Harian]\n";
        $prompt .= "- 'saya tadi udah absen masuk belum ya?' -> SELECT scan_date, status_scan FROM fingerspot_attendance_logs WHERE pin = '{$pin}' AND DATE(scan_date) = '{$today}' ORDER BY scan_date ASC LIMIT 1\n";
        $prompt .= "- 'saya tadi absen pulang jam berapa?' -> SELECT scan_date, status_scan FROM fingerspot_attendance_logs WHERE pin = '{$pin}' AND DATE(scan_date) = '{$today}' AND status_scan = 1 ORDER BY scan_date DESC LIMIT 1\n\n";

        $prompt .= "[2. Rekap Kehadiran Periode Berjalan (Cutoff 25 - 24)]\n";
        $prompt .= "- 'total kehadiran periode ini udah berapa hari' -> SELECT COUNT(DISTINCT DATE(scan_date)) AS total_hari_hadir FROM fingerspot_attendance_logs WHERE pin = '{$pin}' AND status_scan = 0 AND DATE(scan_date) BETWEEN '{$periodeStart}' AND '{$periodeEnd}'\n\n";

        $prompt .= "[3. Saldo & Riwayat Cuti, PH, Extra Off]\n";
        $prompt .= "- 'saldo cuti saya sisa berapa' -> SELECT (SELECT COALESCE(SUM(days), 0) FROM leave_accruals WHERE nik = '{$nik}' AND expired_at >= '{$today}' AND is_used = 0) - (SELECT COALESCE(SUM(total_days), 0) FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE u.username = '{$nik}' AND l.status IN ('approved', 'pending')) AS sisa_cuti_aktif, (SELECT expired_at FROM leave_accruals WHERE nik = '{$nik}' AND expired_at >= '{$today}' AND is_used = 0 ORDER BY expired_at ASC LIMIT 1) AS expired_terdekat\n";
        $prompt .= "- 'saya pernah ngajuin cuti tanggal berapa aja' -> SELECT l.start_date, l.end_date, l.total_days, l.status, l.reason FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE u.username = '{$nik}' ORDER BY l.start_date DESC\n";
        $prompt .= "- 'saldo extra off saya sisa berapa' -> SELECT (SELECT COALESCE(SUM(days), 0) FROM employee_extra_offs WHERE karyawan_nik = '{$nik}' AND DATE_ADD(periode_end, INTERVAL 90 DAY) >= '{$today}') - (SELECT COALESCE(COUNT(*), 0) FROM extra_off_requests e JOIN users u ON e.user_id = u.id WHERE u.username = '{$nik}' AND e.status = 'approved') AS sisa_eo\n";
        $prompt .= "- 'pengajuan PH saya yang masih pending' -> SELECT p.claim_date, p.status, h.name AS nama_holiday FROM public_holiday_requests p JOIN users u ON p.user_id = u.id JOIN public_holidays h ON p.public_holiday_id = h.id WHERE u.username = '{$nik}' AND p.status = 'pending' ORDER BY p.claim_date DESC\n\n";

        $prompt .= "[4. Lembur / SPL]\n";
        $prompt .= "- 'saya pernah ditugaskan lembur tanggal berapa aja & jam berapa' -> SELECT o.date, o.start_time, o.end_time, o.status, o.reason, req_k.nama_karyawan AS ditugaskan_oleh FROM overtime_requests o JOIN users u ON o.user_id = u.id JOIN users req ON o.requested_by_user_id = req.id JOIN m_karyawan req_k ON req.username = req_k.nik WHERE u.username = '{$nik}' ORDER BY o.date DESC\n";
        $prompt .= "- 'total lembur periode ini udah berapa jam' -> SELECT ROUND(SUM(TIME_TO_SEC(TIMEDIFF(o.end_time, o.start_time)))/3600, 2) AS total_jam_lembur FROM overtime_requests o JOIN users u ON o.user_id = u.id WHERE u.username = '{$nik}' AND o.status = 'approved' AND o.date BETWEEN '{$periodeStart}' AND '{$periodeEnd}'\n";
        $prompt .= "- 'lembur yang saya ajukan untuk Dindin / bawahan' -> SELECT o.date, o.start_time, o.end_time, o.status, o.reason, k.nama_karyawan AS nama_bawahan FROM overtime_requests o JOIN users u ON o.user_id = u.id JOIN m_karyawan k ON u.username = k.nik JOIN users req ON o.requested_by_user_id = req.id WHERE req.username = '{$nik}' AND k.nama_karyawan LIKE '%Dindin%' ORDER BY o.date DESC\n\n";

        $prompt .= "[5. Kontrak Kerja]\n";
        $prompt .= "- 'kontrak saya masih lama ga' -> SELECT status_kontrak, start_date, end_date, DATEDIFF(end_date, '{$today}') AS sisa_hari FROM t_kontrak_karyawan WHERE nik = '{$nik}' ORDER BY end_date DESC LIMIT 1\n";
        if ($userLevel <= 2) {
            $prompt .= "- (Admin/HR) 'kontrak yang mau habis bulan ini semua karyawan' -> SELECT k.nama_karyawan, tk.end_date, DATEDIFF(tk.end_date, '{$today}') AS sisa_hari FROM t_kontrak_karyawan tk JOIN m_karyawan k ON tk.nik = k.nik WHERE tk.end_date BETWEEN '{$today}' AND DATE_ADD('{$today}', INTERVAL 30 DAY) ORDER BY tk.end_date ASC\n";
        } else {
            $prompt .= "- 'kontrak bawahan saya [nama] masih lama ga' -> SELECT k.nama_karyawan, tk.end_date, DATEDIFF(tk.end_date, '{$today}') AS sisa_hari FROM t_kontrak_karyawan tk JOIN m_karyawan k ON tk.nik = k.nik WHERE k.atasan_langsung_nik = '{$nik}' AND k.nama_karyawan LIKE '%NAMA%' ORDER BY tk.end_date DESC LIMIT 1\n";
        }
        $prompt .= "\n";

        $prompt .= "[6. Pengajuan & Kehadiran Tim / Bawahan]\n";
        $prompt .= "- 'tim saya ada pengajuan cuti/PH/EO/izin ga' -> SELECT k.nama_karyawan, l.start_date, l.end_date, l.status, 'Cuti' AS tipe FROM leave_requests l JOIN users u ON l.user_id = u.id JOIN m_karyawan k ON u.username = k.nik WHERE k.atasan_langsung_nik = '{$nik}' ORDER BY l.start_date DESC\n";
        $prompt .= "- 'total kehadiran [nama bawahan] periode ini udah berapa' -> SELECT k.nama_karyawan, COUNT(DISTINCT DATE(f.scan_date)) AS total_hadir FROM m_karyawan k LEFT JOIN fingerspot_attendance_logs f ON k.pin = f.pin AND f.status_scan = 0 AND DATE(f.scan_date) BETWEEN '{$periodeStart}' AND '{$periodeEnd}' WHERE k.atasan_langsung_nik = '{$nik}' AND k.nama_karyawan LIKE '%NAMA%' GROUP BY k.nik, k.nama_karyawan\n\n";

        $prompt .= "[7. Fitur Khusus: Status Absen HP & QR Gate]\n";
        $prompt .= "- 'apakah saya bisa absen mandiri via HP' -> SELECT allow_mobile_attendance FROM users WHERE username = '{$nik}'\n";
        $prompt .= "- 'riwayat penggunaan QR gate saya' -> SELECT reason, used_at FROM gate_qr_usage_logs WHERE nik = '{$nik}' ORDER BY used_at DESC LIMIT 10\n\n";

        if ($userLevel <= 2) {
            $prompt .= "[8. Rekap Global Kantor - Khusus Admin/HR]\n";
            $prompt .= "- 'total yang hadir hari ini' -> SELECT COUNT(DISTINCT pin) AS total_hadir FROM fingerspot_attendance_logs WHERE status_scan = 0 AND DATE(scan_date) = '{$today}'\n";
            $prompt .= "- 'rekap kehadiran divisi play hari ini' -> SELECT k.nama_karyawan, MIN(f.scan_date) AS scan_in, MAX(f.scan_date) AS scan_out FROM m_karyawan k LEFT JOIN fingerspot_attendance_logs f ON k.pin = f.pin AND DATE(f.scan_date) = '{$today}' WHERE k.departement LIKE '%Play%' GROUP BY k.nik, k.nama_karyawan\n";
            $prompt .= "- 'riwayat lembur semua karyawan' -> SELECT o.date, o.start_time, o.end_time, o.status, o.reason, k.nama_karyawan AS nama_karyawan, req_k.nama_karyawan AS diajukan_oleh FROM overtime_requests o JOIN users u ON o.user_id = u.id JOIN m_karyawan k ON u.username = k.nik JOIN users req ON o.requested_by_user_id = req.id JOIN m_karyawan req_k ON req.username = req_k.nik ORDER BY o.date DESC\n\n";
        }

        $prompt .= "Pertanyaan Pengguna: \"{$question}\"";

        $sql = $this->callLlm($prompt);
        if (! $sql || trim($sql) === '' || strtoupper(trim($sql)) === 'NONE') {
            return null;
        }

        if (preg_match('/(SELECT\s+[\s\S]+?)(?:;|\n\n|```|$)/i', $sql, $matches)) {
            $cleanSql = trim($matches[1]);
        } else {
            $cleanSql = trim(preg_replace('/^```sql\s*|^```\s*|```$/m', '', $sql));
        }

        $cleanSql = trim($cleanSql, "; \n\r\t`");

        return $cleanSql;
    }

    private function validateSqlSafety(string $sql, string $authenticatedNik, int $userLevel = 3): array
    {
        $trimmed = trim($sql);
        $upper = strtoupper($trimmed);

        // Wajib berawalan SELECT
        if (! str_starts_with($upper, 'SELECT') && ! str_starts_with($upper, 'WITH')) {
            return ['safe' => false, 'reason' => 'Query must start with SELECT'];
        }

        // Blacklist kata kunci berbahaya
        $dangerKeywords = [
            'INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'TRUNCATE ', 'REPLACE ',
            'CREATE ', 'GRANT ', 'REVOKE ', 'INFORMATION_SCHEMA', 'BENCHMARK', 'SLEEP',
            'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE', 'PROCEDURE', 'EXECUTE', 'PREPARE'
        ];

        foreach ($dangerKeywords as $danger) {
            if (str_contains($upper, $danger)) {
                return ['safe' => false, 'reason' => "Dangerous keyword detected: {$danger}"];
            }
        }

        // Blacklist kolom sensitif
        $sensitiveColumns = [
            'PASSWORD', 'REMEMBER_TOKEN', 'SALARY', 'GAJI', 'THP', 'NOMINAL',
            'REKENING', 'TOKEN', 'SECRET', 'API_KEY'
        ];

        foreach ($sensitiveColumns as $sensitive) {
            if (preg_match('/\b' . $sensitive . '\b/i', $upper)) {
                return ['safe' => false, 'reason' => "Sensitive column access blocked: {$sensitive}"];
            }
        }

        // Guard tambahan untuk Level 3 (staff biasa): wajib mengunci ke NIK sendiri atau hierarki bawahan
        if ($userLevel >= 3 && $authenticatedNik !== '') {
            $mentionsOwnNik = str_contains($trimmed, $authenticatedNik);
            $mentionsHierarchy = str_contains($upper, 'ATASAN_LANGSUNG_NIK') || str_contains($upper, 'ATASAN_TIDAK_LANGSUNG_NIK');
            if (! $mentionsOwnNik && ! $mentionsHierarchy) {
                return ['safe' => false, 'reason' => 'Level 3 query does not scope to own NIK or subordinate hierarchy'];
            }
        }

        // Tambahkan LIMIT 25 jika belum ada
        if (! preg_match('/\bLIMIT\s+\d+/i', $upper)) {
            $trimmed .= ' LIMIT 25';
        }

        return ['safe' => true, 'sql' => $trimmed];
    }

    private function summarizeResult(string $question, string $sql, array $results, ?Karyawan $karyawan, string $sapaan, int $userLevel = 3, array $periode = []): string
    {
        $jsonResults = json_encode(array_slice($results, 0, 25), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $joinDateStr = $karyawan?->join_date ? \Carbon\Carbon::parse($karyawan->join_date)->locale('id')->translatedFormat('d F Y') : '-';
        $periodeLabel = $periode['label'] ?? null;

        $roleContext = match ($userLevel) {
            0 => "IT Administrator (Level 0 / Superadmin)",
            1 => "Direksi / Manajemen (Level 1)",
            2 => "HRD / HRGA (Level 2)",
            default => "Karyawan / Staff (Level 3)",
        };

        $prompt = "Kamu adalah Haris, Staff IT dari tim Kak Fajar Arifin yang bertugas khusus menangani dan melayani sistem HRIS di HomPim Play.\n";
        $prompt .= "Pengguna atas nama {$sapaan} (Peran: {$roleContext}) menanyakan: \"{$question}\"\n";
        $prompt .= "Tanggal Bergabung Karyawan: {$joinDateStr}\n";
        if ($periodeLabel) {
            $prompt .= "Periode HRIS Berjalan: {$periodeLabel}\n";
        }
        $prompt .= "\nBerikut adalah data fakta hasil query database HRIS:\n";
        $prompt .= "```json\n{$jsonResults}\n```\n\n";
        $prompt .= "Konteks SOP & Alur Bisnis HRIS (Gunakan jika relevan untuk melengkapi penjelasan data di atas):\n";
        $prompt .= $this->getBusinessRulesContext() . "\n\n";

        $prompt .= "ATURAN GAYA BICARA HARIS (PROFESIONAL, TEPAT, FOKUS DATA & ANTI-HALUSINASI):\n";
        $prompt .= "1. DILARANG MEMBUKA DENGAN KALIMAT PERKENALAN BERULANG SEPERTI 'Halo Kak Fajar! Aku Haris... Ada yang bisa kubantu?'.\n";
        $prompt .= "2. JAWAB HANYA APA YANG DITANYAKAN (ZERO TANGENT): Langsung jawab to-the-point dan lugas tanpa basa-basi berlebih.\n";
        $prompt .= "3. JIKA DATA ADA DI HASIL QUERY: Sebutkan rinciannya secara jelas (angka saldo, tanggal, jam, alasan, status). JANGAN menyuruh user cek sendiri ke portal jika datanya sudah kamu peroleh!\n";
        $prompt .= "4. JIKA DATA KOSONG DI HASIL QUERY: Sampaikan secara jujur dan lugas bahwa data belum tercatat di sistem untuk kriteria/periode tersebut.\n";
        $prompt .= "5. DILARANG KERAS MENGHALUSINASI ATAU BERPURA-PURA (contoh: DILARANG mengatakan 'Haris sudah bantu refresh data / koneksi sistem', 'Haris bantu sinkronisasi', dll). Kamu adalah bot pemberi informasi data real-time, bukan sistem maintenance.\n";
        $prompt .= "6. DILARANG menyebutkan istilah teknis SQL/query/database/SELECT. Sampaikan datanya secara rapi dan natural.\n";
        $prompt .= "7. Panggil pengguna dengan '{$sapaan}'. Gunakan bahasa Indonesia yang santai, sopan, dan jelas.\n";
        $prompt .= "8. Jika pertanyaan menanyakan apakah bisa absen HP (allow_mobile_attendance), jelaskan status izinnya dan jika diizinkan jelaskan langkah-langkah ringkasnya.\n";

        $summary = $this->callLlm($prompt);

        return $summary ? trim($summary) : "Maaf ya {$sapaan}, data terkait pertanyaan tersebut belum tercatat di sistem HRIS.";
    }

    /**
     * Menjawab pertanyaan yang murni prosedural/SOP (tidak butuh query database),
     * misalnya alur pengajuan cuti, cara akses QR gate, atau absensi mandiri via HP.
     */
    private function answerFromKnowledgeBase(string $question, ?Karyawan $karyawan, string $sapaan, int $userLevel = 3): string
    {
        $businessRules = $this->getBusinessRulesContext();

        $prompt = "Kamu adalah Haris, Staff IT dari tim Kak Fajar Arifin yang bertugas khusus menangani dan melayani sistem HRIS di HomPim Play.\n";
        $prompt .= "Pengguna atas nama {$sapaan} menanyakan hal PROSEDURAL / SOP HRIS: \"{$question}\"\n\n";
        $prompt .= "Basis Pengetahuan SOP & Alur Bisnis HRIS HomPim Play:\n";
        $prompt .= "{$businessRules}\n\n";
        $prompt .= "ATURAN JAWAB:\n";
        $prompt .= "1. Jawab secara akurat, runtut, dan jelas berdasarkan basis pengetahuan di atas.\n";
        $prompt .= "2. DILARANG membuka kalimat dengan perkenalan berulang ('Halo Kak, aku Haris...').\n";
        $prompt .= "3. Jawab ringkas, to-the-point, dengan bahasa Indonesia santai, ramah, dan sopan.\n";
        $prompt .= "4. Panggil pengguna dengan '{$sapaan}'.\n";
        $prompt .= "5. PENTING: Jika topik pertanyaan tersebut sama sekali TIDAK ADA dan BELUM TERCANTUM dalam basis pengetahuan di atas: Katakan terus terang secara ramah bahwa informasi tersebut belum tercatat di panduan Haris, dan WAJIB bubuhkan penanda [UNRESOLVED] di paling akhir jawabanmu agar otomatis dicatat sistem ke memori evaluasi tim HRD/IT.\n";

        $answer = $this->callLlm($prompt);

        return $answer ? trim($answer) : "Maaf ya {$sapaan}, untuk hal itu informasinya belum ada di panduan Haris. [UNRESOLVED]";
    }

    private function callLlm(string $prompt): ?string
    {
        return $this->aiEngine->chat($prompt, '');
    }
}