<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jobdesk;
use App\Services\PerformanceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffTalentController extends Controller
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->karyawan()->firstOrFail();
        $jabatan = $this->service->findEmployeeJabatan($employee);

        return response()->json([
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'jabatan' => $jabatan->nama_jabatan,
                'departemen' => $jabatan->departemen,
            ],
            'jobdesks' => $jabatan->jobdesks()->where('is_active', true)->orderBy('kategori')->get(),
            'kpis' => $jabatan->kpiTemplates()->where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function previewPdf(Request $request, Jobdesk $jobdesk): JsonResponse
    {
        $employee = $request->user()->karyawan()->firstOrFail();
        $jabatan = $this->service->findEmployeeJabatan($employee);
        abort_unless($jobdesk->master_jabatan_id === $jabatan->id && $jobdesk->is_active, 403);
        abort_unless($jobdesk->document && Storage::disk('local')->exists($jobdesk->document), 404);

        return response()->json([
            'filename' => 'Jobdesk-'.$jabatan->nama_jabatan.'.pdf',
            'mime_type' => 'application/pdf',
            'content_base64' => base64_encode(Storage::disk('local')->get($jobdesk->document)),
        ])->header('Cache-Control', 'private, no-store');
    }
}
