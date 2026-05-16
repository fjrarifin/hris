<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;

class ApprovalController extends Controller
{
    public function index($type)
    {
        $model = $this->resolveModel($type);

        $relations = $type === 'ph' ? ['user', 'holiday'] : ['user'];

        $requests = $model::with($relations)
            ->whereNotNull('manager_approved_at')
            ->when($type === 'leave', function ($query) {
                $query->whereNotNull('second_manager_approved_at');
            })
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('hr.approval.index', compact('requests', 'type'));
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
        };
    }
}
