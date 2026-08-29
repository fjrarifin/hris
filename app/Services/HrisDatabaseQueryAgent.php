<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HrisDatabaseQueryAgent
{
    public function __construct(
        private readonly OpenRouterChatService $openRouter,
        private readonly GeminiChatService $gemini
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
   - atasan_langsung_nik (VARCHAR, NIK atasan langsung)
   - atasan_tidak_langsung_nik (VARCHAR, NIK atasan tidak langsung)
   - jenis_kelamin (VARCHAR, 'Laki-laki' / 'Perempuan')

2. Tabel: t_kontrak_karyawan (Histori & masa aktif kontrak kerja)
   - nik (VARCHAR, relasi ke m_karyawan.nik)
   - start_date (DATE)
   - end_date (DATE)
   - status_kontrak (VARCHAR, contoh: 'Kontrak 1', 'Kontrak 2')

3. Tabel: employee_daily_schedules (Jadwal shift kerja harian karyawan)
   - karyawan_nik (VARCHAR, relasi ke m_karyawan.nik)
   - schedule_date (DATE, format YYYY-MM-DD)
   - schedule_category_id (BIGINT, relasi ke attendance_schedule_categories.id)
   - schedule_code (VARCHAR, contoh: 'OFFICE', 'OFF', 'PAGI', 'SIANG')
   - notes (TEXT)

4. Tabel: attendance_schedule_categories (Master kategori shift/jadwal kerja)
   - id (INT)
   - name (VARCHAR, nama shift/jadwal misal: 'Office', 'Shift Pagi', 'Shift Siang', 'Libur')
   - code (VARCHAR)

5. Tabel: fingerspot_attendance_logs (Log scan presensi aktual dari mesin fingerspot)
   - pin (VARCHAR, relasi ke m_karyawan.pin)
   - scan_date (DATETIME, waktu scan absensi)
   - status_scan (INT, 0 = Scan Masuk, 1 = Scan Pulang)

6. Tabel: attendance_corrections (Pengajuan koreksi absensi)
   - karyawan_nik (VARCHAR)
   - correction_date (DATE)
   - corrected_scan_in (TIME)
   - corrected_scan_out (TIME)
   - reason (TEXT)
   - status (VARCHAR: 'pending', 'approved', 'rejected')

7. Tabel: leave_requests (Pengajuan cuti tahunan / cuti khusus)
   - user_id (INT, relasi ke users.id di mana users.username = m_karyawan.nik)
   - start_date (DATE)
   - end_date (DATE)
   - total_days (INT)
   - reason (TEXT)
   - status (VARCHAR: 'pending', 'approved', 'rejected', 'cancelled')

8. Tabel: employee_permissions (Pengajuan izin / sakit / dispensasi)
   - user_id (INT)
   - date (DATE)
   - end_date (DATE)
   - type (VARCHAR: 'sakit', 'izin', 'dispensasi')
   - reason (TEXT)
   - status (VARCHAR: 'pending', 'approved', 'rejected')

9. Tabel: overtime_requests (Pengajuan lembur / SPL)
   - user_id (INT)
   - date (DATE)
   - start_time (TIME)
   - end_time (TIME)
   - duration_minutes (INT)
   - reason (TEXT)
   - status (VARCHAR: 'pending', 'approved', 'rejected')

10. Tabel: public_holidays (Daftar Hari Libur Nasional)
    - id (INT, Primary Key)
    - name (VARCHAR, nama hari libur)
    - holiday_date (DATE)
    - is_active (TINYINT: 1 = aktif)

11. Tabel: public_holiday_requests (Klaim libur pengganti Public Holiday / PH)
    - user_id (INT)
    - public_holiday_id (INT, relasi ke public_holidays.id)
    - claim_date (DATE, tanggal libur yang diambil)
    - status (VARCHAR: 'pending', 'approved', 'rejected')

12. Tabel: employee_extra_offs (Saldo Extra Off karyawan)
    - karyawan_nik (VARCHAR)
    - periode_start (DATE)
    - periode_end (DATE)
    - days (INT, jumlah hari EO)

13. Tabel: extra_off_requests (Klaim libur Extra Off / EO)
    - user_id (INT)
    - claim_date (DATE)
    - source_period_start (DATE)
    - source_period_end (DATE)
    - status (VARCHAR: 'pending', 'approved', 'rejected')

14. Tabel: master_departments (Master Departemen kantor)
    - id (INT)
    - name (VARCHAR, nama departemen)

15. Tabel: master_divisions (Master Divisi kantor)
    - id (INT)
    - name (VARCHAR, nama divisi)

16. Tabel: master_jabatans (Master Jabatan kantor)
    - id (INT)
    - name (VARCHAR, nama jabatan)

17. Tabel: event_absen (Master Event Kantor yang membutuhkan absensi QR)
    - id (INT)
    - nama_event (VARCHAR)
    - tgl_event (DATE)
    - waktu_mulai (TIME)
    - waktu_selesai (TIME)
    - lokasi (VARCHAR)

18. Tabel: absensi_event (Data kehadiran karyawan pada event kantor)
    - event_id (INT, relasi ke event_absen.id)
    - karyawan_nik (VARCHAR)
    - scan_time (DATETIME)
    - status (VARCHAR)
SCHEMA;
    }

    /**
     * Memproses pertanyaan bebas user, menghasilkan SQL SELECT yang aman, mengeksekusi, dan merangkum jawaban.
     */
    public function queryAndAnswer(string $question, ?Karyawan $karyawan, array $history = []): ?string
    {
        $today = now()->toDateString();
        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";
        $nik = $karyawan?->nik ?? '';
        $pin = $karyawan?->pin ?? '';

        // 1. Minta AI menghasilkan query SQL SELECT
        $sql = $this->generateSql($question, $karyawan, $today, $history);
        if ($sql === null || trim($sql) === '') {
            return null;
        }

        // 2. Validasi Keamanan SQL (Strict Read-Only & Privacy Guard)
        $securityCheck = $this->validateSqlSafety($sql, $nik);
        if (! $securityCheck['safe']) {
            Log::warning("SQL Security Guard Blocked Query: {$sql}", ['reason' => $securityCheck['reason']]);
            return null;
        }

        $cleanSql = $securityCheck['sql'];

        // 3. Eksekusi Query ke Database (Read-Only)
        try {
            $results = DB::select(DB::raw($cleanSql));
        } catch (Throwable $e) {
            Log::warning("Gagal eksekusi Read-Only SQL AI: {$cleanSql}", ['error' => $e->getMessage()]);
            return null;
        }

        // 4. Minta AI merangkum hasil data ke bahasa WhatsApp yang ramah
        return $this->summarizeResult($question, $cleanSql, $results, $karyawan, $sapaan);
    }

    private function generateSql(string $question, ?Karyawan $karyawan, string $today, array $history = []): ?string
    {
        $schema = $this->getSchemaContext();
        $nik = $karyawan?->nik ?? '';
        $pin = $karyawan?->pin ?? '';
        $nama = $karyawan?->nama_karyawan ?? '';

        $prompt = "Kamu adalah Database Text-to-SQL Generator cerdas untuk sistem HRIS.\n";
        $prompt .= "Tugasmu: Buat 1 query MySQL SELECT yang tepat, efisien, dan valid untuk menjawab pertanyaan karyawan.\n\n";
        $prompt .= "{$schema}\n\n";
        $prompt .= "Informasi Karyawan yang Bertanya:\n";
        $prompt .= "- NIK: {$nik}\n";
        $prompt .= "- Nama: {$nama}\n";
        $prompt .= "- PIN Fingerspot: {$pin}\n";
        $prompt .= "- Tanggal Hari Ini: {$today}\n\n";
        $prompt .= "Aturan SQL Generator:\n";
        $prompt .= "1. HANYA buat query SELECT (Dilarang keras INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE).\n";
        $prompt .= "2. Jika pertanyaan menyangkut data pribadi (jadwal, log presensi, cuti, izin, kontrak), WAJIB filter berdasarkan NIK ({$nik}) atau PIN ({$pin}).\n";
        $prompt .= "3. Jangan pernah query data gaji/nominal finansial pribadi orang lain.\n";
        $prompt .= "4. Output HARUS murni berupa string query SQL saja tanpa penjelasan, tanpa markdown codeblock (tanpa ```sql).\n";
        $prompt .= "5. Jika pertanyaan sama sekali tidak relevan dengan data HRIS kantor, jawab tepat satu kata: NONE\n\n";
        $prompt .= "Pertanyaan Karyawan: \"{$question}\"";

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

    private function validateSqlSafety(string $sql, string $authenticatedNik): array
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

        // Tambahkan LIMIT 25 jika belum ada
        if (! preg_match('/\bLIMIT\s+\d+/i', $upper)) {
            $trimmed .= ' LIMIT 25';
        }

        return ['safe' => true, 'sql' => $trimmed];
    }

    private function summarizeResult(string $question, string $sql, array $results, ?Karyawan $karyawan, string $sapaan): string
    {
        $jsonResults = json_encode(array_slice($results, 0, 25), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = "Kamu adalah Haris, IT AI Agent internal di HomPim Play yang menangani sistem HRIS.\n";
        $prompt .= "Karyawan atas nama {$sapaan} menanyakan: \"{$question}\"\n\n";
        $prompt .= "Berikut adalah data fakta hasil query database HRIS:\n";
        $prompt .= "```json\n{$jsonResults}\n```\n\n";
        $prompt .= "ATURAN GAYA BICARA HARIS (NATURAL & HUMAN-LIKE):\n";
        $prompt .= "1. Jawab langsung to-the-point dalam bentuk kalimat percakapan WhatsApp yang ramah, santai, dan luwes.\n";
        $prompt .= "2. DILARANG menggunakan format list formulir kaku atau bullet point (* ...) kecuali user meminta rincian riwayat banyak baris/hari.\n";
        $prompt .= "3. DILARANG menyebutkan istilah teknis SQL/query/database/SELECT. Bicaralah layaknya rekan kerja HR yang hangat.\n";
        $prompt .= "4. ATURAN PROSEDUR & APPROVAL KANTOR:\n";
        $prompt .= "   - CUTI / LIBUR PH / EXTRA OFF / IZIN / SAKIT: Diajukan sendiri oleh karyawan di portal HRIS (https://hr.hompimplay.id) dan disetujui (approval) oleh ATASAN LANGSUNG.\n";
        $prompt .= "   - LEMBUR (SPL): Hanya bisa diajukan oleh ATASAN LANGSUNG yang mendelegasikan/menugaskan bawahan langsungnya di portal HRIS. Karyawan biasa tidak bisa mengajukan lembur sendiri.\n";
        $prompt .= "5. Jika data kosong/tidak ditemukan, infokan dengan santai dan ramah bahwa datanya belum tercatat di sistem.\n";
        $prompt .= "6. Panggil karyawan dengan '{$sapaan}'. Jangan mengulang kata 'Halo' jika percakapan sedang berjalan.\n";
        $prompt .= "7. Boleh gunakan 1-2 emoji (😊, 👍, ✨) agar chat terasa hidup dan akrab.\n";

        $summary = $this->callLlm($prompt);

        return $summary ? trim($summary) : "Maaf ya {$sapaan}, data terkait pertanyaan tersebut belum tercatat di sistem HRIS.";
    }

    private function callLlm(string $prompt): ?string
    {
        $openRouterRes = $this->openRouter->chat($prompt, '');
        if ($openRouterRes !== null && trim($openRouterRes) !== '') {
            return $openRouterRes;
        }

        return $this->gemini->chat($prompt, '');
    }
}
