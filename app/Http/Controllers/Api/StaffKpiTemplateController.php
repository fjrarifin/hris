<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\KpiTemplate;
use App\Models\KpiTemplateAssignment;
use App\Models\KpiTemplateItem;
use App\Models\MasterJabatan;
use App\Models\PerformancePeriod;
use App\Models\User;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffKpiTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;
        $this->ensureIsSupervisorOrAbove($user, $employee);

        $myTemplates = KpiTemplate::query()
            ->with(['jabatan', 'items'])
            ->withCount(['assignments' => fn ($q) => $q->where('status', 'active')])
            ->where(function ($query) use ($user, $employee) {
                $query->where('created_by', $user->id);
                if ($employee) {
                    $query->orWhere('creator_nik', $employee->nik);
                }
            })
            ->latest()
            ->get();

        $pendingApprovals = collect();
        if ($employee) {
            $pendingApprovals = KpiTemplate::query()
                ->with(['jabatan', 'items', 'creatorEmployee', 'creator'])
                ->where('supervisor_nik', $employee->nik)
                ->where('status', 'waiting_supervisor')
                ->latest()
                ->get();
        }

        $approvedTeamTemplates = KpiTemplate::query()
            ->with(['jabatan', 'items'])
            ->withCount(['assignments' => fn ($q) => $q->where('status', 'active')])
            ->where('status', 'approved')
            ->where(function ($query) use ($user, $employee) {
                $query->where('created_by', $user->id);
                if ($employee) {
                    $query->orWhere('creator_nik', $employee->nik)
                        ->orWhere('supervisor_nik', $employee->nik);
                }
            })
            ->latest()
            ->get();

        return response()->json([
            'my_templates' => $myTemplates,
            'pending_approvals' => $pendingApprovals,
            'approved_team_templates' => $approvedTeamTemplates,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'jabatans' => MasterJabatan::query()->where('is_active', true)->orderBy('nama_jabatan')->get(['id', 'nama_jabatan', 'departemen']),
            'periods' => PerformancePeriod::query()->whereIn('status', ['draft', 'active'])->latest()->get(['id', 'nama_periode', 'status']),
            'satuan_options' => [
                ['value' => 'persen', 'label' => 'Persen (%)'],
                ['value' => 'jumlah', 'label' => 'Jumlah (Qty)'],
                ['value' => 'hari', 'label' => 'Hari'],
                ['value' => 'rupiah', 'label' => 'Rupiah (Rp)'],
                ['value' => 'skor', 'label' => 'Skor / Nilai'],
                ['value' => 'dokumen', 'label' => 'Dokumen / Laporan'],
                ['value' => 'poin', 'label' => 'Poin'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;
        $this->ensureIsSupervisorOrAbove($user, $employee);

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

        $template = DB::transaction(function () use ($validated, $user, $employee, $jabatan): KpiTemplate {
            $totalBobot = collect($validated['items'])->sum('bobot');

            $template = KpiTemplate::query()->create([
                'nama_template' => $validated['nama_template'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'master_jabatan_id' => $validated['master_jabatan_id'] ?? null,
                'departemen' => $validated['departemen'] ?? $jabatan?->departemen ?? $employee?->departement,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'creator_nik' => $employee?->nik,
                'status' => 'draft',
                'supervisor_nik' => $employee?->atasan_langsung_nik,
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
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            return $template->load(['jabatan', 'items']);
        });

        app(HrdAuditLogService::class)->record(
            $request,
            'KPI Template',
            'created',
            "Template {$template->nama_template} (Draft)",
            null,
            $template,
            KpiTemplate::class,
            $template->id
        );

        return response()->json([
            'message' => 'Template KPI berhasil disimpan sebagai draft.',
            'data' => $template,
        ], 201);
    }

    public function show(Request $request, KpiTemplate $kpiTemplate): JsonResponse
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

    public function update(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;
        $this->ensureCanEditTemplate($kpiTemplate, $user, $employee);

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

        $template = DB::transaction(function () use ($kpiTemplate, $validated, $user, $employee, $jabatan): KpiTemplate {
            $totalBobot = collect($validated['items'])->sum('bobot');

            $kpiTemplate->update([
                'nama_template' => $validated['nama_template'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'master_jabatan_id' => $validated['master_jabatan_id'] ?? null,
                'departemen' => $validated['departemen'] ?? $jabatan?->departemen ?? $employee?->departement,
                'updated_by' => $user->id,
                'total_bobot' => $totalBobot,
                'supervisor_nik' => $employee?->atasan_langsung_nik ?? $kpiTemplate->supervisor_nik,
            ]);

            // Recreate items
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
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            return $kpiTemplate->load(['jabatan', 'items']);
        });

        app(HrdAuditLogService::class)->record(
            $request,
            'KPI Template',
            'updated',
            "Template {$template->nama_template}",
            $beforeAudit,
            $template->fresh(),
            KpiTemplate::class,
            $template->id
        );

        return response()->json([
            'message' => 'Template KPI berhasil diperbarui.',
            'data' => $template,
        ]);
    }

    public function destroy(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;
        $this->ensureCanEditTemplate($kpiTemplate, $user, $employee);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($kpiTemplate);
        $subject = $kpiTemplate->nama_template;
        $subjectId = $kpiTemplate->id;

        $kpiTemplate->delete();

        app(HrdAuditLogService::class)->record(
            $request,
            'KPI Template',
            'deleted',
            "Template {$subject}",
            $beforeAudit,
            null,
            KpiTemplate::class,
            $subjectId
        );

        return response()->json(['message' => 'Template KPI berhasil dihapus.']);
    }

    public function submit(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;
        $this->ensureCanEditTemplate($kpiTemplate, $user, $employee);

        $items = $kpiTemplate->items;
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Template KPI harus memiliki minimal 1 poin KPI.'],
            ]);
        }

        $totalBobot = (float) $items->sum('bobot');
        if (abs($totalBobot - 100) > 0.001) {
            throw ValidationException::withMessages([
                'bobot' => ["Total bobot seluruh poin KPI harus tepat 100%. Total saat ini: {$totalBobot}%."],
            ]);
        }

        $supervisorNik = $employee?->atasan_langsung_nik;
        $nextStatus = filled($supervisorNik) ? 'waiting_supervisor' : 'waiting_hrd';

        $kpiTemplate->update([
            'status' => $nextStatus,
            'supervisor_nik' => $supervisorNik,
            'total_bobot' => $totalBobot,
            'reject_reason' => null,
            'updated_by' => $user->id,
        ]);

        $msg = $nextStatus === 'waiting_supervisor'
            ? 'Template KPI berhasil dikirim ke Atasan Langsung untuk persetujuan.'
            : 'Template KPI berhasil dikirim langsung ke HRD untuk persetujuan.';

        return response()->json([
            'message' => $msg,
            'data' => $kpiTemplate->fresh(['jabatan', 'items', 'supervisorEmployee']),
        ]);
    }

    public function decide(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;

        if ($kpiTemplate->status !== 'waiting_supervisor') {
            throw ValidationException::withMessages([
                'status' => ['Template ini tidak sedang menunggu persetujuan atasan langsung.'],
            ]);
        }

        if (! $employee || $kpiTemplate->supervisor_nik !== $employee->nik) {
            abort(403, 'Hanya atasan langsung yang berhak menyetujui template ini.');
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:decision,rejected'],
        ]);

        if ($validated['decision'] === 'approved') {
            $kpiTemplate->update([
                'status' => 'waiting_hrd',
                'supervisor_approved_at' => now(),
                'supervisor_notes' => $validated['notes'] ?? null,
                'reject_reason' => null,
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Template KPI disetujui dan diteruskan ke HRD untuk persetujuan final.',
                'data' => $kpiTemplate->fresh(['jabatan', 'items', 'creatorEmployee']),
            ]);
        }

        $kpiTemplate->update([
            'status' => 'rejected',
            'supervisor_rejected_at' => now(),
            'supervisor_notes' => $validated['notes'] ?? null,
            'reject_reason' => $validated['reason'] ?? $validated['notes'],
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Template KPI ditolak dan dikembalikan ke pembuat untuk revisi.',
            'data' => $kpiTemplate->fresh(['jabatan', 'items', 'creatorEmployee']),
        ]);
    }

    public function subordinates(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;
        $this->ensureIsSupervisorOrAbove($user, $employee);

        $query = Karyawan::query()
            ->where(function ($q) use ($employee) {
                if ($employee) {
                    $q->where('atasan_langsung_nik', $employee->nik)
                        ->orWhere('atasan_tidak_langsung_nik', $employee->nik)
                        ->orWhere('departement', $employee->departement);
                }
            })
            ->where('status_karyawan', 'aktif')
            ->orderBy('nama_karyawan');

        $employees = $query->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'unit']);

        return response()->json($employees);
    }

    public function assign(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $user = $request->user();
        $employee = $user->karyawan;
        $this->ensureIsSupervisorOrAbove($user, $employee);

        if ($kpiTemplate->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Hanya template KPI yang telah disetujui (Approved) yang dapat ditugaskan ke tim.'],
            ]);
        }

        $validated = $request->validate([
            'employee_niks' => ['required', 'array', 'min:1'],
            'employee_niks.*' => ['required', 'exists:m_karyawan,nik'],
            'performance_period_id' => ['nullable', 'exists:performance_periods,id'],
            'weight_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $periodId = $validated['performance_period_id'] ?? null;
        $weight = $validated['weight_percentage'] ?? 100.00;
        $notes = $validated['notes'] ?? null;
        $assignedCount = 0;

        DB::transaction(function () use ($kpiTemplate, $validated, $user, $periodId, $weight, $notes, &$assignedCount): void {
            foreach ($validated['employee_niks'] as $nik) {
                KpiTemplateAssignment::query()->updateOrCreate(
                    [
                        'kpi_template_id' => $kpiTemplate->id,
                        'employee_nik' => $nik,
                        'performance_period_id' => $periodId,
                    ],
                    [
                        'assigned_by' => $user->id,
                        'assigned_at' => now(),
                        'status' => 'active',
                        'weight_percentage' => $weight,
                        'notes' => $notes,
                    ]
                );
                $assignedCount++;
            }
        });

        return response()->json([
            'message' => "Template KPI berhasil ditugaskan ke {$assignedCount} karyawan.",
            'data' => $kpiTemplate->load('assignments.employee'),
        ]);
    }

    public function unassign(Request $request, KpiTemplateAssignment $assignment): JsonResponse
    {
        $assignment->delete();

        return response()->json(['message' => 'Penugasan template KPI berhasil dihapus.']);
    }

    private function ensureIsSupervisorOrAbove(User $user, ?Karyawan $employee): void
    {
        if ((int) $user->level === 0 || (int) $user->level === 2) {
            return;
        }

        if (! $employee) {
            abort(403, 'Akses ditolak: Data karyawan tidak ditemukan.');
        }

        $hasSubordinates = Karyawan::query()
            ->where('atasan_langsung_nik', $employee->nik)
            ->orWhere('atasan_tidak_langsung_nik', $employee->nik)
            ->exists();

        $positionTitle = strtolower(trim((string) ($employee->posisi_title ?: $employee->jabatan)));
        $isLeaderOrAbove = in_array($positionTitle, [
            'leader', 'spv', 'supervisor', 'asst. manager', 'manager', 'gm', 'general manager', 'head', 'coordinator',
        ], true);

        if (! $hasSubordinates && ! $isLeaderOrAbove) {
            abort(403, 'Akses khusus level Supervisor ke atas.');
        }
    }

    private function ensureCanEditTemplate(KpiTemplate $template, User $user, ?Karyawan $employee): void
    {
        if ((int) $user->level === 0 || (int) $user->level === 2) {
            return;
        }

        $isOwner = $template->created_by === $user->id || ($employee && $template->creator_nik === $employee->nik);
        if (! $isOwner) {
            abort(403, 'Anda hanya dapat mengelola template yang Anda buat.');
        }

        if (! in_array($template->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Template yang sedang dalam proses persetujuan atau sudah disetujui tidak dapat diubah langsung.'],
            ]);
        }
    }
}
