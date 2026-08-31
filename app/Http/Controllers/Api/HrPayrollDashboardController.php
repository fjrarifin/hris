<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\MonthlyRevenue;
use App\Models\Payroll;
use App\Models\PayrollItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPayrollDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $monthInput = $request->query('month', now()->format('Y-m'));
        $businessUnit = $request->query('business_unit', 'HomPimPlay');

        try {
            $selectedDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable $e) {
            $selectedDate = now()->startOfMonth();
        }

        $startDate = $selectedDate->copy()->startOfMonth();
        $endDate = $selectedDate->copy()->endOfMonth();

        // Periksa apakah sudah ada data bisnis_unit yang terisi di tabel m_karyawan
        $hasSpecificBu = Karyawan::query()->whereNotNull('bisnis_unit')->where('bisnis_unit', '!=', '')->exists();

        // Helper filter business unit
        $applyBusinessUnitFilter = function ($query, $column = 'bisnis_unit') use ($businessUnit, $hasSpecificBu) {
            if (! empty($businessUnit) && $businessUnit !== 'all') {
                if ($hasSpecificBu) {
                    $query->where($column, $businessUnit);
                } else {
                    $query->where(function ($q) use ($column, $businessUnit) {
                        $q->where($column, $businessUnit)
                            ->orWhereNull($column)
                            ->orWhere($column, '');
                    });
                }
            }
        };

        // 1. Ambil Data Payroll Periode Terpilih (Termasuk karyawan yang sudah nonaktif tapi punya payroll di periode ini)
        $payrollQuery = Payroll::query()
            ->with(['karyawan', 'items'])
            ->whereHas('karyawan', function ($q) use ($applyBusinessUnitFilter) {
                $applyBusinessUnitFilter($q, 'bisnis_unit');
            })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('periode_start', [$startDate, $endDate])
                    ->orWhereBetween('periode_end', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('periode_start', '<=', $startDate)
                            ->where('periode_end', '>=', $endDate);
                    });
            });

        $payrolls = $payrollQuery->get();
        $payrollIds = $payrolls->pluck('id');

        // 2. Ambil Payroll Items untuk kalkulasi komponen yang akurat
        $payrollItems = $payrollIds->isNotEmpty()
            ? PayrollItem::whereIn('payroll_id', $payrollIds)->get()
            : collect();

        // 3. Score Cards (KPI Metrics)
        // Total Gaji Bruto (Gross Pendapatan Karyawan) - sama persis dengan /payroll/process
        $totalGajiBruto = (int) $payrolls->sum('total_pendapatan');

        // Total Bruto Man Power (Total Beban Biaya Perusahaan termasuk BPJS Perusahaan)
        $totalBrutoManPower = (int) $payrolls->sum(function ($p) {
            return $p->bruto_man_power ?: ($p->total_pendapatan ?: $p->basic_salary);
        });

        $totalGajiNetto = (int) $payrolls->sum('total_dibayarkan');

        // Biaya Lembur yang akurat
        $biayaLembur = (int) $payrollItems->filter(function ($item) {
            $name = strtolower((string) ($item->nama_item ?: ''));
            return str_contains($name, 'lembur') || str_contains($name, 'overtime');
        })->sum('amount');

        // Biaya BPJS (Total Iuran Perusahaan & Karyawan)
        $biayaBpjsPerusahaan = (int) $payrollItems->filter(function ($item) {
            $name = strtolower((string) ($item->nama_item ?: ''));
            return $item->type === 'employer_contribution' ||
                (str_contains($name, 'perusahaan') && (str_contains($name, 'bpjs') || str_contains($name, 'jht') || str_contains($name, 'jp') || str_contains($name, 'jkk') || str_contains($name, 'jkm') || str_contains($name, 'jkn')));
        })->sum('amount');

        $biayaBpjsKaryawan = (int) $payrollItems->filter(function ($item) {
            $name = strtolower((string) ($item->nama_item ?: ''));
            return $item->type === 'deduction' &&
                (str_contains($name, 'bpjs') || str_contains($name, 'jht') || str_contains($name, 'jp') || str_contains($name, 'jkn'));
        })->sum('amount');

        $biayaBpjs = $biayaBpjsPerusahaan + $biayaBpjsKaryawan;

        // PPh 21 Terpisah
        $pph21 = (int) $payrollItems->filter(function ($item) {
            $name = strtolower((string) ($item->nama_item ?: ''));
            return str_contains($name, 'pph') || str_contains($name, 'pajak');
        })->sum('amount');

        // Biaya Potongan Murni (Eksklusi BPJS Karyawan & PPh 21 -> hanya Kasbon, Izin, Sakit Tanpa Surat, Denda, dll)
        $biayaPotongan = (int) $payrollItems->filter(function ($item) {
            if ($item->type !== 'deduction') {
                return false;
            }
            $name = strtolower((string) ($item->nama_item ?: ''));
            $isBpjs = str_contains($name, 'bpjs') || str_contains($name, 'jht') || str_contains($name, 'jp') || str_contains($name, 'jkn');
            $isPph = str_contains($name, 'pph') || str_contains($name, 'pajak');
            return ! $isBpjs && ! $isPph;
        })->sum('amount');

        // Biaya Casual di periode ini
        $biayaCasual = (int) $payrolls->filter(function ($p) {
            $status = strtolower((string) ($p->karyawan?->status_karyawan ?: ''));
            $jabatan = strtolower((string) ($p->karyawan?->jabatan ?: ''));
            return str_contains($status, 'casual') || str_contains($status, 'freelance') || str_contains($status, 'harian')
                || str_contains($jabatan, 'casual') || str_contains($jabatan, 'freelance');
        })->sum(fn ($p) => $p->bruto_man_power ?: ($p->total_pendapatan ?: $p->total_dibayarkan));

        // Jumlah Karyawan Aktif berdasarkan Business Unit (Status AKTIF)
        $activeEmployeesQuery = Karyawan::query()
            ->where('status_karyawan', 'AKTIF')
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
        $applyBusinessUnitFilter($activeEmployeesQuery, 'bisnis_unit');
        $allActiveEmployees = $activeEmployeesQuery->get();

        $jumlahCasual = $allActiveEmployees->filter(function ($k) {
            $status = strtolower((string) $k->status_karyawan);
            return str_contains($status, 'casual') || str_contains($status, 'freelance') || str_contains($status, 'harian') || str_contains($status, 'daily') || str_contains($status, 'part');
        })->count();

        $jumlahRegular = max(0, $allActiveEmployees->count() - $jumlahCasual);

        // 4. Bar Cards (Tren 12 Bulan Terakhir)
        $monthlyTrends = [];
        for ($i = 11; $i >= 0; $i--) {
            $mDate = $selectedDate->copy()->subMonths($i);
            $mStart = $mDate->copy()->startOfMonth();
            $mEnd = $mDate->copy()->endOfMonth();
            $mKey = $mDate->format('Y-m');
            $mLabel = $mDate->translatedFormat('M Y');

            $mPayrolls = Payroll::query()
                ->with('karyawan')
                ->whereHas('karyawan', function ($q) use ($applyBusinessUnitFilter) {
                    $applyBusinessUnitFilter($q, 'bisnis_unit');
                })
                ->where(function ($q) use ($mStart, $mEnd) {
                    $q->whereBetween('periode_start', [$mStart, $mEnd])
                        ->orWhereBetween('periode_end', [$mStart, $mEnd]);
                })
                ->get();

            $mBruto = (int) $mPayrolls->sum(fn ($p) => $p->total_pendapatan ?: $p->basic_salary);
            $mNetto = (int) $mPayrolls->sum('total_dibayarkan');

            // Biaya Casual di bulan ini
            $mBiayaCasual = (int) $mPayrolls->filter(function ($p) {
                $status = strtolower((string) ($p->karyawan?->status_karyawan ?: ''));
                return str_contains($status, 'casual') || str_contains($status, 'freelance') || str_contains($status, 'harian');
            })->sum(fn ($p) => $p->bruto_man_power ?: ($p->total_pendapatan ?: $p->total_dibayarkan));

            // Karyawan Resign di bulan ini (Cek m_karyawan.end_date dan fallback kontrak terakhir di t_kontrak_karyawan)
            $resignDirectNiks = Karyawan::query()
                ->where(function ($q) use ($applyBusinessUnitFilter) {
                    $applyBusinessUnitFilter($q, 'bisnis_unit');
                })
                ->whereBetween('end_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->pluck('nik');

            $activeHomPimPlayNiks = Karyawan::query()
                ->where(function ($q) use ($applyBusinessUnitFilter) {
                    $applyBusinessUnitFilter($q, 'bisnis_unit');
                })
                ->whereNull('end_date')
                ->pluck('nik');

            $contractResignCount = $activeHomPimPlayNiks->isNotEmpty()
                ? DB::table('t_kontrak_karyawan as c1')
                    ->whereIn('c1.nik', $activeHomPimPlayNiks)
                    ->whereIn('c1.status_kontrak', ['NONAKTIF', 'HABIS', 'RESIGN'])
                    ->whereBetween('c1.end_date', [$mStart->toDateString(), $mEnd->toDateString()])
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('t_kontrak_karyawan as c2')
                            ->whereColumn('c2.nik', 'c1.nik')
                            ->where('c2.id', '>', DB::raw('c1.id'));
                    })
                    ->count()
                : 0;

            $mResign = $resignDirectNiks->count() + $contractResignCount;

            // Omset Bulan Ini
            $mRevenueRecord = MonthlyRevenue::where('year', (int) $mDate->format('Y'))
                ->where('month', (int) $mDate->format('n'))
                ->first();
            $mOmset = $mRevenueRecord ? (float) $mRevenueRecord->omset : 0;

            $mPersenManpower = $mOmset > 0 ? round(($mBruto / $mOmset) * 100, 2) : 0;

            $monthlyTrends[] = [
                'month_key' => $mKey,
                'month_label' => $mLabel,
                'gaji_bruto' => $mBruto,
                'gaji_netto' => $mNetto,
                'biaya_casual' => $mBiayaCasual,
                'karyawan_resign' => $mResign,
                'omset' => $mOmset,
                'persentase_manpower_omset' => $mPersenManpower,
            ];
        }

        // Omset Bulan Terpilih
        $currentRevenue = MonthlyRevenue::where('year', (int) $selectedDate->format('Y'))
            ->where('month', (int) $selectedDate->format('n'))
            ->first();
        $currentOmset = $currentRevenue ? (float) $currentRevenue->omset : 0;
        $currentPersenManpower = $currentOmset > 0 ? round(($totalGajiBruto / $currentOmset) * 100, 2) : 0;

        // 5. Pie Cards (Distribusi Komposisi)

        // A. Metode Pembayaran: Cash (Tunjangan Tidak Tetap + Tunjangan Jabatan) vs Transfer (Sisa Komponen Lainnya)
        $totalTunjanganJabatan = (int) $payrollItems->filter(function ($item) {
            $name = strtolower((string) ($item->nama_item ?: ''));
            return str_contains($name, 'tunjangan jabatan');
        })->sum('amount');

        $totalTunjanganTidakTetap = (int) $payrollItems->filter(function ($item) {
            $name = strtolower((string) ($item->nama_item ?: ''));
            return str_contains($name, 'tunjangan tidak tetap');
        })->sum('amount');

        $cashAmount = $totalTunjanganJabatan + $totalTunjanganTidakTetap;
        $transferAmount = max(0, $totalGajiNetto - $cashAmount);

        // Fallback jika belum ada payroll di bulan terpilih: estimasi dari rekening
        $transferCount = $allActiveEmployees->filter(fn ($k) => ! empty(trim((string) $k->bank)) && ! empty(trim((string) $k->no_rekening)))->count();
        $cashCount = max(0, $allActiveEmployees->count() - $transferCount);

        // B. Perbandingan Gender
        $maleCount = $allActiveEmployees->filter(fn ($k) => in_array(strtoupper(trim((string) $k->jenis_kelamin)), ['L', 'LAKI-LAKI', 'PRIA', 'MALE']))->count();
        $femaleCount = $allActiveEmployees->filter(fn ($k) => in_array(strtoupper(trim((string) $k->jenis_kelamin)), ['P', 'PEREMPUAN', 'WANITA', 'FEMALE']))->count();
        $unknownGenderCount = max(0, $allActiveEmployees->count() - ($maleCount + $femaleCount));

        // C. Perbandingan Tingkat Pendidikan
        $eduDistribution = [
            'SMA / SMK' => 0,
            'Diploma (D1-D4)' => 0,
            'Sarjana (S1)' => 0,
            'Magister (S2)' => 0,
            'Lainnya' => 0,
        ];
        foreach ($allActiveEmployees as $k) {
            $edu = strtoupper(trim((string) $k->pendidikan_terakhir));
            if (str_contains($edu, 'SMA') || str_contains($edu, 'SMK') || str_contains($edu, 'SLTA')) {
                $eduDistribution['SMA / SMK']++;
            } elseif (str_contains($edu, 'D1') || str_contains($edu, 'D2') || str_contains($edu, 'D3') || str_contains($edu, 'D4') || str_contains($edu, 'DIPLOMA')) {
                $eduDistribution['Diploma (D1-D4)']++;
            } elseif (str_contains($edu, 'S1') || str_contains($edu, 'SARJANA')) {
                $eduDistribution['Sarjana (S1)']++;
            } elseif (str_contains($edu, 'S2') || str_contains($edu, 'MAGISTER') || str_contains($edu, 'MASTER')) {
                $eduDistribution['Magister (S2)']++;
            } else {
                $eduDistribution['Lainnya']++;
            }
        }

        // D. BPJS Coverage
        $bpjsEnrolledCount = $allActiveEmployees->filter(fn ($k) => (bool) $k->bpjs)->count();
        $bpjsNotEnrolledCount = max(0, $allActiveEmployees->count() - $bpjsEnrolledCount);

        return response()->json([
            'meta' => [
                'selected_month' => $selectedDate->format('Y-m'),
                'selected_month_label' => $selectedDate->translatedFormat('F Y'),
                'business_unit' => $businessUnit,
                'total_active_employees' => $allActiveEmployees->count(),
            ],
            'kpi' => [
                'total_gaji_bruto' => $totalGajiBruto,
                'total_bruto_man_power' => $totalBrutoManPower,
                'total_gaji_netto' => $totalGajiNetto,
                'biaya_potongan' => $biayaPotongan,
                'biaya_lembur' => $biayaLembur,
                'biaya_bpjs' => $biayaBpjs,
                'biaya_bpjs_perusahaan' => $biayaBpjsPerusahaan,
                'biaya_bpjs_karyawan' => $biayaBpjsKaryawan,
                'pph21' => $pph21,
                'biaya_casual' => $biayaCasual,
                'jumlah_regular' => $jumlahRegular,
                'jumlah_casual' => $jumlahCasual,
                'omset' => $currentOmset,
                'persentase_manpower_omset' => $currentPersenManpower,
                'cash_amount' => $cashAmount,
                'transfer_amount' => $transferAmount,
                'tunjangan_jabatan' => $totalTunjanganJabatan,
                'tunjangan_tidak_tetap' => $totalTunjanganTidakTetap,
            ],
            'monthly_trends' => $monthlyTrends,
            'distributions' => [
                'payment_methods' => [
                    'cash_amount' => $cashAmount,
                    'transfer_amount' => $transferAmount,
                    'cash_count' => $cashCount,
                    'transfer_count' => $transferCount,
                ],
                'gender' => [
                    'male' => $maleCount,
                    'female' => $femaleCount,
                    'unknown' => $unknownGenderCount,
                ],
                'education' => $eduDistribution,
                'bpjs' => [
                    'enrolled' => $bpjsEnrolledCount,
                    'not_enrolled' => $bpjsNotEnrolledCount,
                ],
            ],
        ]);
    }
}
