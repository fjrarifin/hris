# 📋 PAYROLL EXCEL UPLOAD GUIDE

## 🔧 Struktur File Excel yang Dibutuhkan

Berdasarkan `PayrollController`, file Excel harus memiliki kolom-kolom berikut:

---

## 📌 KOLOM WAJIB (Fixed Columns)

| No | Nama Kolom | Tipe Data | Deskripsi | Contoh |
|----|-----------|-----------|-----------|--------|
| 1 | `nik` | Text | NIK Karyawan (unique identifier) | `HPP25120147` |
| 2 | `periode_start` | Date | Tanggal mulai periode | `2026-04-01` |
| 3 | `periode_end` | Date | Tanggal akhir periode | `2026-04-30` |
| 4 | `hari_kerja` | Number | Jumlah hari kerja dalam periode | `22` |
| 5 | `hadir` | Number | Jumlah hari kehadiran | `20` |
| 6 | `libur` | Number | Jumlah hari libur/cuti | `2` |

---

## 💰 KOLOM DINAMIS (Payroll Components)

Kolom-kolom berikutnya adalah **komponen gaji** yang harus cocok dengan `payroll_components` di database.

**⚠️ PENTING:**
- Nama kolom **HARUS SAMA PERSIS** dengan nama component (case-sensitive, termasuk spasi!)
- Setiap component memiliki `type`: `earning` (pendapatan) atau `deduction` (potongan)

### ✅ PAYROLL COMPONENTS YANG TERSEDIA (Real Database):

#### 💵 Earning Components (Pendapatan):
| Nama Kolom Excel | Deskripsi |
|-----------------|-----------|
| `Gaji Pokok` | Gaji Pokok |
| `Tunjangan Jabatan` | Tunjangan Jabatan |
| `Tunjangan Tidak Tetap` | Tunjangan Tidak Tetap |
| `Lembur` | Gaji Lembur |
| `Kekurangan Bulan Sebelumnya` | Kekurangan Bulan Sebelumnya |
| `THR` | Tunjangan Hari Raya |
| `Lain-lain` | Komponen Lain-lain |
| `Tunjangan BPJS Kesehatan Karyawan` | Tunjangan BPJS Kesehatan |
| `Tunjangan JHT Karyawan` | Tunjangan JHT (Jaminan Hari Tua) |
| `Tunjangan JP Karyawan` | Tunjangan JP (Jaminan Pensiun) |

#### 💸 Deduction Components (Potongan):
| Nama Kolom Excel | Deskripsi |
|-----------------|-----------|
| `Potongan Izin` | Potongan Izin |
| `Potongan Kasbon` | Potongan Kasbon |
| `Potongan Lain-lain` | Potongan Lain-lain |
| `PPh21` | PPh 21 (Pajak Penghasilan) |
| `Potongan Sakit Tanpa Surat` | Potongan Sakit Tanpa Surat |

---

## 📊 CONTOH FILE EXCEL

### Header Row (Semua kolom wajib ada):
```
nik | periode_start | periode_end | hari_kerja | hadir | libur | Gaji Pokok | Tunjangan Jabatan | Lembur | PPh21 | Potongan Kasbon
```

### Data Row (Contoh):
```
HPP25120147 | 2026-04-01 | 2026-04-30 | 22 | 20 | 2 | 5000000 | 1000000 | 500000 | 150000 | 200000
3201010101010001 | 2026-04-01 | 2026-04-30 | 22 | 21 | 1 | 4500000 | 750000 | 0 | 100000 | 0
```

### Hasil Processing (Row 1):
- **Karyawan**: HPP25120147 
- **Periode**: 1 April - 30 April 2026
- **Absensi**: 22 hari kerja, 20 hadir, 2 libur
- **Komponen Earning**: 
  - Gaji Pokok: 5.000.000
  - Tunjangan Jabatan: 1.000.000
  - Lembur: 500.000
  - **Total Pendapatan: 6.500.000**
- **Komponen Deduction**: 
  - PPh21: 150.000
  - Potongan Kasbon: 200.000
  - **Total Potongan: 350.000**
- **Total Dibayarkan: 6.150.000**

---

## 🔎 VERIFIKASI COMPONENT DI DATABASE

Untuk melihat component terbaru, jalankan command di terminal:

```bash
php artisan tinker
> App\Models\PayrollComponent::get()->each(fn($c) => echo sprintf('- %s (type: %s)', $c->nama, $c->type) . "\n")
```

Atau query SQL:
```sql
SELECT id, nama, type, is_active FROM payroll_components WHERE is_active = 1;
```

---

## ⚠️ VALIDASI & ERROR HANDLING

### Error yang Mungkin Terjadi:

1. **"Kolom NIK kosong / tidak terbaca"**
   - Pastikan kolom `nik` ada dan tidak kosong

2. **Component tidak ditemukan**
   - Nama komponen tidak sesuai dengan database
   - Controller akan skip kolom yang tidak cocok

3. **NIK tidak ditemukan di m_karyawan**
   - Pastikan NIK sudah exist di master karyawan

---

## 📝 LANGKAH UPLOAD

1. ✅ Siapkan file Excel dengan struktur di atas
2. 👁️ Klik "Preview & Upload" di halaman upload
3. 📊 Review preview data (5-10 baris pertama)
4. ✔️ Klik "Lanjutkan Upload"
5. ⏳ Tunggu proses selesai
6. ✅ Lihat notifikasi sukses/error

---

## 🎯 TIPS PENTING

- ✅ Gunakan format tanggal: `YYYY-MM-DD`
- ✅ Gunakan angka tanpa separator (tidak perlu "Rp" atau ".")
- ✅ Minimal 1 earning component dan opsional potongan
- ✅ Kolom kosong/0 akan di-skip (tidak disimpan)
- ✅ Data disimpan dalam transaction (all or nothing)

---

## 🔄 FLOW PROCESSING

```
Excel File
    ↓
Baca Header
    ↓
Loop setiap baris:
  - Validasi NIK ada di m_karyawan
  - Buat Payroll record
  - Loop setiap component:
    - Cari di payroll_components
    - Jika ada, buat PayrollItem
    - Hitung total (earning/deduction)
    ↓
Update Payroll totals:
  - total_pendapatan
  - total_potongan
  - total_dibayarkan
    ↓
✅ Success atau ❌ Rollback
```

