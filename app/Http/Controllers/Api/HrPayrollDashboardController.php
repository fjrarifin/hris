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
        try {
            $selectedDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable $e) {
            $selectedDate = now()->startOfMonth();
        }

        $startDate = $selectedDate->copy()->startOfMonth();
        $endDate = $selectedDate->copy()->endOfMonth();

        // 1. Ambil Data Payroll Periode Terpilih
        $payrollQuery = Payroll::query()
            ->with(['karyawan', 'items.component'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('periode_start', [$startDate, $endDate])
                    ->orWhereBetween('periode_end', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('periode_start', '<=', $startDate)
                            ->where('periode_end', '>=', $endDate);
                    });
            });

        $payrolls = $payrollQuery->get();

        // 2. Score Cards (KPI Metrics)
        $totalGajiBruto = (int) $payrolls->sum(function ($p) {
            return $p->bruto_man_power ?: ($p->total_pendapatan ?: $p->basic_salary);
        });

        $totalGajiNetto = (int) $payrolls->sum('total_dibayarkan');
        $biayaPotongan = (int) $payrolls->sum('total_potongan');

        // Biaya Lembur & BPJS dari Payroll Items
        $payrollIds = $payrolls->pluck('id');
        $payrollItems = $payrollIds->isNotEmpty()
            ? PayrollItem::with('component')->whereIn('payroll_id', $payrollIds)->get()
            : collect();

        $biayaLembur = (int) $payrollItems->filter(function ($item) {
            $code = strtolower((string) ($item->component?->code ?: ''));
            $name = strtolower((string) ($item->component?->name ?: ''));
            return str_contains($code, 'lembur') || str_contains($code, 'overtime') || str_contains($name, 'lembur') || str_contains($name, 'overtime');
        })->sum('amount');

        $biayaBpjs = (int) $payrollItems->filter(function ($item) {
            $code = strtolower((string) ($item->component?->code ?: ''));
            $name = strtolower((string) ($item->component?->name ?: ''));
            return str_contains($code, 'bpjs') || str_contains($code, 'jht') || str_contains($code, 'jp') || str_contains($code, 'jkk') || str_contains($code, 'jkm')
                || str_contains($name, 'bpjs') || str_contains($name, 'jaminan');
        })->sum('amount');

        // Jumlah Karyawan Aktif
        $allActiveEmployees = Karyawan::query()
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->get();

        $jumlahCasual = $allActiveEmployees->filter(function ($k) {
            $status = strtolower((string) $k->status_karyawan);
            return str_contains($status, 'casual') || str_contains($status, 'freelance') || str_contains($status, 'harian') || str_contains($status, 'daily') || str_contains($status, 'part');
        })->count();

        $jumlahRegular = max(0, $allActiveEmployees->count() - $jumlahCasual);

        // 3. Bar Cards (Tren 12 Bulan Terakhir)
        $monthlyTrends = [];
        for ($i = 11; $i >= 0; $i--) {
            $mDate = $selectedDate->copy()->subMonths($i);
            $mStart = $mDate->copy()->startOfMonth();
            $mEnd = $mDate->copy()->endOfMonth();
            $mKey = $mDate->format('Y-m');
            $mLabel = $mDate->translatedFormat('M Y');

            $mPayrolls = Payroll::query()
                ->with('karyawan')
                ->where(function ($q) use ($mStart, $mEnd) {
                    $q->whereBetween('periode_start', [$mStart, $mEnd])
                        ->orWhereBetween('periode_end', [$mStart, $mEnd]);
                })
                ->get();

            $mBruto = (int) $mPayrolls->sum(fn ($p) => $p->bruto_man_power ?: ($p->total_pendapatan ?: $p->basic_salary));
            $mNetto = (int) $mPayrolls->sum('total_dibayarkan');

            // Biaya Casual di bulan ini
            $mBiayaCasual = (int) $mPayrolls->filter(function ($p) {
                $status = strtolower((string) ($p->karyawan?->status_karyawan ?: ''));
                return str_contains($status, 'casual') || str_contains($status, 'freelance') || str_contains($status, 'harian');
            })->sum(fn ($p) => $p->bruto_man_power ?: ($p->total_pendapatan ?: $p->total_dibayarkan));

            // Karyawan Resign di bulan ini
            $mResign = Karyawan::query()
                ->whereBetween('end_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->count();

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

        // 4. Pie Cards (Distribusi & Perbandingan Komposisi)
        
        // A. Metode Pembayaran (Cash vs Transfer)
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
            } elseif (str_contains($edu, 'S1') || str_contains($edu, 'SARJANA') || str_contains($edu, 'BACHELOR')) {
                $eduDistribution['Sarjana (S1)']++;
            } elseif (str_contains($edu, 'S2') || str_contains($edu, 'MAGISTER') || str_contains($edu, 'MASTER')) {
                $eduDistribution['Magister (S2)']++;
            } else {
                $eduDistribution['Lainnya']++;
            }
        }

        // D. Perbandingan Status Karyawan (Tetap, Kontrak, Casual)
        $statusTetap = $allActiveEmployees->filter(fn ($k) => in_array(strtolower(trim((string) $k->status_karyawan)), ['tetap', 'permanent', 'pkwtt']))->count();
        $statusKontrak = $allActiveEmployees->filter(fn ($k) => in_array(strtolower(trim((string) $k->status_karyawan)), ['kontrak', 'contract', 'pkwt', 'probation', 'percobaan']))->count();
        $statusCasual = $jumlahCasual;
        $statusLainnya = max(0, $allActiveEmployees->count() - ($statusTetap + $statusKontrak + $statusCasual));

        // E. Perbandingan BPJS vs Non-BPJS
        $bpjsCount = $allActiveEmployees->filter(function ($k) {
            return (bool) $k->bpjs || ! empty(trim((string) $k->no_bpjs));
        })->count();
        $nonBpjsCount = max(0, $allActiveEmployees->count() - $bpjsCount);

        return response()->json([
            'period' => [
                'month' => $selectedDate->format('Y-m'),
                'label' => $selectedDate->translatedFormat('F Y'),
                'year' => (int) $selectedDate->format('Y'),
            ],
            'score_cards' => [
                'total_gaji_bruto' => $totalGajiBruto,
                'total_gaji_netto' => $totalGajiNetto,
                'jumlah_karyawan_regular' => $jumlahRegular,
                'jumlah_karyawan_casual' => $jumlahCasual,
                'total_karyawan' => $allActiveEmployees->count(),
                'biaya_lembur' => $biayaLembur,
                'biaya_bpjs' => $biayaBpjs,
                'biaya_potongan' => $biayaPotongan,
                'omset' => $currentOmset,
                'persentase_manpower_omset' => $currentPersenManpower,
            ],
            'bar_cards' => [
                'monthly_trends' => $monthlyTrends,
            ],
            'pie_cards' => [
                'payment_method' => [
                    ['label' => 'Transfer Bank', 'value' => $transferCount, 'color' => '#3b82f6'],
                    ['label' => 'Cash / Tunai', 'value' => $cashCount, 'color' => '#10b981'],
                ],
                'gender' => [
                    ['label' => 'Laki-laki', 'value' => $maleCount, 'color' => '#0284c7'],
                    ['label' => 'Perempuan', 'value' => $femaleCount, 'color' => '#ec4899'],
                    ['label' => 'Belum Diisi', 'value' => $unknownGenderCount, 'color' => '#94a3b8'],
                ],
                'education' => [
                    ['label' => 'SMA / SMK', 'value' => $eduDistribution['SMA / SMK'], 'color' => '#f59e0b'],
                    ['label' => 'Diploma (D1-D4)', 'value' => $eduDistribution['Diploma (D1-D4)'], 'color' => '#8b5cf6'],
                    ['label' => 'Sarjana (S1)', 'value' => $eduDistribution['Sarjana (S1)'], 'color' => '#3b82f6'],
                    ['label' => 'Magister (S2)', 'value' => $eduDistribution['Magister (S2)'], 'color' => '#059669'],
                    ['label' => 'Lainnya', 'value' => $eduDistribution['Lainnya'], 'color' => '#64748b'],
                ],
                'employment_status' => [
                    ['label' => 'Karyawan Tetap (PKWTT)', 'value' => $statusTetap, 'color' => '#10b981'],
                    ['label' => 'Karyawan Kontrak (PKWT)', 'value' => $statusKontrak, 'color' => '#3b82f6'],
                    ['label' => 'Casual / Freelance', 'value' => $statusCasual, 'color' => '#f59e0b'],
                    ['label' => 'Lainnya', 'value' => $statusLainnya, 'color' => '#94a3b8'],
                ],
                'bpjs_coverage' => [
                    ['label' => 'Terdaftar BPJS', 'value' => $bpjsCount, 'color' => '#059669'],
                    ['label' => 'Non BPJS', 'value' => $nonBpjsCount, 'color' => '#ef4444'],
                ],
            ],
        ]);
    }

    public function saveMonthlyRevenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2040'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'omset' => ['required', 'numeric', 'min:0'],
            'branch_or_unit' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $revenue = MonthlyRevenue::updateOrCreate(
            [
                'year' => $validated['year'],
                'month' => $validated['month'],
                'branch_or_unit' => $validated['branch_or_unit'] ?: 'Holding',
            ],
            [
                'omset' => $validated['omset'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]
        );

        return response()->json([
            'message' => 'Data omset bulanan berhasil disimpan.',
            'data' => $revenue,
        ]);
    }
}
