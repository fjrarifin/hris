<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jobdesk;
use App\Models\KpiTemplate;
use App\Models\KpiTemplateAssignment;
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

        $assignedTemplates = KpiTemplateAssignment::query()
            ->with(['template.items' => fn ($q) => $q->where('is_active', true)->orderBy('urutan')->orderBy('id')])
            ->where('employee_nik', $employee->nik)
            ->where('status', 'active')
            ->whereHas('template', fn ($q) => $q->where('status', 'approved')->where('is_active', true))
            ->get();

        $kpis = collect();
        if ($assignedTemplates->isNotEmpty()) {
            foreach ($assignedTemplates as $assignment) {
                $template = $assignment->template;
                if (! $template) {
                    continue;
                }
                $weightMultiplier = (float) ($assignment->weight_percentage ?: 100) / 100;
                foreach ($template->items as $item) {
                    $kpis->push([
                        'id' => $item->id,
                        'template_id' => $template->id,
                        'template_name' => $template->nama_template,
                        'nama_kpi' => $item->nama_kpi,
                        'deskripsi' => $item->deskripsi,
                        'target' => $item->target,
                        'satuan' => $item->satuan,
                        'bobot' => round((float) $item->bobot * $weightMultiplier, 2),
                        'formula_penilaian' => $item->formula_penilaian,
                    ]);
                }
            }
        } else {
            $fallback = KpiTemplate::query()
                ->where('master_jabatan_id', $jabatan->id)
                ->where('status', 'approved')
                ->where('is_active', true)
                ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('urutan')->orderBy('id')])
                ->first();

            if ($fallback) {
                foreach ($fallback->items as $item) {
                    $kpis->push([
                        'id' => $item->id,
                        'template_id' => $fallback->id,
                        'template_name' => $fallback->nama_template,
                        'nama_kpi' => $item->nama_kpi,
                        'deskripsi' => $item->deskripsi,
                        'target' => $item->target,
                        'satuan' => $item->satuan,
                        'bobot' => (float) $item->bobot,
                        'formula_penilaian' => $item->formula_penilaian,
                    ]);
                }
            }
        }

        return response()->json([
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'jabatan' => $jabatan->nama_jabatan,
                'departemen' => $jabatan->departemen,
            ],
            'jobdesks' => $jabatan->jobdesks()->where('is_active', true)->orderBy('kategori')->get(),
            'assigned_templates' => $assignedTemplates,
            'kpis' => $kpis,
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
