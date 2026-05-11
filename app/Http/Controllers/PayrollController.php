<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollComponent;
use App\Models\Karyawan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\SlipGajiMail;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendSlipGajiJob;
use App\Models\PayrollNew;
use Illuminate\View\Component;

class PayrollController extends Controller
{
    public function index()
    {
        $payrolls = Payroll::with('karyawan')->orderBy('periode_start', 'desc')->get();
        return view('hr.payroll.index', compact('payrolls'));
    }

    public function form()
    {
        return view('hr.payroll.upload');
    }

    public function show($id)
    {
        $payroll = Payroll::with([
            'karyawan',
            'items.component'
        ])->findOrFail($id);

        return view('hr.payroll.slip', compact('payroll'));
    }

    public function downloadTemplate()
    {
        $filePath = public_path('template/template_payroll.xlsx');

        if (!file_exists($filePath)) {
            throw new \Exception('File tidak ditemukan');
        }

        return response()->download($filePath);
    }

    public function blastEmail()
    {
        try {

            $latest = Payroll::orderBy('periode_start', 'desc')->first();

            if (!$latest) {
                return response()->json([
                    'status' => false,
                    'error'  => 'Data payroll tidak tersedia'
                ]);
            }

            $payrolls = Payroll::where('periode_start', $latest->periode_start)
                ->where('periode_end', $latest->periode_end)
                ->get();

            foreach ($payrolls as $payroll) {
                SendSlipGajiJob::dispatch($payroll);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Blast email sedang diproses di background'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error'  => $e->getMessage()
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Helper: password dari tanggal lahir format ddmmyy
    // contoh: 20 Nov 1995 → "201195"
    // Fallback ke NIK jika tanggal_lahir kosong
    // ─────────────────────────────────────────────────────────
    private function pdfPassword(Payroll $payroll): string
    {
        $tglLahir = $payroll->karyawan->tanggal_lahir ?? null;

        if (!$tglLahir) {
            return $payroll->karyawan->nik ?? '123456';
        }

        return \Carbon\Carbon::parse($tglLahir)->format('dmy');
    }

    // ─────────────────────────────────────────────────────────
    // Helper: nama file PDF
    // ─────────────────────────────────────────────────────────
    private function pdfFileName(Payroll $payroll): string
    {
        return 'Slip_Gaji_'
            . str_replace(' ', '_', $payroll->karyawan->nama_karyawan)
            . '_' . date('M_Y', strtotime($payroll->periode_start))
            . '.pdf';
    }

    // ─────────────────────────────────────────────────────────
    // Helper: build PDF dengan password encryption
    // Kompatibel DomPDF v1 & v2
    // ─────────────────────────────────────────────────────────
    private function buildPdf(Payroll $payroll, string $password): string
    {
        $karyawan = $payroll->karyawan;

        // 🔥 Tentukan view berdasarkan BPJS
        $view = 'hr.payroll.pdf'; // default

        if ($karyawan && $karyawan->bpjs == 1) {
            $view = 'hr.payroll.pdf_bpjs';
        }

        $pdf = Pdf::loadView($view, compact('payroll'))
            ->setPaper('a5', 'landscape');

        // Render agar canvas/DOM siap
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();

        // DomPDF v2: canvas IS the CPDF instance (Dompdf\Adapter\CPDF)
        // method setEncryption(userPass, ownerPass, permissions)
        if (method_exists($canvas, 'setEncryption')) {
            $canvas->setEncryption($password, '', ['print', 'copy']);

            // DomPDF v1 fallback: lewat get_cpdf()
        } elseif (method_exists($canvas, 'get_cpdf')) {
            $canvas->get_cpdf()->setEncryption($password, '', ['print', 'copy']);

            // Fallback terakhir: lewat property cpdf langsung
        } elseif (isset($canvas->cpdf) && method_exists($canvas->cpdf, 'setEncryption')) {
            $canvas->cpdf->setEncryption($password, '', ['print', 'copy']);
        }

        return $dompdf->output();
    }

    public function download($id)
    {
        $payroll = Payroll::with([
            'karyawan',
            'items.component'
        ])->findOrFail($id);

        // dd($payroll->items->map(function ($item) {
        //     return [
        //         'id_item' => $item->id,
        //         'nama_komponen' => $item->component->nama,
        //         'amount' => $item->amount,
        //         'type' => $item->type
        //     ];
        // })->toArray());

        $password   = $this->pdfPassword($payroll);
        $pdfContent = $this->buildPdf($payroll, $password);

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->pdfFileName($payroll) . '"',
        ]);
    }

    public function sendEmail($id)
    {
        try {
            $payroll = Payroll::with([
                'karyawan',
                'items.component'
            ])->findOrFail($id);

            $email = $payroll->karyawan->email ?? null;

            if (!$email) {
                return response()->json([
                    'status' => false,
                    'error'  => 'Email karyawan ' . $payroll->karyawan->nama_karyawan . ' tidak tersedia.'
                ]);
            }

            $password   = $this->pdfPassword($payroll);
            $pdfContent = $this->buildPdf($payroll, $password);

            Mail::to($email)->send(
                new SlipGajiMail($payroll, $pdfContent, $this->pdfFileName($payroll), $password)
            );

            return response()->json([
                'status'  => true,
                'message' => 'Slip gaji berhasil dikirim ke ' . $email
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error'  => $e->getMessage()
            ]);
        }
    }

    public function syncKaryawanFromGsheet()
    {
        try {
            $url = "https://docs.google.com/spreadsheets/d/e/2PACX-1vS3nGWBtIVnYeo4tuNP4R_rS8E0t41fs3OaDjBAK6e_m1pRBeebdiVpzFO6jsDNGg/pub?gid=228519111&single=true&output=csv";

            $csv = file_get_contents($url);
            if (!$csv) {
                return response()->json(['status' => false, 'error' => 'Gagal mengambil data dari Google Sheets']);
            }

            $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));

            $dataStartIndex = 5;

            if (!isset($rows[3])) {
                return response()->json(['status' => false, 'error' => 'Header tidak ditemukan']);
            }

            $headerRow = $rows[3];

            // 🔥 Mapping CSV → DB Component
            $columnMapping = [
                'Gaji Pokok' => 'Gaji Pokok',
                'Tunjangan Jabatan' => 'Tunjangan Jabatan',
                'JKN Perusahaan (4%)' => 'JKN Perusahaan',
                'JHT Perusahaan (3.7%)' => 'JHT Perusahaan',
                'JP Perusahaan (2%)' => 'JP Perusahaan',
                'JKK Perusahaan (0.54%)' => 'JKK Perusahaan',
                'JKM Perusahaan (0.30%)' => 'JKM Perusahaan',
                'Pot. JKN Karyawan (1%)' => 'Pot. JKN Karyawan',
                'Pot. JHT Karyawan (2%)' => 'Pot. JHT Karyawan',
                'Pot. JP Karyawan (1%)' => 'Pot. JP Karyawan',
                'Total Tunj. Tidak Tetap' => 'Tunjangan Tidak Tetap',
                'Nominal Lembur' => 'Lembur',
                'Nominal PIKET' => 'Nominal PIKET',
                'Lain-lain' => 'Lain-lain',
                'Trainning' => 'Training',
                'THR' => 'THR',
                'Kekurangan Gaji' => 'Kekurangan Bulan Sebelumnya',
                'Pot. SWC (Informasi)' => 'Potongan Sakit Tanpa Surat',
                'Pot. IZIN' => 'Potongan Izin',
                'Pot. Kasbon' => 'Potongan Kasbon',
                'Kelebihan Gaji' => 'Kelebihan Gaji',
                'Pot. Denda Kehilangan Aset' => 'Pot. Denda Kehilangan Aset',
                'Pot. PPH 21' => 'PPh21',
                'Service' => 'Service',
                'Bonus' => 'Bonus',
            ];

            // 🔥 Load komponen dari DB
            $components = DB::table('payroll_components')
                ->where('is_active', 1)
                ->get()
                ->keyBy('nama');

            DB::beginTransaction();

            $inserted = 0;
            $skipped = 0;
            $errors = [];

            foreach ($rows as $i => $row) {
                if ($i < $dataStartIndex) continue;

                $nik = trim($row[1] ?? '');
                if (!$nik) continue;

                // 🔥 Ambil data kehadiran
                $hadir = (int) preg_replace('/[^0-9]/', '', $row[20] ?? '0');
                $libur = (int) preg_replace('/[^0-9]/', '', $row[17] ?? '0');
                $izin = (int) preg_replace('/[^0-9]/', '', $row[18] ?? '0');
                $sakit_surat = (int) preg_replace('/[^0-9]/', '', $row[12] ?? '0');
                $sakit_tanpa_surat = (int) preg_replace('/[^0-9]/', '', $row[16] ?? '0');
                $libur_nasional = (int) preg_replace('/[^0-9]/', '', $row[19] ?? '0');
                $ph = (int) preg_replace('/[^0-9]/', '', $row[13] ?? '0');

                // Ambil data bpjs perusahaan
                $jknPerusahaan = (int) preg_replace('/[^0-9]/', '', $row[30] ?? '0');

                // Ambil data potongan
                $potongan = (int) preg_replace('/[^0-9]/', '', $row[51] ?? '0');
                $totalPotongan = $potongan - $jknPerusahaan;

                // Ambil data total dibayarkan
                $pendapatan = (int) preg_replace('/[^0-9]/', '', $row[44] ?? '0');
                $totalPendapatan = $pendapatan - $jknPerusahaan;

                $totalDibayarkan = (int) preg_replace('/[^0-9]/', '', $row[57] ?? '0');

                // 🔥 Ambil periode
                $periodeStart = null;
                $periodeEnd = null;

                foreach ($headerRow as $colIndex => $headerName) {
                    $headerName = trim($headerName ?? '');

                    if ($headerName === 'Priode Awal') {
                        $periodeStart = trim($row[$colIndex] ?? '');
                    }

                    if ($headerName === 'Periode akhir') {
                        $periodeEnd = trim($row[$colIndex] ?? '');
                    }
                }

                if (!$periodeStart || !$periodeEnd) {
                    $errors[] = "Baris " . ($i + 1) . ": Periode tidak ditemukan";
                    continue;
                }

                try {
                    $periodeStart = \Carbon\Carbon::createFromFormat('d/m/Y', $periodeStart)->format('Y-m-d');
                    $periodeEnd   = \Carbon\Carbon::createFromFormat('d/m/Y', $periodeEnd)->format('Y-m-d');
                } catch (\Exception $e) {
                    $errors[] = "Baris " . ($i + 1) . ": Format tanggal salah";
                    continue;
                }

                // 🔥 Cek duplikat
                $existing = Payroll::where('karyawan_nik', $nik)
                    ->where('periode_start', $periodeStart)
                    ->where('periode_end', $periodeEnd)
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // 🔥 Create payroll
                $payroll = Payroll::create([
                    'karyawan_nik'  => $nik,
                    'periode_start' => $periodeStart,
                    'periode_end'   => $periodeEnd,
                    'hadir'         => $hadir,
                    'libur'         => $libur,
                    'izin'          => $izin,
                    'sakit_surat'   => $sakit_surat,
                    'sakit_tanpa_surat' => $sakit_tanpa_surat,
                    'libur_nasional' => $libur_nasional,
                    'total_pendapatan' => $totalPendapatan,
                    'total_potongan' => $totalPotongan,
                    'total_dibayarkan' => $totalDibayarkan,
                    'ph' => $ph,

                ]);

                // 🔥 Variable penampung total
                $gajiPokok = 0;
                $tunjanganJabatan = 0;
                $tunjanganTidakTetap = 0;
                $jknPerusahaan = 0;
                $kasbon = 0;

                $itemCount = 0;

                foreach ($headerRow as $colIndex => $headerName) {
                    $headerName = trim($headerName ?? '');

                    if (!isset($columnMapping[$headerName])) continue;

                    $componentName = $columnMapping[$headerName];

                    if (!isset($components[$componentName])) continue;

                    $value = trim($row[$colIndex] ?? '');
                    if ($value === '' || $value === '0') continue;

                    $amount = (int) preg_replace('/[^0-9]/', '', $value);
                    if ($amount <= 0) continue;

                    // // 🔥 Tangkap untuk total
                    // switch (strtolower($componentName)) {
                    //     case 'gaji pokok':
                    //         $gajiPokok = $amount;
                    //         break;

                    //     case 'tunjangan jabatan':
                    //         $tunjanganJabatan = $amount;
                    //         break;

                    //     case 'tunjangan tidak tetap':
                    //         $tunjanganTidakTetap = $amount;
                    //         break;

                    //     case 'jkn perusahaan':
                    //         $jknPerusahaan = $amount;
                    //         break;

                    //     case 'potongan kasbon':
                    //         $kasbon = $amount;
                    //         break;
                    // }

                    $comp = $components[$componentName];

                    PayrollItem::create([
                        'payroll_id'   => $payroll->id,
                        'component_id' => $comp->id,
                        'type'         => $comp->type,
                        'amount'       => $amount,
                    ]);

                    $itemCount++;
                }

                // 🔥 HITUNG TOTAL (SETELAH LOOP)
                // $totalPendapatan =
                //     $gajiPokok +
                //     $tunjanganJabatan +
                //     $tunjanganTidakTetap +
                //     $jknPerusahaan;

                // $totalPotongan = $jknPerusahaan + $kasbon;

                // $totalDibayarkan = $totalPendapatan - $totalPotongan;

                // $payroll->update([
                //     'total_pendapatan' => $totalPendapatan,
                //     'total_potongan'   => $totalPotongan,
                //     'total_dibayarkan' => $totalDibayarkan,
                // ]);

                $inserted++;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sync berhasil',
                'data' => [
                    'inserted' => $inserted,
                    'skipped' => $skipped,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // public function upload(Request $request)
    // {
    //     $file = $request->file('file');

    //     $rows = Excel::toArray([], $file)[0];

    //     $normalizeKey = function ($value) {
    //         $value = strtolower(trim($value));
    //         $value = preg_replace('/[^a-z0-9]/', '_', $value);
    //         $value = preg_replace('/_+/', '_', $value);
    //         return trim($value, '_');
    //     };

    //     $parseAmount = function ($value) {
    //         if (is_numeric($value)) {
    //             return (float) $value;
    //         }

    //         $clean = preg_replace('/[^0-9]/', '', $value);
    //         return $clean === '' ? 0 : (float) $clean;
    //     };

    //     $originalHeaderByNormalized = [];
    //     foreach ($rows[0] as $headerValue) {
    //         $originalHeaderByNormalized[$normalizeKey($headerValue)] = trim($headerValue);
    //     }

    //     $header = array_map(function ($h) use ($normalizeKey) {
    //         return $normalizeKey($h);
    //     }, $rows[0]);

    //     DB::beginTransaction();

    //     try {
    //         foreach ($rows as $index => $row) {

    //             if ($index == 0) continue;

    //             $data = array_combine($header, $row);
    //             $data = array_map(function ($v) {
    //                 return is_string($v) ? trim($v) : $v;
    //             }, $data);

    //             if (empty($data['nik'])) {
    //                 throw new \Exception("Kolom NIK kosong / tidak terbaca");
    //             }

    //             $periodeStart = $data['periode_start'];
    //             $periodeEnd   = $data['periode_end'];

    //             if (is_numeric($periodeStart)) {
    //                 $periodeStart = \Carbon\Carbon::instance(
    //                     ExcelDate::excelToDateTimeObject($periodeStart)
    //                 )->format('Y-m-d');
    //             }

    //             if (is_numeric($periodeEnd)) {
    //                 $periodeEnd = \Carbon\Carbon::instance(
    //                     ExcelDate::excelToDateTimeObject($periodeEnd)
    //                 )->format('Y-m-d');
    //             }

    //             $existing = Payroll::where('karyawan_nik', $data['nik'])
    //                 ->where('periode_start', $periodeStart)
    //                 ->where('periode_end', $periodeEnd)
    //                 ->first();

    //             if ($existing) {
    //                 continue; // 🔥 SKIP kalau sudah ada
    //             }

    //             $payroll = Payroll::create([
    //                 'karyawan_nik'  => $data['nik'],
    //                 'periode_start' => $periodeStart,
    //                 'periode_end'   => $periodeEnd,
    //                 'hari_kerja'    => $data['hari_kerja'],
    //                 'hadir'         => $data['hadir'],
    //                 'libur'         => $data['libur'],
    //             ]);

    //             $components = PayrollComponent::all()->keyBy(function ($item) use ($normalizeKey) {
    //                 return $normalizeKey($item->nama);
    //             });

    //             $totalPendapatan = 0;
    //             $totalPotongan   = 0;
    //             $rawTotals = [
    //                 'total_pendapatan' => null,
    //                 'total_potongan' => null,
    //                 'total_dibayarkan' => null,
    //             ];

    //             foreach ($data as $key => $value) {
    //                 if (isset($rawTotals[$key])) {
    //                     $rawTotals[$key] = $parseAmount($value);
    //                 }

    //                 if (in_array($key, [
    //                     'nik',
    //                     'periode_start',
    //                     'periode_end',
    //                     'hari_kerja',
    //                     'hadir',
    //                     'libur'
    //                 ])) continue;

    //                 if ($value === null || $value === '' || $value == 0) continue;

    //                 $component = $components[$key] ?? null;

    //                 if (!$component) {
    //                     continue;
    //                 }

    //                 $amount = $parseAmount($value);
    //                 if ($amount <= 0) {
    //                     continue;
    //                 }

    //                 $itemData = [
    //                     'payroll_id' => $payroll->id,
    //                     'type'       => $component->type,
    //                     'nama_item'  => $originalHeaderByNormalized[$key] ?? $key,
    //                     'amount'     => $amount,
    //                 ];

    //                 if (Schema::hasColumn('payroll_items', 'component_id')) {
    //                     $itemData['component_id'] = $component->id;
    //                 }

    //                 PayrollItem::create($itemData);

    //                 if ($component->type == 'earning') {
    //                     $totalPendapatan += $amount;
    //                 } else {
    //                     $totalPotongan += $amount;
    //                 }
    //             }

    //             if ($rawTotals['total_pendapatan'] !== null) {
    //                 $totalPendapatan = $rawTotals['total_pendapatan'];
    //             }

    //             if ($rawTotals['total_potongan'] !== null) {
    //                 $totalPotongan = $rawTotals['total_potongan'];
    //             }

    //             $totalDibayarkan = $rawTotals['total_dibayarkan'] !== null
    //                 ? $rawTotals['total_dibayarkan']
    //                 : $totalPendapatan - $totalPotongan;

    //             $payroll->update([
    //                 'total_pendapatan' => $totalPendapatan,
    //                 'total_potongan'   => $totalPotongan,
    //                 'total_dibayarkan' => $totalDibayarkan
    //             ]);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'status'  => true,
    //             'message' => 'Upload payroll berhasil'
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'status' => false,
    //             'error'  => $e->getMessage()
    //         ]);
    //     }
    // }
}
