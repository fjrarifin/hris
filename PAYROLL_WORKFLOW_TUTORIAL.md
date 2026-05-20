# Tutorial Workflow Payroll HRIS

Dokumen ini menjelaskan alur penggunaan modul Payroll dari awal sampai slip gaji bisa dikirim massal lewat email.

## 1. Hak Akses

Modul Payroll bersifat confidential.

User yang boleh mengakses Payroll:

- User `hrd0002`.
- User lain yang dimasukkan ke allowlist `PAYROLL_ALLOWED_USERNAMES`.

User `hrd0001` tetap dapat mengakses menu HR lain, tetapi tidak dapat membuka Payroll kecuali dimasukkan ke allowlist.

Jika perlu menambah akses payroll khusus, tambahkan username/NIK ke `.env`:

```env
PAYROLL_ALLOWED_USERNAMES=hrd0002,NIK_LAIN
```

Setelah mengubah `.env`, jalankan:

```bash
php artisan config:clear
```

## 2. Persiapan Sebelum Payroll

Pastikan hal berikut sudah siap:

- Data karyawan di Master Karyawan sudah lengkap.
- Email karyawan terisi.
- Data bank dan nomor rekening karyawan terisi.
- SMTP email di `.env` sudah aktif jika ingin mengirim email real.
- File Google Sheet payroll sudah berisi periode dan komponen payroll yang benar.

