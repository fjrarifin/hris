<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\FingerspotAttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:100'],
            'status_scan' => ['nullable', 'string', 'max:20'],
        ]);

        $startDate = $data['start_date'] ?? now()->toDateString();
        $endDate = $data['end_date'] ?? now()->toDateString();
        $q = trim((string) ($data['q'] ?? ''));
        $statusScan = $data['status_scan'] ?? null;

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $logs = FingerspotAttendanceLog::query()
            ->with('karyawan')
            ->whereBetween('scan_date', [$start, $end])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->where('pin', 'like', "%{$q}%")
                        ->orWhereHas('karyawan', function ($karyawanQuery) use ($q) {
                            $karyawanQuery->where('nik', 'like', "%{$q}%")
                                ->orWhere('nama_karyawan', 'like', "%{$q}%")
                                ->orWhere('departement', 'like', "%{$q}%")
                                ->orWhere('unit', 'like', "%{$q}%");
                        });
                });
            })
            ->when($statusScan !== null && $statusScan !== '', fn ($query) => $query->where('status_scan', $statusScan))
            ->orderByDesc('scan_date')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'total' => $logs->total(),
            'period_total' => FingerspotAttendanceLog::whereBetween('scan_date', [$start, $end])->count(),
            'unique_pin' => FingerspotAttendanceLog::whereBetween('scan_date', [$start, $end])->distinct('pin')->count('pin'),
            'last_sync' => FingerspotAttendanceLog::max('updated_at'),
        ];

        return view('hr.attendance.index', compact(
            'logs',
            'summary',
            'startDate',
            'endDate',
            'q',
            'statusScan'
        ));
    }
}
