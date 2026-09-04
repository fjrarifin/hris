# PANDUAN RESMI FITUR, ALUR & SOP HRIS HOMPIM PLAY (KNOWLEDGE BASE)

Dokumen ini adalah acuan resmi operasional dan panduan lengkap bagi Asisten AI (Haris) dalam menjawab pertanyaan karyawan seputar sistem HRIS HomPim Play.

---

## 1. Presensi & Kehadiran (Attendance)
* **Aturan Scan Mesin Fingerspot:**
  * Jam Masuk diambil dari **scan pertama** pada hari tersebut.
  * Jam Pulang diambil dari **scan terakhir** pada hari tersebut.
  * Jika karyawan sudah scan masuk tetapi belum scan pulang, status di sistem adalah **"Sedang Bekerja"**.
  * Jika salah satu scan tidak dilakukan:
    * Hadir tanpa scan masuk: Terlewat scan pertama kali saat datang.
    * Hadir tanpa scan pulang: Terlewat scan saat pulang kantor.
* **Absensi Mandiri via HP (Mobile Attendance):**
  * Hanya berlaku bagi karyawan yang telah diizinkan oleh HRD/IT (kolom `allow_mobile_attendance = 1`).
  * Mewajibkan izin akses lokasi GPS (harus berada dalam radius kantor) dan pengambilan foto selfie.
* **Koreksi Absensi (Attendance Correction):**
  * **Kapan digunakan:** Jika lupa scan masuk/pulang, mesin fingerspot offline, atau dinas luar kota.
  * **Cara mengajukan:** Masuk ke menu *Koreksi Absensi* ➔ Pilih tanggal yang ingin diperbaiki ➔ Masukkan jam scan masuk/pulang yang benar ➔ Tuliskan alasan jelas ➔ Klik Ajukan.
  * **Alur Persetujuan:** Pengajuan akan diproses oleh Atasan Langsung, kemudian diverifikasi oleh tim HRD.
* **Periode Cut-Off Penggajian (Payroll Cycle):**
  * Periode berjalan dari **tanggal 25 bulan lalu s/d tanggal 24 bulan ini**.
  * Segala rekap kehadiran, lembur, dan potongan dihitung berdasarkan rentang periode tersebut.

---

## 2. Pengajuan Cuti (Leave Request)
* **Syarat & Hak Cuti Tahunan:**
  * Hak cuti tahunan **baru aktif setelah mencapai masa kerja 1 tahun (12 bulan)** sejak tanggal bergabung (*join_date*).
  * Setelah 1 tahun, saldo cuti bertambah otomatis **+1 hari per bulan** pada tanggal yang sama dengan *join date*.
  * Masa kedaluwarsa saldo cuti mengikuti tanggal akhir kontrak kerja aktif karyawan.
  * Jika ada karyawan bertanya kenapa cutinya masih 0, cek masa kerjanya. Jika belum genap 12 bulan, jelaskan dengan sopan bahwa hak cuti mulai diperoleh setelah 1 tahun masa kerja.
* **Aturan Pengajuan Cuti:**
  * Maksimal pengambilan adalah **5 hari kerja per satu kali pengajuan**.
  * Tanggal cuti tidak boleh bertabrakan dengan pengajuan cuti lain atau tanggal Public Holiday (PH).
  * Pengajuan harus disetujui oleh **Atasan Langsung** terlebih dahulu, baru kemudian diverifikasi oleh **HRD**.
  * Khusus level Manager atau General Manager (GM), pengajuan langsung ditujukan ke HRD tanpa approval atasan.
* **Pembatalan Cuti:**
  * Jika karyawan sudah mengajukan cuti dan disetujui, namun pada hari tersebut karyawan **tetap masuk kerja dan melakukan scan absensi**, pengajuan cuti dapat dibatalkan sehingga saldo cuti dikembalikan secara otomatis.
* **Cuti Normatif / Khusus (Tanpa Memotong Saldo Cuti Tahunan):**
  * Menikah: 3 hari
  * Menikahkan anak: 2 hari
  * Istri melahirkan / keguguran: 2 hari
  * Karyawati melahirkan: 3 bulan
  * Anggota keluarga inti meninggal dunia (suami/istri/anak/orang tua/mertua): 2 hari
  * Anggota keluarga serumah meninggal dunia: 1 hari
  * Khitanan / Baptis anak: 2 hari

---

## 3. Public Holiday (PH) & Extra Off (EO)
* **Kompensasi Public Holiday (PH):**
  * **Cara Mendapatkan:** Saldo PH diperoleh jika karyawan **masuk bekerja** (tercatat scan masuk fingerspot) pada hari libur nasional / tanggal merah resmi kalender kantor.
  * **Masa Berlaku:** Saldo PH berlaku selama **90 hari (3 bulan)** sejak tanggal libur nasional tersebut.
  * **Pengambilan:** Digunakan sebagai hari libur pengganti lewat menu *Pengajuan PH* di portal HRIS.
  * **Alur Persetujuan:** Disetujui oleh Atasan Langsung ➔ Diverifikasi oleh HRD.
  * **Pembatalan:** Jika karyawan tetap masuk kerja pada hari PH yang disetujui, hak PH dapat dikembalikan.
* **Saldo Extra Off (EO):**
  * Merupakan saldo kompensasi kelebihan jam kerja atau insentif libur kerja yang diterbitkan oleh HRD/Payroll.
  * Masa berlaku saldo Extra Off adalah **3 bulan (90 hari)** sejak periode diterbitkan.

