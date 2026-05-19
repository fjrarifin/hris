<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Exports\HRApprovalExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LeaveRequest;
use App\Models\EmployeePermission;
use App\Models\PublicHolidayRequest;
use App\Models\OvertimeRequest;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;
use App\Notifications\RequestStatusNotification;
use Maatwebsite\Excel\Facades\Excel;

class ApprovalController extends Controller
{
    public function all()
    {
        $requests = LeaveRequest::with('user.karyawan')
            ->whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->latest()
            ->get()
            ->map(fn ($request) => (object) ['type' => 'leave', 'request' => $request])
            ->concat(
                PublicHolidayRequest::with('user.karyawan', 'holiday')
                    ->whereNotNull('manager_approved_at')
                    ->whereNull('hr_approved_at')
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->latest()
                    ->get()
                    ->map(fn ($request) => (object) ['type' => 'ph', 'request' => $request])
            )
            ->concat(
                EmployeePermission::with('user.karyawan')
                    ->whereNotNull('manager_approved_at')
                    ->whereNull('hr_approved_at')
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->latest()
                    ->get()
                    ->map(fn ($request) => (object) ['type' => 'permission', 'request' => $request])
            )
            ->concat(
                OvertimeRequest::with('user.karyawan', 'requestedBy')
                    ->whereNotNull('requested_by_user_id')
                    ->whereNull('hr_approved_at')
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->latest()
                    ->get()
                    ->map(fn ($request) => (object) ['type' => 'overtime', 'request' => $request])
            )
            ->sortByDesc(fn ($item) => $item->request->created_at)
            ->values();

        return view('hr.approval.all', compact('requests'));
    }

    public function index($type)
    {
        $model = $this->resolveModel($type);

        $relations = match ($type) {
            'ph' => ['user', 'holiday'],
            'overtime' => ['user.karyawan', 'requestedBy'],
            'permission' => ['user.karyawan'],
            default => ['user'],
        };

        $requests = $model::with($relations)
            ->when($type === 'overtime', fn ($query) => $query->whereNotNull('requested_by_user_id'))
            ->when($type !== 'overtime', fn ($query) => $query->whereNotNull('manager_approved_at'))
            ->latest()
            ->get();

        return view('hr.approval.index', compact('requests', 'type'));
    }

    public function export($type)
    {
        $this->resolveModel($type);

        return Excel::download(
            new HRApprovalExport($type),
            'HR_Approval_' . strtoupper($type) . '_' . now()->format('Ymd_His') . '.xlsx'
        );
    }


    public function approve($type, $id)
    {
        $model = $this->resolveModel($type)::with('user')->findOrFail($id);

        $model->update([
            'hr_approved_at' => now(),
            'hr_approved_by' => Auth::id(),
            'status' => 'approved',
        ]);

        $this->notifyUser($model, $type, 'approved');

        return back()->with('success', 'Pengajuan disetujui HR');
    }

    public function reject(Request $request, $type, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $model = $this->resolveModel($type)::with('user')->findOrFail($id);

        $model->update([
            'status' => 'rejected',
            'reject_reason' => $request->reason,
            'hr_approved_by' => Auth::id(),
        ]);

        $this->notifyUser($model, $type, 'rejected');

        return back()->with('success', 'Pengajuan ditolak HR');
    }

    private function resolveModel($type)
    {
        return match ($type) {
            'leave' => LeaveRequest::class,
            'ph' => PublicHolidayRequest::class,
            'permission' => EmployeePermission::class,
            'overtime' => OvertimeRequest::class,
            default => abort(404),
        };
    }

    private function notifyUser($model, $type, $status)
    {
        match ($type) {
            'leave' => $model->user->notify(
                new LeaveStatusNotification($model, $status)
            ),
            'ph' => $model->user->notify(
                new PublicHolidayStatusNotification($model, $status)
            ),
            'permission' => $model->user->notify(
                new RequestStatusNotification($model, 'IZIN', $status)
            ),
            'overtime' => $model->user->notify(
                new RequestStatusNotification($model, 'LEMBUR', $status)
            ),
        };
    }
}
