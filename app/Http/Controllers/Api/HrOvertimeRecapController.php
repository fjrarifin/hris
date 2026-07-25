<?php

namespace App\Http\Controllers\Api;

use App\Exports\HrOvertimeRecapExport;
use App\Http\Controllers\Controller;
use App\Models\MasterDepartment;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HrOvertimeRecapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string'],
        ]);

        $queryResult = $this->buildQuery($validated);
        $serialized = $queryResult['records']->map(fn ($item) => $this->serializeItem($item));

        $summary = $this->buildSummary($serialized);

        $departments = MasterDepartment::query()
            ->where('is_active', true)
            ->pluck('name')
            ->values()
            ->all();

        return response()->json([
            'summary' => $summary,
            'records' => $serialized->values()->all(),
            'department_options' => $departments,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'department' => $validated['department'] ?? '',
                'start_date' => $queryResult['start_date'],
                'end_date' => $queryResult['end_date'],
                'status' => $validated['status'] ?? 'all',
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string'],
        ]);

        $queryResult = $this->buildQuery($validated);
        $serialized = $queryResult['records']->map(fn ($item) => $this->serializeItem($item));

        $filename = 'Rekap_Lembur_Karyawan_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new HrOvertimeRecapExport($serialized), $filename);
    }

    private function buildQuery(array $validated): array
    {
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $search = trim((string) ($validated['search'] ?? ''));
        $department = trim((string) ($validated['department'] ?? ''));
        $status = trim((string) ($validated['status'] ?? 'all'));

        $records = OvertimeRequest::query()
            ->with(['user.karyawan', 'requestedBy.karyawan'])
            ->when($startDate, fn (Builder $q) => $q->where('date', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->where('date', '<=', $endDate))
            ->when($status !== 'all' && $status !== '', function (Builder $q) use ($status) {
                if ($status === 'waiting_hr') {
                    $q->whereNull('hr_approved_at')->whereNotIn('status', ['rejected', 'cancelled']);
                } elseif ($status === 'approved') {
                    $q->where('status', 'approved')->whereNotNull('hr_approved_at');
                } else {
                    $q->where('status', $status);
                }
            })
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->whereHas('user.karyawan', function (Builder $emp) use ($search) {
                        $emp->where('nama_karyawan', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%");
                    })->orWhereHas('user', function (Builder $u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                          ->orWhere('username', 'like', "%{$search}%");
                    });
                });
            })
            ->when($department !== '', function (Builder $q) use ($department) {
                $q->whereHas('user.karyawan', function (Builder $emp) use ($department) {
                    $emp->where('departement', $department)
                        ->orWhere('divisi', $department);
                });
            })
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'asc')
            ->get();

        return [
            'records' => $records,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function serializeItem(OvertimeRequest $item): array
    {
        $employee = $item->user?->karyawan;
        $dateStr = $item->date ? $item->date->toDateString() : '';
        $startTime = (string) $item->start_time;
        $endTime = (string) $item->end_time;

        $durationMinutes = $this->calculateOvertimeMinutes($dateStr, $startTime, $endTime);

        $workflowStatus = match (true) {
            $item->status === 'rejected' => 'rejected',
            $item->status === 'cancelled' => 'cancelled',
            $item->status === 'approved' && $item->hr_approved_at !== null => 'approved',
            default => 'waiting_hr',
        };

        $statusLabel = match ($workflowStatus) {
            'approved' => 'Disetujui HRD',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => 'Menunggu HRD',
        };

        return [
            'id' => $item->id,
            'date' => $dateStr,
            'date_formatted' => $item->date ? Carbon::parse($item->date)->isoFormat('D MMM YYYY') : '-',
            'employee_nik' => $employee?->nik ?? $item->user?->username ?? '-',
            'employee_name' => $employee?->nama_karyawan ?? $item->user?->name ?? '-',
            'department' => $employee?->departement ?? $employee?->divisi ?? '-',
            'position' => $employee?->posisi ?? $employee?->jabatan ?? '-',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'duration_formatted' => $this->formatDuration($durationMinutes),
            'reason' => $item->reason ?? '-',
            'status' => $workflowStatus,
            'status_label' => $statusLabel,
            'source_status' => $item->status,
            'reject_reason' => $item->reject_reason,
            'hr_approved_at' => $item->hr_approved_at?->toDateTimeString(),
            'requested_by_name' => $item->requestedBy?->name ?? '-',
        ];
    }

    private function calculateOvertimeMinutes(string $date, string $startTime, string $endTime): int
    {
        if (! $date || ! $startTime || ! $endTime) {
            return 0;
        }

        try {
            $start = Carbon::parse($date . ' ' . $startTime);
            $end = Carbon::parse($date . ' ' . $endTime);

            if ($end->lt($start)) {
                $end->addDay();
            }

            return max(0, (int) $start->diffInMinutes($end));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 jam';
        }

        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours} jam";
        }
        if ($remMinutes > 0) {
            $parts[] = "{$remMinutes} menit";
        }

        return implode(' ', $parts);
    }

    private function buildSummary(Collection $records): array
    {
        $totalMinutes = $records->sum('duration_minutes');
        $totalHours = round($totalMinutes / 60, 1);

        $uniqueEmployees = $records->pluck('employee_nik')->unique()->count();
        $approvedCount = $records->where('status', 'approved')->count();
        $waitingCount = $records->where('status', 'waiting_hr')->count();

        return [
            'total_records' => $records->count(),
            'total_employees' => $uniqueEmployees,
            'total_minutes' => $totalMinutes,
            'total_hours' => $totalHours,
            'total_duration_formatted' => $this->formatDuration($totalMinutes),
            'approved_count' => $approvedCount,
            'waiting_count' => $waitingCount,
        ];
    }
}