---

## 4. Pengajuan Izin & Sakit (Permission)
* **Pengajuan Izin:**
  * Digunakan untuk keperluan pribadi mendesak yang tidak termasuk kategori cuti.
  * Wajib mencantumkan alasan yang jelas pada form pengajuan.
* **Pengajuan Sakit:**
  * Karyawan yang tidak masuk karena sakit **wajib mengunggah dokumen/foto Surat Keterangan Sakit dari Dokter**.
* **Alur Persetujuan:**
  * Diajukan oleh karyawan ➔ Diperiksa dan disetujui Atasan Langsung ➔ Disetujui HRD (Manager/GM langsung ke HRD).

---

## 5. Pengajuan Lembur / SPL (Surat Perintah Lembur)
* **Aturan Utama:**
  * Staf biasa **tidak memiliki menu pengajuan lembur mandiri**.
  * Lembur **HANYA bisa diajukan oleh ATASAN LANGSUNG** (Supervisor atau Manager) untuk menugaskan bawahan langsungnya lewat menu *Atasan ➔ Pengajuan Lembur*.
* **Ketentuan Lembur:**
  * Durasi pelaksanaan lembur yang sah adalah antara **1 hingga 4 jam** per hari.
  * Wajib mencantumkan tanggal pelaksanaan, jam mulai, jam selesai, serta rincian pekerjaan atau alasan penugasan lembur.
  * Pengajuan dikirim langsung ke HRD untuk verifikasi dan perhitungan uang lembur pada periode payroll.

---

## 6. Pengaturan Jadwal Kerja Tim (Khusus Supervisor)
* Menu *Jadwal Tim* hanya dapat diakses oleh karyawan dengan jabatan **Supervisor**.
* Supervisor bertugas mengatur shift kerja bawahan langsungnya dengan rentang tanggal maksimal **46 hari**.
* Pilihan kode shift: Pagi, Siang, Libur (OFF), Cuti, atau Public Holiday (PH).
* Tersedia fitur unduh template excel untuk pengisian jadwal banyak karyawan sekaligus.

---

## 7. Keamanan Akun & Sesi Login Portal HRIS
* **Sesi Login (Session):**
  * Akun akan otomatis keluar (*auto-logout*) bila tidak ada aktivitas interaktif selama **30 menit**.
  * Satu akun **hanya dapat aktif pada satu perangkat / browser**.
  * Jika muncul error *"Akun Anda sedang aktif di perangkat/browser lain"*, karyawan bisa meminta bot WhatsApp ini dengan mengetik *"Reset session"* atau *"Bantu reset login"*.
* **Password:**
  * Password default akun baru adalah **12345678** (delapan digit).
  * Penggantian password mandiri dibatasi maksimal **1 kali dalam 30 hari**.
  * **Lupa Password:** Karyawan dapat mengklik tautan *Lupa Password?* pada halaman login portal. Kode OTP 6 digit akan dikirimkan ke WhatsApp terdaftar dengan masa berlaku 2 menit.

---

## 8. Akses Masuk Turnstile Gate Kantor (RFID & QR Code)
* **Penggunaan Kartu Akses RFID:**
  * Kartu RFID adalah metode utama karyawan untuk membuka gate turnstile kantor.
* **Pembuatan QR Code Gate Darurat:**
  * Jika kartu RFID tertinggal di rumah, hilang, atau rusak, karyawan dapat meminta **QR Code Gate Turnstile langsung via chat WhatsApp ke bot ini**.
  * Sesuai SOP, karyawan wajib menyebutkan alasan penggunaan (contoh: kartu tertinggal di rumah).
  * Bot akan membuatkan QR Code unik beresolusi tinggi yang berlaku untuk **hari ini** dan otomatis mencatat riwayat ke log HRD.

---

## 9. Aturan Khusus Karyawan Holding (`m_karyawan_holding`)
* Rekan Karyawan Holding (perusahaan holding HomPim Play) yang statusnya aktif (`is_active = true`) diakui oleh sistem.
* **Batasan Hak Akses WhatsApp Bot:**
  * Karyawan Holding **khusus memiliki hak akses untuk pembuatan QR Code Gate Turnstile masuk kantor hari ini** (menggunakan kode holding khusus grup 4).
  * Transaksi otomatis dicatat ke tabel `t_qr_holding`.
  * Karyawan Holding tidak memiliki data cuti, jadwal shift, atau fingerspot di portal HRIS unit bisnis HomPim Play.
  * Jika karyawan holding menanyakan hal di luar QR Gate, arahkan dengan ramah bahwa wewenang di bot WhatsApp ini khusus untuk akses QR Gate Turnstile hari ini.

---

## 10. Tingkatan Wewenang (User Level / RBAC)
* **Level 0 (IT Administrator / Superadmin):** Akses teknis penuh seluruh sistem, database, dan pemeliharaan server.
* **Level 1 (Direksi / Top Management):** Akses monitoring eksekutif dan laporan performa perusahaan.
* **Level 2 (HRD / HRGA Management):** Akses pengelolaan seluruh data karyawan, rekrutmen, absensi, approval pengajuan, dan penggajian.
* **Level 3 (Staff / Karyawan Biasa):** Hanya memiliki wewenang untuk melihat dan mengajukan data pribadi sendiri.
