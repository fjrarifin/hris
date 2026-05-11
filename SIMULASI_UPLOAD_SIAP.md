# ✅ SIMULASI UPLOAD PAYROLL - READY TO GO

## 🔍 HASIL VALIDASI DATABASE

### ✅ Semua Component Valid
```
✅ Gaji Pokok (earning)
✅ Tunjangan Jabatan (earning)
✅ Tunjangan Tidak Tetap (earning)
✅ THR (earning)
✅ Lembur (earning)
✅ PPh21 (deduction)
✅ Potongan Kasbon (deduction)
✅ Potongan Izin (deduction)
```

### ✅ Semua Karyawan Valid
```
✅ HPP25120147 | FAJAR ARIFIN | SPV IT
✅ HPP25110042 | ILHAM FADJRIANSYAH | Senior Manager Marketing, Sales & CRM
```

---

## 📊 DATA YANG AKAN DIUPLOAD

### Row 1: Fajar Arifin (Fajar)
```
NIK             : HPP25120147
Periode         : 25 Februari - 25 Maret 2026
Hari Kerja      : 24 hari
Hadir           : 22 hari
Libur           : 2 hari

PENDAPATAN:
- Gaji Pokok              : 3,791,250
- Tunjangan Jabatan       : 1,253,750
- Tunjangan Tidak Tetap   : 950,000
- THR                     : 852,500
- Lembur                  : 0
━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Pendapatan          : 6,847,500

POTONGAN:
- PPh21                   : 500,000
- Potongan Kasbon         : 342,500
- Potongan Izin           : 0
━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Potongan            : 842,500

HASIL AKHIR:
Total Dibayarkan          : 6,005,000 ✅
```

### Row 2: Ilham Fadjriansyah (Ilham)
```
NIK             : HPP25110042
Periode         : 01 April - 30 April 2026
Hari Kerja      : 22 hari
Hadir           : 21 hari
Libur           : 1 hari

PENDAPATAN:
- Gaji Pokok              : 8,000,000
- Tunjangan Jabatan       : 3,000,000
- Tunjangan Tidak Tetap   : 0
- THR                     : 0
- Lembur                  : 1,500,000
━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Pendapatan          : 12,500,000

POTONGAN:
- PPh21                   : 1,250,000
- Potongan Kasbon         : 0
- Potongan Izin           : 0
━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Potongan            : 1,250,000

HASIL AKHIR:
Total Dibayarkan          : 11,250,000 ✅
```

---

## 📁 FILE SIAP PAKAI

### Download File CSV:
File sudah siap di: `/public/sample_payroll.csv`

**Isi File:**
```csv
nik,periode_start,periode_end,hari_kerja,hadir,libur,Gaji Pokok,Tunjangan Jabatan,Tunjangan Tidak Tetap,THR,Lembur,PPh21,Potongan Kasbon,Potongan Izin
HPP25120147,2026-02-25,2026-03-25,24,22,2,3791250,1253750,950000,852500,0,500000,342500,0
HPP25110042,2026-04-01,2026-04-30,22,21,1,8000000,3000000,0,0,1500000,1250000,0,0
```

---

## 🚀 LANGKAH UPLOAD

### Step 1: Siapkan File Excel
1. Buka Excel / Google Sheets / LibreOffice
2. Copy data dari file CSV di atas
3. Atau download `sample_payroll.csv` langsung
4. Convert ke `.xlsx` jika diperlukan

### Step 2: Buka Halaman Upload
1. Buka: `http://hris.hompimplay.id/hr/payroll/upload`
2. Klik "Pilih File Excel"
3. Pilih file yang sudah disiapkan

### Step 3: Preview Data
1. Klik "Preview & Upload"
2. Modal akan menampilkan preview data
3. Lihat 5-10 baris pertama untuk verifikasi

### Step 4: Confirm Upload
1. Klik "✅ Lanjutkan Upload"
2. Tunggu proses selesai
3. Lihat notifikasi sukses/error

### Step 5: Verifikasi Data
1. Cek database untuk melihat data payroll
2. Generate slip gaji untuk setiap karyawan
3. Lihat total pendapatan & potongan

---

## 🔍 EXPECTED DATABASE STATE AFTER UPLOAD