Konfigurasi email minimal:

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="HRIS"
```

## 3. Masuk ke Halaman Payroll

Login sebagai user yang punya akses Payroll, lalu buka:

```text
HR > Payroll
```

Atau akses URL:

```text
/hr/payroll
```

Di halaman ini tersedia ringkasan:

- Total Payroll
- Approved
- Locked
- Warning

Tabel payroll menampilkan:

- Periode
- Karyawan
- Total Dibayarkan
- Approval
- Lock
- Validasi
- Email Log
- Aksi

## 4. Tombol di Bagian Atas Halaman

### Filter

Memfilter data payroll berdasarkan:

- Periode Awal
- Periode Akhir
- Status Approval

Gunakan ini jika ingin fokus pada satu periode payroll tertentu.

### Reset

Menghapus filter dan menampilkan semua data payroll kembali.

### History

Membuka ringkasan payroll per periode.

Di halaman History, setiap periode menampilkan:

- Total data payroll
- Jumlah approved
- Jumlah locked
- Total nominal dibayarkan
- Tombol detail periode
- Tombol export periode

### Template

Membuka halaman template email slip gaji.

Template ini dipakai untuk subject email dan preview isi email di log. Desain email slip gaji tetap menggunakan template email original di aplikasi.

Placeholder yang bisa digunakan:

```text
{nama_karyawan}
{nik}
{periode}
{total_dibayarkan}
```

### Export

Mengunduh hasil payroll ke Excel.

Export mengikuti filter yang sedang aktif. Jika halaman difilter periode tertentu, file Excel hanya berisi periode tersebut.

### Sync

Mengambil data payroll dari Google Sheet.

Saat tombol Sync ditekan:

1. Sistem mengambil CSV dari Google Sheet yang sudah dikonfigurasi di `PayrollController`.
2. Sistem membaca periode awal dan periode akhir.
3. Sistem mencocokkan NIK karyawan.
4. Sistem membuat payroll baru jika belum ada untuk NIK dan periode tersebut.
5. Sistem membuat detail komponen payroll.
6. Sistem menjalankan validasi awal.
7. Data duplikat akan dilewati.

Saat proses berjalan, akan muncul spinner `Sinkronisasi Payroll...`.

Hasil sync menampilkan:

- Data baru
- Data dilewati
- Error jika ada baris yang tidak valid

### Blast Email

Mengirim slip gaji lewat email secara massal untuk periode payroll terakhir.

Syarat setiap payroll agar bisa terkirim:

- Payroll sudah approved.
- Payroll sudah locked.
- Validasi tidak invalid.
- Email karyawan tersedia.

Jika salah satu syarat tidak terpenuhi, data tersebut tidak dikirim dan akan dicatat sebagai `blocked`.

Saat proses berjalan, akan muncul spinner `Mengirim Email Massal...`.

Hasil blast menampilkan:

- Terkirim
- Diblokir
- Gagal

Perhatian: tombol ini mengirim email real jika SMTP `.env` aktif.

## 5. Tombol di Kolom Aksi

Setiap baris payroll punya tombol dropdown `Aksi`.

### Preview Slip

Membuka halaman preview slip gaji karyawan.

Preview menampilkan:

- Panel workflow payroll
- Status approval
- Status lock
- Status validasi
- Log email terakhir
- Tampilan slip gaji seperti format PDF
- Detail pendapatan, potongan, benefit, bank, dan total dibayarkan

### Download PDF

Mengunduh slip gaji dalam format PDF.

PDF dilindungi password.

Password menggunakan:

- Tanggal lahir format `ddmmyy`, jika tanggal lahir tersedia.
- NIK karyawan, jika tanggal lahir belum tersedia.

### Approve

Menyetujui payroll.

Status berubah menjadi:

```text
approved
```

Payroll yang belum valid tidak bisa di-approve.

Gunakan tombol ini setelah data payroll sudah dicek dan valid. Tidak ada langkah pengajuan terpisah; HR Manager/Admin bisa langsung approve data yang sudah benar.

### Reject

Menolak payroll.

Saat reject, user bisa mengisi catatan penolakan.

Payroll yang sudah locked tidak bisa ditolak.

### Lock

Mengunci payroll setelah approved.

Syarat lock:

- Payroll sudah approved.
- Tidak ada error validasi critical.

Setelah locked, payroll dianggap final untuk proses pengiriman slip gaji.

### Unlock

Membuka kembali payroll yang sudah dikunci.

Gunakan hanya jika payroll perlu koreksi ulang.

### Kirim Ulang Email

Mengirim ulang slip gaji ke karyawan tertentu.

Syarat kirim ulang:

- Payroll approved.
- Payroll locked.
- Payroll valid.
- Email karyawan tersedia.

Saat proses berjalan, akan muncul spinner `Mengirim Email...`.

Setelah selesai, sistem mencatat log dengan status:

- `sent` jika email berhasil dikirim.
- `blocked` jika syarat belum terpenuhi.
- `failed` jika ada error SMTP atau error teknis.

## 6. Status Validasi

Kolom Validasi menampilkan kondisi payroll:

### valid

Payroll aman untuk proses approval, lock, dan pengiriman.

### warning

Payroll punya catatan, tapi belum tentu menghalangi proses.

Contoh warning:

- Total dibayarkan nol.
- Total pendapatan nol.
- Data bank belum lengkap.
- Komponen wajib seperti Gaji Pokok belum ada.
- Nominal terlihat tidak normal.

### invalid

Payroll memiliki error critical dan tidak bisa diproses untuk pengiriman.

Contoh invalid:

- Total dibayarkan minus.
- Total pendapatan minus.
- Total potongan minus.
- Komponen payroll bernilai minus.
- Saat pengiriman: payroll belum approved.
- Saat pengiriman: payroll belum locked.
- Saat pengiriman: email karyawan kosong.

## 7. Log Pengiriman Email

Setiap percobaan pengiriman dicatat di tabel log.

Status log:

- `sent`: email real berhasil dikirim.
- `blocked`: sistem menolak kirim karena validasi atau syarat belum terpenuhi.
- `failed`: sistem mencoba kirim, tetapi gagal karena error teknis.
- `simulated`: log lama dari mode simulasi sebelumnya.

Log bisa dilihat di:

- Kolom Email Log di halaman Payroll.
- Panel log di halaman Preview Slip.

## 8. Alur Payroll yang Disarankan

Ikuti urutan ini untuk proses payroll yang aman:

1. Buka halaman Payroll.
2. Klik `Sync`.
3. Cek jumlah data baru, dilewati, dan error.
4. Gunakan filter periode untuk fokus pada periode payroll terbaru.
5. Klik badge Validasi pada data yang perlu dicek.
6. Buka `Preview Slip` untuk sampling beberapa karyawan.
7. Jika data sudah benar dan valid, klik `Approve`.
8. Klik `Lock`.
9. Download PDF jika perlu pengecekan manual.
10. Test `Kirim Ulang Email` ke satu karyawan terlebih dahulu.
11. Jika email test sudah masuk dan benar, klik `Blast Email`.
12. Cek hasil blast: sent, blocked, failed.
13. Buka Preview Slip atau log untuk karyawan yang blocked/failed.

## 9. Contoh Flow Test Aman

Gunakan satu karyawan sebagai sample.

Contoh NIK:

```text
HPP25120147
```

Langkah:

1. Filter atau cari NIK tersebut di tabel payroll.
2. Buka `Preview Slip`.
3. Pastikan status `approved`, `locked`, dan `valid`.
4. Klik `Kirim Ulang Email`.
5. Tunggu spinner selesai.
6. Pastikan log menjadi `sent`.
7. Cek inbox email karyawan.

Jika sample berhasil, baru lanjut blast email massal.

## 10. Troubleshooting

### Tombol Payroll tidak muncul

Kemungkinan:

- User tidak punya akses payroll.
- Username belum masuk allowlist.
- User bukan Admin level 1.
- User bukan HR Manager yang terdeteksi.

Solusi:

- Login sebagai `hrd0002`, atau
- Tambahkan username ke `PAYROLL_ALLOWED_USERNAMES`.

### Sync gagal

Kemungkinan:

- Google Sheet tidak bisa diakses.
- URL CSV berubah.
- Format kolom berubah.
- Periode kosong atau format tanggal salah.

Cek pesan error dari hasil Sync.

### Email tidak terkirim

Cek:

- `MAIL_MAILER` harus `smtp`.
- Credential SMTP benar.
- Email karyawan terisi.
- Payroll sudah approved.
- Payroll sudah locked.
- Validasi tidak invalid.
- Cek log status `blocked` atau `failed`.

### Blast banyak blocked

Biasanya karena payroll belum approved atau belum locked.

Solusi:

1. Filter periode payroll.
2. Approve data yang sudah benar.
3. Lock data yang sudah approved.
4. Jalankan Blast Email lagi.

## 11. Catatan Penting

- Payroll adalah data confidential.
- Jangan beri akses payroll ke HR Admin biasa jika tidak perlu.
- Selalu test kirim ulang ke satu karyawan sebelum blast massal.
- Jangan klik Blast Email sebelum payroll final dan locked.
- Jika ada koreksi setelah lock, lakukan Unlock, koreksi data, validasi ulang, approve ulang jika perlu, lalu lock kembali.
