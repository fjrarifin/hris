<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\MasterDepartment;
use App\Models\MasterPositionTitle;
use App\Models\MasterUnit;
use App\Models\RecruitmentRequest;
use App\Services\RecruitmentRequestApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffRecruitmentRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = RecruitmentRequest::query()
            ->with(['vacancy.candidates', 'requester', 'replacedEmployee', 'directReport'])
            ->where('requester_nik', $request->user()->username)
            ->latest()
            ->get()
            ->map(function ($item) {
                $candidates = $item->vacancy ? $item->vacancy->candidates : collect();
                $item->stats = [
                    'applied' => $candidates->where('status', 'applied')->count(),
                    'screening' => $candidates->where('status', 'screening')->count(),
                    'interview' => $candidates->where('status', 'interview')->count(),
                    'offered' => $candidates->where('status', 'offered')->count(),
                    'hired' => $candidates->where('status', 'hired')->count(),
                    'rejected' => $candidates->where('status', 'rejected')->count(),
                    'total' => $candidates->count(),
                ];
                unset($item->vacancy);
                return $item;
            });

        return response()->json($requests);
    }

    public function options(Request $request): JsonResponse
    {
        $positionTitles = MasterPositionTitle::query()->where('is_active', true)->orderBy('name')->pluck('name');
        $departments = MasterDepartment::query()->where('is_active', true)->orderBy('name')->pluck('name');
        $units = MasterUnit::query()->where('is_active', true)->orderBy('name')->pluck('name');

        $employees = Karyawan::query()
            ->select('nik', 'nama_karyawan', 'posisi_title', 'jabatan', 'departement', 'unit', 'divisi')
            ->orderBy('nama_karyawan')
            ->get();

        $currentUserKaryawan = Karyawan::query()->where('nik', $request->user()->username)->first();
        $canCreate = $this->userHasSubordinates($request->user(), $currentUserKaryawan);

        return response()->json([
            'position_titles' => $positionTitles,
            'departments' => $departments,
            'units' => $units,
            'employees' => $employees,
            'current_user' => $currentUserKaryawan,
            'can_create_request' => $canCreate,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $currentUserKaryawan = Karyawan::query()->where('nik', $request->user()->username)->first();
        if (! $this->userHasSubordinates($request->user(), $currentUserKaryawan)) {
            return response()->json([
                'message' => 'Hanya karyawan yang memiliki bawahan (Leader / Supervisor / Manager / GM) yang dapat mengajukan manpower request.',
            ], 403);
        }

        $payload = $request->validate([
            'title' => ['required', 'string'],
            'department' => ['required', 'string'],
            'unit' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'hiring_type' => ['required', 'in:new_hiring,replacement'],
            'replaced_employee_nik' => ['nullable', 'string', 'required_if:hiring_type,replacement'],
            'replaced_employee_name' => ['nullable', 'string'],
            'replaced_employee_position' => ['nullable', 'string'],
            'employment_status' => ['required', 'in:pkwt,casual'],
            'direct_report_nik' => ['nullable', 'string'],
            'direct_report_name' => ['nullable', 'string'],
            'subordinates' => ['nullable', 'array'],
            'requires_gm' => ['nullable', 'boolean'],
            'request_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
        ]);

        if (! empty($payload['replaced_employee_nik'])) {
            $replacedEmp = Karyawan::where('nik', $payload['replaced_employee_nik'])->first();
            if ($replacedEmp) {
                $payload['replaced_employee_name'] = $replacedEmp->nama_karyawan;
                $payload['replaced_employee_position'] = $replacedEmp->posisi_title ?: $replacedEmp->jabatan;
            }
        }

        if (! empty($payload['direct_report_nik'])) {
            $directEmp = Karyawan::where('nik', $payload['direct_report_nik'])->first();
            if ($directEmp) {
                $payload['direct_report_name'] = $directEmp->nama_karyawan;
            }
        }

        $payload['requester_nik'] = $request->user()->username;
        $payload['request_date'] = $payload['request_date'] ?? now()->toDateString();
        $payload['status'] = 'pending';
        $payload['hiring_status'] = 'pending';
        $payload['requires_gm'] = (bool) ($payload['requires_gm'] ?? false);

        $recruitmentRequest = RecruitmentRequest::query()->create($payload);

        app(RecruitmentRequestApprovalService::class)->initiateApproval($recruitmentRequest);

        return response()->json([
            'message' => 'Pengajuan rekrutmen berhasil diajukan dan notifikasi telah dikirim ke approver.',
            'data' => $recruitmentRequest->fresh(['requester', 'replacedEmployee', 'directReport']),
        ], 201);
    }

    private function userHasSubordinates($user, ?Karyawan $employee): bool
    {
        if (in_array((int) $user->level, [0, 1, 2], true)) {
            return true;
        }

        if (! $employee) {
            return false;
        }

        $hasDirectSubordinates = Karyawan::query()
            ->where('atasan_langsung_nik', $employee->nik)
            ->exists();

        $positionTitle = strtolower(trim((string) ($employee->posisi_title ?: $employee->jabatan)));
        $isLeaderOrAbove = in_array($positionTitle, [
            'leader', 'spv', 'supervisor', 'asst. manager', 'manager', 'gm', 'general manager', 'head', 'coordinator'
        ], true);

        return $hasDirectSubordinates || $isLeaderOrAbove;
    }
}
