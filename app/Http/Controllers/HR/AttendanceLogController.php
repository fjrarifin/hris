<?php

namespace App\Http\Controllers\HR;

use App\Exports\FingerspotAttendanceLogExport;
use App\Http\Controllers\Controller;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->validatedFilters($request);

        $startDate = $data['start_date'] ?? now()->toDateString();
        $endDate = $data['end_date'] ?? now()->toDateString();
        $q = trim((string) ($data['q'] ?? ''));
        $statusScan = $data['status_scan'] ?? null;

        [$start, $end] = $this->dateRange($startDate, $endDate);

        $logs = $this->attendanceQuery($start, $end, $q, $statusScan)
            ->orderByDesc('scan_date')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'total' => $logs->total(),
            'period_total' => FingerspotAttendanceLog::whereBetween('scan_date', [$start, $end])->count(),
            'unique_pin' => FingerspotAttendanceLog::whereBetween('scan_date', [$start, $end])->distinct('pin')->count('pin'),
            'last_sync' => optional(FingerspotAttendanceLog::latest('updated_at')->first())->updated_at,
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

    public function export(Request $request)
    {
        $data = $this->validatedFilters($request);
        $startDate = $data['start_date'] ?? now()->toDateString();
        $endDate = $data['end_date'] ?? now()->toDateString();
        $q = trim((string) ($data['q'] ?? ''));
        $statusScan = $data['status_scan'] ?? null;
        [$start, $end] = $this->dateRange($startDate, $endDate);

        $logs = $this->attendanceQuery($start, $end, $q, $statusScan)
            ->orderBy('scan_date')
            ->get();

        $fileName = 'Log_Absensi_Fingerspot_' . $start->format('Ymd') . '_' . $end->format('Ymd') . '.xlsx';

        return Excel::download(new FingerspotAttendanceLogExport($logs), $fileName);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:100'],
            'status_scan' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function dateRange(string $startDate, string $endDate): array
    {
        return [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ];
    }

    private function attendanceQuery(Carbon $start, Carbon $end, string $q, ?string $statusScan)
    {
        $employeePins = $this->employeePins($q);

        return FingerspotAttendanceLog::query()
            ->with('karyawan')
            ->whereBetween('scan_date', [$start, $end])
            ->when($q !== '', function ($query) use ($q, $employeePins) {
                $query->where(function ($subQuery) use ($q, $employeePins) {
                    $subQuery->where('pin', 'like', "%{$q}%")
                        ->orWhere('cloud_id', 'like', "%{$q}%")
                        ->orWhere('source', 'like', "%{$q}%")
                        ->orWhere('trans_id', 'like', "%{$q}%");

                    if ($employeePins->isNotEmpty()) {
                        $subQuery->orWhereIn('pin', $employeePins);
                    }
                });
            })
            ->when($statusScan !== null && $statusScan !== '', fn ($query) => $query->where('status_scan', $statusScan));
    }

    private function employeePins(string $q)
    {
        if ($q === '') {
            return collect();
        }

        return Karyawan::query()
            ->where(function ($query) use ($q) {
                $query->where('pin', 'like', "%{$q}%")
                    ->orWhere('nik', 'like', "%{$q}%")
                    ->orWhere('nama_karyawan', 'like', "%{$q}%");
            })
            ->whereNotNull('pin')
            ->pluck('pin')
            ->filter()
            ->values();
    }
}
