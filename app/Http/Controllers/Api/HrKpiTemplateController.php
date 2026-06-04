<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jobdesk;
use App\Models\KpiTemplate;
use App\Models\MasterJabatan;
use App\Services\HrdAuditLogService;
use App\Services\PerformanceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrKpiTemplateController extends Controller
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(KpiTemplate::query()
            ->with(['jabatan', 'jobdesk'])
            ->when($request->integer('master_jabatan_id'), fn ($query, $id) => $query->where('master_jabatan_id', $id))
            ->latest()
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $template = KpiTemplate::query()->create($this->payload($request) + ['is_active' => false, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        app(HrdAuditLogService::class)->record(
            $request,
            'KPI',
            'created',
            $template->nama_kpi,
            null,
            $template,
            KpiTemplate::class,
            $template->id
        );

        return response()->json(['message' => 'Template KPI disimpan sebagai draft.', 'data' => $template->load(['jabatan', 'jobdesk'])], 201);
    }

    public function update(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $this->ensureDraft($kpiTemplate);
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($kpiTemplate);
        $kpiTemplate->update($this->payload($request) + ['updated_by' => $request->user()->id]);
        app(HrdAuditLogService::class)->record(
            $request,
            'KPI',
            'updated',
            $kpiTemplate->nama_kpi,
            $beforeAudit,
            $kpiTemplate->fresh(),
            KpiTemplate::class,
            $kpiTemplate->id
        );

        return response()->json(['message' => 'Template KPI berhasil diperbarui.', 'data' => $kpiTemplate->load(['jabatan', 'jobdesk'])]);
    }

    public function destroy(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $this->ensureDraft($kpiTemplate);
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($kpiTemplate);
        $subjectLabel = $kpiTemplate->nama_kpi;
        $subjectId = $kpiTemplate->id;
        $kpiTemplate->delete();
        app(HrdAuditLogService::class)->record(
            $request,
            'KPI',
            'deleted',
            $subjectLabel,
            $beforeAudit,
            null,
            KpiTemplate::class,
            $subjectId
        );

        return response()->json(['message' => 'Template KPI berhasil dihapus.']);
    }

    public function syncActive(Request $request, MasterJabatan $jabatan): JsonResponse
    {
        $validated = $request->validate(['template_ids' => ['required', 'array', 'min:1'], 'template_ids.*' => ['integer']]);
        $beforeAudit = KpiTemplate::query()->where('master_jabatan_id', $jabatan->id)->pluck('is_active', 'id')->all();
        $this->service->syncActiveKpis($jabatan, $validated['template_ids']);
        $afterAudit = KpiTemplate::query()->where('master_jabatan_id', $jabatan->id)->pluck('is_active', 'id')->all();
        app(HrdAuditLogService::class)->record(
            $request,
            'KPI',
            'updated',
            "KPI aktif {$jabatan->nama_jabatan}",
            ['active_map' => $beforeAudit],
            ['active_map' => $afterAudit],
            MasterJabatan::class,
            $jabatan->id
        );

        return response()->json(['message' => 'KPI aktif berhasil diperbarui dengan total bobot 100%.']);
    }

    private function payload(Request $request): array
    {
        $validated = $request->validate([
            'master_jabatan_id' => ['required', 'exists:master_jabatans,id'],
            'jobdesk_id' => ['nullable', 'exists:jobdesks,id'],
            'nama_kpi' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'target' => ['required', 'string', 'max:150'],
            'satuan' => ['required', Rule::in(['persen', 'jumlah', 'hari', 'rupiah', 'skor', 'dokumen'])],
            'bobot' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'formula_penilaian' => ['nullable', 'string'],
        ]);

        if (filled($validated['jobdesk_id'] ?? null) && ! Jobdesk::query()
            ->whereKey($validated['jobdesk_id'])
            ->where('master_jabatan_id', $validated['master_jabatan_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'jobdesk_id' => ['Jobdesk harus berasal dari jabatan yang sama dengan KPI.'],
            ]);
        }

        return $validated;
    }

    private function ensureDraft(KpiTemplate $template): void
    {
        if ($template->is_active) {
            throw ValidationException::withMessages([
                'kpi_template' => ['KPI aktif tidak dapat diubah langsung. Perbarui pilihan KPI aktif terlebih dahulu.'],
            ]);
        }
    }
}
