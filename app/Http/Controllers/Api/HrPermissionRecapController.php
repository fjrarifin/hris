<?php

namespace App\Http\Controllers\Api;

use App\Exports\HrPermissionRecapExport;
use App\Http\Controllers\Controller;
use App\Models\EmployeePermission;
use App\Models\MasterDepartment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class HrPermissionRecapController extends Controller
{
    public function index(Request $request)
    {
        $records = $this->records($request);

        return response()->json([
            'records' => $records,
            'department_options' => MasterDepartment::where('is_active', true)->pluck('name')->values(),
            'summary' => [
                'total_records' => $records->count(),
                'total_employees' => $records->pluck('employee_nik')->unique()->count(),
                'permission_days' => $records->where('type', 'izin')->sum('days'),
                'sick_days' => $records->where('type', 'sakit')->sum('days'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(new HrPermissionRecapExport($this->records($request)), 'Rekap_Izin_Sakit_'.now()->format('Ymd_His').'.xlsx');
    }

    public function document(EmployeePermission $permission)
    {
        $path = $permission->document;
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('public')->download($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function records(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', Rule::in(['all', 'izin', 'sakit'])],
            'status' => ['nullable', Rule::in(['all', 'pending', 'waiting_manager', 'waiting_hr', 'approved', 'rejected', 'cancelled'])],
        ]);
        $status = $filters['status'] ?? 'all';

        return EmployeePermission::query()->with('user.karyawan')
            ->when($filters['start_date'] ?? null, fn ($q, $date) => $q->whereRaw('COALESCE(end_date, date) >= ?', [$date]))
            ->when($filters['end_date'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when(($filters['type'] ?? 'all') !== 'all', fn ($q) => $q->where('type', $filters['type']))
            ->when($status === 'waiting_hr', fn ($q) => $q->whereNotNull('manager_approved_at')->whereNull('hr_approved_at')->whereNotIn('status', ['rejected', 'cancelled']))
            ->when($status === 'waiting_manager', fn ($q) => $q->whereNull('manager_approved_at')->whereNotIn('status', ['rejected', 'cancelled']))
            ->when($status === 'pending', fn ($q) => $q->whereNull('hr_approved_at')->whereNotIn('status', ['rejected', 'cancelled']))
            ->when($status === 'approved', fn ($q) => $q->where('status', 'approved')->whereNotNull('hr_approved_at'))
            ->when(in_array($status, ['rejected', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when(trim($filters['search'] ?? ''), function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u->where(function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")
                        ->orWhereHas('karyawan', fn ($e) => $e->where('nama_karyawan', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"));
                }));
            })
            ->when($filters['department'] ?? null, fn ($q, $department) => $q->whereHas('user.karyawan', fn ($e) => $e->where('departement', $department)->orWhere('divisi', $department)))
            ->orderByDesc('date')->orderByDesc('id')->get()->map(function (EmployeePermission $item) use ($filters) {
                $start = $item->date->copy();
                $end = ($item->end_date ?? $item->date)->copy();
                $periodStart = ! empty($filters['start_date']) ? \Carbon\Carbon::parse($filters['start_date'])->max($start) : $start;
                $periodEnd = ! empty($filters['end_date']) ? \Carbon\Carbon::parse($filters['end_date'])->min($end) : $end;
                $status = match (true) {
                    in_array($item->status, ['rejected', 'cancelled'], true) => $item->status,
                    $item->status === 'approved' && $item->hr_approved_at !== null => 'approved',
                    $item->manager_approved_at !== null => 'waiting_hr',
                    default => 'waiting_manager',
                };

                return [
                    'id' => $item->id,
                    'employee_nik' => $item->user?->karyawan?->nik ?? $item->user?->username,
                    'employee_name' => $item->user?->karyawan?->nama_karyawan ?? $item->user?->name ?? '-',
                    'department' => $item->user?->karyawan?->departement ?? $item->user?->karyawan?->divisi ?? '-',
                    'type' => $item->type,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'days' => max(0, (int) $periodStart->diffInDays($periodEnd, false) + 1),
                    'reason' => $item->reason,
                    'has_document' => filled($item->document),
                    'status' => $status,
                    'status_label' => ['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'waiting_hr' => 'Menunggu HRD', 'waiting_manager' => 'Menunggu Atasan'][$status],
                    'reject_reason' => $item->reject_reason,
                ];
            });
    }
}