### Tabel `payrolls`:
```
id | karyawan_nik  | periode_start | periode_end | hari_kerja | hadir | libur | total_pendapatan | total_potongan | total_dibayarkan
1  | HPP25120147   | 2026-02-25    | 2026-03-25  | 24         | 22    | 2     | 6847500          | 842500         | 6005000
2  | HPP25110042   | 2026-04-01    | 2026-04-30  | 22         | 21    | 1     | 12500000         | 1250000        | 11250000
```

### Tabel `payroll_items` (untuk Fajar Arifin):
```
id | payroll_id | component_id | type      | amount
1  | 1          | 1            | earning   | 3791250
2  | 1          | 7            | earning   | 1253750
3  | 1          | 10           | earning   | 950000
4  | 1          | 5            | earning   | 852500
5  | 1          | 11           | deduction | 500000
6  | 1          | 12           | deduction | 342500
```

---

## ✅ CHECKLIST SEBELUM UPLOAD

- [x] Semua NIK ada di database
- [x] Semua component ada di database
- [x] Format tanggal: YYYY-MM-DD
- [x] Angka tanpa separator (bukan 3.791.250)
- [x] Header kolom sesuai nama component
- [x] Tidak ada spasi/tab di awal/akhir cell
- [x] File sudah ready di `/public/sample_payroll.csv`

---

## 🎯 TIPS PENTING

1. **Preview Modal** - Selalu gunakan untuk verifikasi sebelum upload
2. **Case Sensitive** - Nama component harus sama persis (termasuk spasi)
3. **Format Tanggal** - Harus YYYY-MM-DD (2026-02-25, bukan 25/02/2026)
4. **Kolom Kosong** - Akan di-skip otomatis (0 atau kosong tidak disimpan)
5. **Transaction Safety** - Jika error, semua data akan di-rollback

---

## 📝 TESTING SCENARIOS

### Scenario 1: Happy Path ✅
- Upload file dengan data valid
- Preview modal menampilkan data
- Click confirm
- Data tersimpan, totals dihitung dengan benar
- Response: "Upload payroll berhasil"

### Scenario 2: Validation Error ❌
- Upload file dengan NIK tidak valid
- Error: "Kolom NIK kosong / tidak terbaca"

### Scenario 3: Component Not Found ⚠️
- Upload file dengan nama component salah
- Component akan di-skip
- Data tetap tersimpan tapi component yang salah tidak masuk

### Scenario 4: Preview Data ✅
- File dipilih
- Click "Preview & Upload"
- Modal menampilkan 2 baris data (header + data)
- Info file: "Total kolom: 14 | Total baris data: 2 | Menampilkan: 2 baris pertama"

---

## 🔧 QUICK TEST

Jalankan di terminal untuk double-check sebelum upload:

```bash
php artisan tinker
> $p1 = App\Models\Payroll::whereHas('karyawan', fn($q) => $q->where('nik', 'HPP25120147'))->first();
> dd($p1);
```

Atau cek via SQL:
```sql
SELECT * FROM payrolls WHERE karyawan_nik = 'HPP25120147';
SELECT * FROM payroll_items WHERE payroll_id = 1;
```

---

## 📞 TROUBLESHOOTING

### Upload gagal dengan error "Kolom NIK kosong"
**Solusi**: Pastikan kolom pertama adalah `nik` dan ada value di setiap baris

### Component tidak terbaca
**Solusi**: Cek nama component di database, harus SAMA PERSIS (case-sensitive + spasi)

### Total tidak sesuai
**Solusi**: Verifikasi perhitungan di file, pastikan semua angka benar

### File tidak bisa di-preview
**Solusi**: Pastikan file format `.xlsx` atau `.csv` yang valid

---

## 🎬 READY TO GO! 🚀

File sudah siap di:
- 📄 [CONTOH_EXCEL_PAYROLL.md](./CONTOH_EXCEL_PAYROLL.md) - Dokumentasi lengkap
- 📄 [PAYROLL_UPLOAD_GUIDE.md](./PAYROLL_UPLOAD_GUIDE.md) - Panduan component
- 📊 [public/sample_payroll.csv](./public/sample_payroll.csv) - File siap upload

**Silakan buka halaman upload dan coba! 🎯**

