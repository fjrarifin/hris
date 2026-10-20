<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\KpiTemplate;
use App\Models\KpiTemplateAssignment;
use App\Models\KpiTemplateItem;
use App\Models\MasterJabatan;
use App\Services\HrdAuditLogService;
use App\Services\PerformanceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrKpiTemplateController extends Controller
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $jabatanId = $request->integer('master_jabatan_id');
        $search = trim((string) $request->query('search'));

        $query = KpiTemplate::query()
            ->with(['jabatan', 'items', 'creatorEmployee', 'supervisorEmployee', 'hrdApprover'])
            ->withCount(['assignments' => fn ($q) => $q->where('status', 'active')])
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($jabatanId, fn ($q) => $q->where('master_jabatan_id', $jabatanId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('nama_template', 'like', "%{$search}%")
                        ->orWhere('departemen', 'like', "%{$search}%")
                        ->orWhereHas('creatorEmployee', fn ($ce) => $ce->where('nama_karyawan', 'like', "%{$search}%"));
                });
            })
            ->latest();

        return response()->json($query->get());
    }

    public function show(KpiTemplate $kpiTemplate): JsonResponse
    {
        $kpiTemplate->load([
            'jabatan',
            'items',
            'creatorEmployee',
            'supervisorEmployee',
            'hrdApprover',
            'assignments.employee',
        ]);

        return response()->json($kpiTemplate);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_template' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'master_jabatan_id' => ['nullable', 'exists:master_jabatans,id'],
            'departemen' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_kpi' => ['required', 'string', 'max:150'],
            'items.*.deskripsi' => ['nullable', 'string'],
            'items.*.target' => ['required', 'string', 'max:150'],
            'items.*.satuan' => ['required', Rule::in(['persen', 'jumlah', 'hari', 'rupiah', 'skor', 'dokumen', 'poin'])],
            'items.*.bobot' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'items.*.formula_penilaian' => ['nullable', 'string'],
        ]);

        $jabatan = filled($validated['master_jabatan_id'] ?? null)
            ? MasterJabatan::query()->find($validated['master_jabatan_id'])
            : null;

        $template = DB::transaction(function () use ($validated, $request, $jabatan): KpiTemplate {
            $totalBobot = collect($validated['items'])->sum('bobot');

            $template = KpiTemplate::query()->create([
                'nama_template' => $validated['nama_template'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'master_jabatan_id' => $validated['master_jabatan_id'] ?? null,
                'departemen' => $validated['departemen'] ?? $jabatan?->departemen,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'status' => 'approved',
                'hrd_approved_by' => $request->user()->id,
                'hrd_approved_at' => now(),
                'total_bobot' => $totalBobot,
                'is_active' => true,
            ]);

            foreach ($validated['items'] as $index => $itemData) {
                $template->items()->create([
                    'master_jabatan_id' => $template->master_jabatan_id,
                    'nama_kpi' => $itemData['nama_kpi'],
                    'deskripsi' => $itemData['deskripsi'] ?? null,
                    'target' => $itemData['target'],
                    'satuan' => $itemData['satuan'],
                    'bobot' => $itemData['bobot'],
                    'formula_penilaian' => $itemData['formula_penilaian'] ?? null,
                    'urutan' => $index + 1,
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return $template->load(['jabatan', 'items']);
        });

        app(HrdAuditLogService::class)->record(
            $request,
            'KPI',
            'created',
            $template->nama_template,
            null,
            $template,
            KpiTemplate::class,
            $template->id
        );

        return response()->json(['message' => 'Template KPI berhasil dibuat.', 'data' => $template], 201);
    }

    public function update(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $validated = $request->validate([
            'nama_template' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'master_jabatan_id' => ['nullable', 'exists:master_jabatans,id'],
            'departemen' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_kpi' => ['required', 'string', 'max:150'],
            'items.*.deskripsi' => ['nullable', 'string'],
            'items.*.target' => ['required', 'string', 'max:150'],
            'items.*.satuan' => ['required', Rule::in(['persen', 'jumlah', 'hari', 'rupiah', 'skor', 'dokumen', 'poin'])],
            'items.*.bobot' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'items.*.formula_penilaian' => ['nullable', 'string'],
        ]);

        $jabatan = filled($validated['master_jabatan_id'] ?? null)
            ? MasterJabatan::query()->find($validated['master_jabatan_id'])
            : null;

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($kpiTemplate);

        $template = DB::transaction(function () use ($kpiTemplate, $validated, $request, $jabatan): KpiTemplate {
            $totalBobot = collect($validated['items'])->sum('bobot');

            $kpiTemplate->update([
                'nama_template' => $validated['nama_template'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'master_jabatan_id' => $validated['master_jabatan_id'] ?? null,
                'departemen' => $validated['departemen'] ?? $jabatan?->departemen,
                'updated_by' => $request->user()->id,
                'total_bobot' => $totalBobot,
            ]);

            $kpiTemplate->items()->delete();
            foreach ($validated['items'] as $index => $itemData) {
                $kpiTemplate->items()->create([
                    'master_jabatan_id' => $kpiTemplate->master_jabatan_id,
                    'nama_kpi' => $itemData['nama_kpi'],
                    'deskripsi' => $itemData['deskripsi'] ?? null,
                    'target' => $itemData['target'],
                    'satuan' => $itemData['satuan'],
                    'bobot' => $itemData['bobot'],
                    'formula_penilaian' => $itemData['formula_penilaian'] ?? null,
                    'urutan' => $index + 1,
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return $kpiTemplate->load(['jabatan', 'items']);
        });

        app(HrdAuditLogService::class)->record(
            $request,
            'KPI',
            'updated',
            $kpiTemplate->nama_template,
            $beforeAudit,
            $template->fresh(),
            KpiTemplate::class,
            $kpiTemplate->id
        );

        return response()->json(['message' => 'Template KPI berhasil diperbarui.', 'data' => $template]);
    }

    public function destroy(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($kpiTemplate);
        $subjectLabel = $kpiTemplate->nama_template;
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

    public function decide(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:decision,rejected'],
        ]);

        if ($validated['decision'] === 'approved') {
            $kpiTemplate->update([
                'status' => 'approved',
                'hrd_approved_by' => $request->user()->id,
                'hrd_approved_at' => now(),
                'hrd_notes' => $validated['notes'] ?? null,
                'reject_reason' => null,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Template KPI berhasil disetujui HRD dan siap ditugaskan.',
                'data' => $kpiTemplate->fresh(['jabatan', 'items', 'creatorEmployee', 'supervisorEmployee']),
            ]);
        }

        $kpiTemplate->update([
            'status' => 'rejected',
            'hrd_approved_by' => $request->user()->id,
            'hrd_rejected_at' => now(),
            'hrd_notes' => $validated['notes'] ?? null,
            'reject_reason' => $validated['reason'] ?? $validated['notes'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Template KPI ditolak oleh HRD dan dikembalikan ke pembuat.',
            'data' => $kpiTemplate->fresh(['jabatan', 'items', 'creatorEmployee', 'supervisorEmployee']),
        ]);
    }

    public function syncActive(Request $request, MasterJabatan $jabatan): JsonResponse
    {
        $validated = $request->validate([
            'template_ids' => ['required', 'array', 'min:1'],
            'template_ids.*' => ['integer'],
        ]);

        $this->service->syncActiveKpis($jabatan, $validated['template_ids']);

        return response()->json(['message' => 'KPI aktif berhasil diperbarui.']);
    }

    public function assignments(Request $request): JsonResponse
    {
        $assignments = KpiTemplateAssignment::query()
            ->with(['template.items', 'employee', 'period', 'assignedByUser'])
            ->latest('assigned_at')
            ->get();

        return response()->json($assignments);
    }
}
