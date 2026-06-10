<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeePermission;
use App\Models\ExtraOffRequest;
use App\Models\FingerspotAttendanceLog;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHolidayRequest;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;
use App\Notifications\RequestStatusNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrApprovalController extends Controller
{
    public function index(Request $request, string $type): JsonResponse
    {
        $model = $this->model($type);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'waiting_hr', 'approved', 'rejected', 'cancelled'])],
        ]);
        $status = $validated['status'] ?? 'waiting_hr';

        $requests = $this->query($model, $type)
            ->when(
                $status === 'waiting_hr',
                fn (Builder $query) => $query
                    ->whereNull('hr_approved_at')
                    ->whereNotIn('status', ['rejected', 'cancelled'])
            )
            ->when(
                $status === 'approved',
                fn (Builder $query) => $query->where('status', 'approved')->whereNotNull('hr_approved_at')
            )
            ->when(
                in_array($status, ['rejected', 'cancelled'], true),
                fn (Builder $query) => $query->where('status', $status)
            )
            ->latest()
            ->get()
            ->map(fn (object $item) => $this->serialize($type, $item));

        return response()->json([
            'type' => $type,
            'requests' => $requests,
        ]);
    }

    public function decide(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:255', 'required_if:decision,rejected'],
        ]);

        $item = $this->query($this->model($type), $type)->findOrFail($id);
        abort_unless($this->canDecide($item), 422, 'Pengajuan ini sudah diproses.');

        if ($type === 'ph' && $validated['decision'] === 'approved' && ! $this->hasWorkedOnPublicHoliday($item)) {
            throw ValidationException::withMessages([
                'decision' => 'PH tidak dapat disetujui karena karyawan tidak memiliki scan pada hari libur nasional tersebut.',
            ]);
        }

        $item->update([
            'status' => $validated['decision'],
            'reject_reason' => $validated['decision'] === 'rejected' ? $validated['reason'] : null,
            'hr_approved_at' => $validated['decision'] === 'approved' ? now() : null,
            'hr_approved_by' => $request->user()->id,
        ]);

        $this->notify($type, $item, $validated['decision'], $validated['reason'] ?? null);

        return response()->json([
            'message' => $validated['decision'] === 'approved'
                ? 'Pengajuan berhasil disetujui HRD.'
                : 'Pengajuan berhasil ditolak HRD.',
        ]);
    }

    public function cancel(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $item = $this->query($this->model($type), $type)->findOrFail($id);
        abort_unless($this->canCancel($item), 422, 'Hanya pengajuan final yang dapat dibatalkan.');

        $item->update([
            'status' => 'cancelled',
            'reject_reason' => $validated['reason'],
            'hr_approved_by' => $request->user()->id,
        ]);

        $this->notify($type, $item, 'cancelled', $validated['reason']);

        return response()->json([
            'message' => 'Pengajuan berhasil dibatalkan oleh HRD.',
        ]);
    }

    private function model(string $type): string
    {
        return match ($type) {
            'leave' => LeaveRequest::class,
            'overtime' => OvertimeRequest::class,
            'ph' => PublicHolidayRequest::class,
            'extra_off' => ExtraOffRequest::class,
            'permission' => EmployeePermission::class,
            default => abort(404),
        };
    }

    private function query(string $model, string $type): Builder
    {
        return $model::query()
            ->with($type === 'ph' ? ['user.karyawan', 'holiday'] : ['user.karyawan'])
            ->when(
                $type === 'overtime',
                fn (Builder $query) => $query->whereNotNull('requested_by_user_id'),
                fn (Builder $query) => $query->whereNotNull('manager_approved_at')
            );
    }

    private function serialize(string $type, object $item): array
    {
        $employee = $item->user?->karyawan;
        $workflowStatus = match (true) {
            $item->status === 'rejected' => 'rejected',
            $item->status === 'cancelled' => 'cancelled',
            $item->status === 'approved' && $item->hr_approved_at !== null => 'approved',
            default => 'waiting_hr',
        };

        return [
            'id' => $item->id,
            'type' => $type,
            'employee_nik' => $employee?->nik ?? $item->user?->username,
            'employee_name' => $employee?->nama_karyawan ?? $item->user?->name ?? '-',
            'department' => $employee?->departement ?? $employee?->divisi ?? '-',
            'label' => match ($type) {
                'leave' => LeaveRequest::LEAVE_TYPES[$item->leave_type] ?? $item->leave_type,
                'ph' => $item->holiday?->name ?? 'Public Holiday',
                'extra_off' => 'Extra Off',
                'permission' => $item->type === 'sakit' ? 'Sakit' : 'Izin',
                default => 'Lembur',
            },
            'date' => match ($type) {
                'leave' => $item->start_date,
                'ph' => $item->claim_date,
                'extra_off' => $item->claim_date,
                default => $item->date,
            },
            'end_date' => match ($type) {
                'leave' => $item->end_date,
                'permission' => ($item->end_date ?? $item->date)?->toDateString(),
                default => null,
            },
            'time' => $type === 'overtime' ? "{$item->start_time} - {$item->end_time}" : null,
            'reason' => $item->reason ?? null,
            'document_url' => $type === 'permission' && $item->document
                ? asset('storage/'.$item->document)
                : null,
            'status' => $workflowStatus,
            'source_status' => $item->status,
            'reject_reason' => $item->reject_reason ?? null,
            'hr_approved_at' => $item->hr_approved_at,
            'can_decide' => $this->canDecide($item),
            'can_cancel' => $this->canCancel($item),
        ];
    }

    private function notify(string $type, object $item, string $decision, ?string $reason): void
    {
        match ($type) {
            'leave' => $item->user->notify(new LeaveStatusNotification($item, $decision, $reason)),
            'ph' => $item->user->notify(new PublicHolidayStatusNotification($item, $decision)),
            'extra_off' => $item->user->notify(new RequestStatusNotification($item, 'EO', $decision)),
            'permission' => $item->user->notify(new RequestStatusNotification($item, 'IZIN', $decision)),
            'overtime' => $item->user->notify(new RequestStatusNotification($item, 'LEMBUR', $decision)),
        };
    }

    private function canDecide(object $item): bool
    {
        return $item->hr_approved_at === null && ! in_array($item->status, ['rejected', 'cancelled'], true);
    }

    private function canCancel(object $item): bool
    {
        return $item->status === 'approved' && $item->hr_approved_at !== null;
    }

    private function hasWorkedOnPublicHoliday(PublicHolidayRequest $item): bool
    {
        $pin = $item->user?->karyawan?->pin;

        return $pin !== null
            && $item->holiday !== null
            && $item->holiday->is_active
            && FingerspotAttendanceLog::query()
                ->where('pin', $pin)
                ->whereDate('scan_date', $item->holiday->holiday_date)
                ->exists();
    }
}
