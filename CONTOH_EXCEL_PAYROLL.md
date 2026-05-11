# CONTOH PENGISIAN EXCEL PAYROLL UPLOAD

## 📊 Berdasarkan Slip Gaji yang Anda Tunjukkan

### Data Dari Slip:
- **NIK**: HPP25120147
- **Nama**: FAJAR ARIFIN
- **Jabatan**: SPV IT
- **Periode**: 25 Februari 2026 - 25 Maret 2026
- **Hari Kerja**: 24 Hari
- **Hadir**: 22 Hari (asumsi, slip terlihat 25 tapi mungkin typo)
- **Libur**: 2 Hari

### Pendapatan (dari slip):
| Item | Nominal | Keterangan |
|------|---------|-----------|
| Gaji Pokok | Rp 3,791,250 | Gaji dasar |
| Tunjangan Jabatan | Rp 1,253,750 | Tunjangan jabatan SPV |
| Tunjangan Tidak Tetap | Rp 950,000 | Tunjangan operasional |
| THR | Rp 852,500 | Tambahan hari raya |
| **Total Pendapatan** | **Rp 6,847,500** | |

### Potongan (dari slip - estimasi):
| Item | Nominal | Keterangan |
|------|---------|-----------|
| PPh21 | Rp 500,000 | Pajak Penghasilan |
| Potongan Kasbon | Rp 342,500 | Cicilan kasbon |
| **Total Potongan** | **Rp 842,500** | |

### Hasil Akhir:
- **Total Pendapatan**: Rp 6,847,500
- **Total Potongan**: Rp 842,500
- **Total Dibayarkan**: Rp 6,005,000 ✅

---

## 📑 FORMAT FILE EXCEL

### Header (Row 1):
```
nik | periode_start | periode_end | hari_kerja | hadir | libur | Gaji Pokok | Tunjangan Jabatan | Tunjangan Tidak Tetap | THR | PPh21 | Potongan Kasbon
```

### Data (Row 2 - Fajar Arifin):
```
HPP25120147 | 2026-02-25 | 2026-03-25 | 24 | 22 | 2 | 3791250 | 1253750 | 950000 | 852500 | 500000 | 342500
```

---

## 📋 CONTOH KEDUA (Variasi Data Berbeda)

### Data Karyawan:
- **NIK**: HPP25110042
- **Nama**: ILHAM FADJRIANSYAH
- **Jabatan**: Senior Manager Marketing, Sales & CRM
- **Periode**: 01 April 2026 - 30 April 2026
- **Hari Kerja**: 22 Hari
- **Hadir**: 21 Hari
- **Libur**: 1 Hari

### Pendapatan:
| Item | Nominal |
|------|---------|
| Gaji Pokok | 8,000,000 |
| Tunjangan Jabatan | 3,000,000 |
| Lembur | 1,500,000 |
| **Total** | **12,500,000** |

### Potongan:
| Item | Nominal |
|------|---------|
| PPh21 | 1,250,000 |
| Potongan Izin | 0 |
| **Total** | **1,250,000** |

### Hasil:
- Total Dibayarkan: **11,250,000**

### Data (Row 3):
```
HPP25110042 | 2026-04-01 | 2026-04-30 | 22 | 21 | 1 | 8000000 | 3000000 | 0 | 0 | 1500000 | 1250000 | 0
```

---

## 🎯 CARA MEMBUAT FILE EXCEL

### Opsi 1: Menggunakan Google Sheets
1. Buat spreadsheet baru
2. Input header di Row 1
3. Input data Fajar Arifin di Row 2
4. Input data Ilham di Row 3
5. Download sebagai `.xlsx`

### Opsi 2: Menggunakan Excel Desktop
1. Buka Excel
2. Input sesuai struktur di atas
3. Format kolom sebagai berikut:
   - `nik` = Text
   - `periode_start`, `periode_end` = Date (YYYY-MM-DD)
   - Semua nominal = Number

### Opsi 3: Copy-Paste ke CSV
```csv
nik,periode_start,periode_end,hari_kerja,hadir,libur,Gaji Pokok,Tunjangan Jabatan,Tunjangan Tidak Tetap,THR,Lembur,PPh21,Potongan Kasbon,Potongan Izin
HPP25120147,2026-02-25,2026-03-25,24,22,2,3791250,1253750,950000,852500,0,500000,342500,0
HPP25110042,2026-04-01,2026-04-30,22,21,1,8000000,3000000,0,0,1500000,1250000,0,0
```

---

## ⚠️ PENTING SEBELUM UPLOAD

✅ **Checklist:**
- [ ] Semua NIK ada di database `m_karyawan`
- [ ] Format tanggal: `YYYY-MM-DD`
- [ ] Header kolom HARUS sesuai dengan nama component
- [ ] Jangan ada spasi/tab di awal/akhir cell
- [ ] Angka tanpa separator (bukan 3.791.250 tapi 3791250)
- [ ] Kalau komponen tidak ada di database, akan di-skip

---

## 🚀 WORKFLOW SAAT UPLOAD

1. **Preview Modal**: Akan menampilkan 5-10 baris pertama
2. **Validasi NIK**: Check apakah NIK ada di m_karyawan
3. **Create Payroll**: Buat record utama payroll
4. **Create Items**: Loop setiap component:
   - Cari component nama
   - Buat PayrollItem record
   - Hitung total (earning/deduction)
5. **Update Totals**: Update total_pendapatan, total_potongan, total_dibayarkan
6. **Success/Error**: Response berhasil atau error detail

---

## 📝 CONTOH HASIL DATABASE SETELAH UPLOAD

### Tabel `payrolls`:
| id | karyawan_nik | periode_start | periode_end | hari_kerja | hadir | libur | total_pendapatan | total_potongan | total_dibayarkan |
|----|--------------|---------------|-------------|-----------|-------|-------|-----------------|----------------|-----------------|
| 1 | HPP25120147 | 2026-02-25 | 2026-03-25 | 24 | 22 | 2 | 6847500 | 842500 | 6005000 |
| 2 | HPP25110042 | 2026-04-01 | 2026-04-30 | 22 | 21 | 1 | 12500000 | 1250000 | 11250000 |

### Tabel `payroll_items` (untuk Fajar):
| id | payroll_id | component_id | type | amount |
|----|-----------|--------------|------|--------|
| 1 | 1 | 1 | earning | 3791250 |
| 2 | 1 | 7 | earning | 1253750 |
| 3 | 1 | 10 | earning | 950000 |
| 4 | 1 | 5 | earning | 852500 |
| 5 | 1 | 11 | deduction | 500000 |
| 6 | 1 | 12 | deduction | 342500 |

---

## 🎬 SIAP UNTUK TESTING!

File Anda sudah siap untuk:
1. ✅ Dibuat di Excel / Google Sheets / CSV
2. ✅ Di-upload via halaman upload payroll
3. ✅ Lihat preview sebelum confirm
4. ✅ Cek database untuk hasil
5. ✅ Generate slip gaji

Silakan buat file dan coba upload! 🚀

