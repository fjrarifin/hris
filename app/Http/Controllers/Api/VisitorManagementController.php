<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VisitorLog::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('nomor_kunjungan', 'like', "%{$search}%")
                    ->orWhere('nomor_identitas', 'like', "%{$search}%")
                    ->orWhere('nama_visitor', 'like', "%{$search}%")
                    ->orWhere('instansi', 'like', "%{$search}%")
                    ->orWhere('tujuan_bertemu', 'like', "%{$search}%")
                    ->orWhere('keperluan', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($date = $request->input('date')) {
            $query->whereDate('waktu_masuk', $date);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $logs = $query->orderByDesc('id')->paginate($perPage);

        $today = now()->toDateString();
        $startOfWeek = now()->startOfWeek()->toDateTimeString();

        $kpi = [
            'today_count' => VisitorLog::whereDate('waktu_masuk', $today)->count(),
            'this_week_count' => VisitorLog::where('waktu_masuk', '>=', $startOfWeek)->count(),
            'total_count' => VisitorLog::count(),
        ];

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'kpi' => $kpi,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $log = VisitorLog::findOrFail($id);
        $log->delete();

        return response()->json([
            'message' => 'Data log visitor berhasil dihapus.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = VisitorLog::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('nomor_kunjungan', 'like', "%{$search}%")
                    ->orWhere('nomor_identitas', 'like', "%{$search}%")
                    ->orWhere('nama_visitor', 'like', "%{$search}%")
                    ->orWhere('instansi', 'like', "%{$search}%")
                    ->orWhere('tujuan_bertemu', 'like', "%{$search}%")
                    ->orWhere('keperluan', 'like', "%{$search}%");
            });
        }

        if ($date = $request->input('date')) {
            $query->whereDate('waktu_masuk', $date);
        }

        $records = $query->orderByDesc('id')->get();
        $fileName = 'buku-tamu-visitor-' . now()->format('YmdHis') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($records) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($handle, [
                'No',
                'No. Kunjungan',
                'No. Identitas',
                'Nama Visitor',
                'No. HP / WA',
                'Asal Instansi / PT',
                'Tujuan Bertemu',
                'Keperluan Kunjungan',
                'Waktu Registrasi (WIB)',
                'IP Address',
                'Perangkat / Browser',
            ]);

            $no = 1;
            foreach ($records as $item) {
                fputcsv($handle, [
                    $no++,
                    $item->nomor_kunjungan,
                    $item->nomor_identitas,
                    $item->nama_visitor,
                    $item->no_hp ?: '-',
                    $item->instansi ?: '-',
                    $item->tujuan_bertemu ?: '-',
                    $item->keperluan,
                    $item->waktu_masuk ? $item->waktu_masuk->format('Y-m-d H:i:s') : '-',
                    $item->ip_address ?: '-',
                    $item->user_agent ?: '-',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
