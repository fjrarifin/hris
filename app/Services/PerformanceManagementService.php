<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\KpiTemplate;
use App\Models\MasterJabatan;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerformanceManagementService
{
    public function syncActiveKpis(MasterJabatan $jabatan, array $templateIds): void
    {
        $templates = KpiTemplate::query()
            ->where('master_jabatan_id', $jabatan->id)
            ->whereIn('id', $templateIds)
            ->get();

        if ($templates->count() !== count(array_unique($templateIds))) {
            throw ValidationException::withMessages([
                'template_ids' => ['Terdapat KPI yang bukan milik jabatan terpilih.'],
            ]);
        }

        $total = (float) $templates->sum('bobot');

        if (abs($total - 100) > 0.001) {
            throw ValidationException::withMessages([
                'template_ids' => ["Total bobot KPI aktif harus tepat 100%. Total pilihan saat ini: {$total}%."],
            ]);
        }

        DB::transaction(function () use ($jabatan, $templateIds): void {
            KpiTemplate::query()->where('master_jabatan_id', $jabatan->id)->update(['is_active' => false]);
            KpiTemplate::query()->whereIn('id', $templateIds)->update(['is_active' => true]);
        });
    }

    public function generateReview(PerformancePeriod $period, Karyawan $employee, ?int $reviewerId = null): PerformanceReview
    {
        if ($period->status !== 'active') {
            throw ValidationException::withMessages([
                'performance_period_id' => ['Review hanya dapat dibuat untuk periode aktif.'],
            ]);
        }

        if (PerformanceReview::query()->where('employee_nik', $employee->nik)->where('performance_period_id', $period->id)->exists()) {
            throw ValidationException::withMessages([
                'employee_nik' => ['Karyawan sudah memiliki review pada periode ini.'],
            ]);
        }

        $jabatan = $this->findEmployeeJabatan($employee);
        $reviewItems = collect();

        // 1. Check active template assignments for this employee
        $assignments = \App\Models\KpiTemplateAssignment::query()
            ->with(['template.items' => fn ($q) => $q->where('is_active', true)->orderBy('urutan')->orderBy('id')])
            ->where('employee_nik', $employee->nik)
            ->where('status', 'active')
            ->whereHas('template', fn ($q) => $q->where('status', 'approved')->where('is_active', true))
            ->get();

        if ($assignments->isNotEmpty()) {
            foreach ($assignments as $assignment) {
                $template = $assignment->template;
                if (! $template || $template->items->isEmpty()) {
                    continue;
                }
                $weightMultiplier = (float) ($assignment->weight_percentage ?: 100) / 100;
                foreach ($template->items as $item) {
                    $reviewItems->push([
                        'kpi_template_id' => $template->id,
                        'kpi_template_item_id' => $item->id,
                        'nama_kpi_snapshot' => $item->nama_kpi,
                        'target_snapshot' => $item->target,
                        'satuan_snapshot' => $item->satuan,
                        'bobot_snapshot' => round((float) $item->bobot * $weightMultiplier, 2),
                    ]);
                }
            }
        }

        // 2. Fallback to default approved template for employee's jabatan
        if ($reviewItems->isEmpty()) {
            $template = KpiTemplate::query()
                ->where('master_jabatan_id', $jabatan->id)
                ->where('status', 'approved')
                ->where('is_active', true)
                ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('urutan')->orderBy('id')])
                ->first();

            if (! $template || $template->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'employee_nik' => ['Karyawan belum memiliki template KPI aktif yang disetujui.'],
                ]);
            }

            foreach ($template->items as $item) {
                $reviewItems->push([
                    'kpi_template_id' => $template->id,
                    'kpi_template_item_id' => $item->id,
                    'nama_kpi_snapshot' => $item->nama_kpi,
                    'target_snapshot' => $item->target,
                    'satuan_snapshot' => $item->satuan,
                    'bobot_snapshot' => (float) $item->bobot,
                ]);
            }
        }

        return DB::transaction(function () use ($period, $employee, $jabatan, $reviewItems, $reviewerId): PerformanceReview {
            $review = PerformanceReview::query()->create([
                'employee_nik' => $employee->nik,
                'performance_period_id' => $period->id,
                'jabatan_snapshot' => $jabatan->nama_jabatan,
                'departemen_snapshot' => $jabatan->departemen,
                'reviewer_id' => $reviewerId ?: $this->resolveReviewerId($employee),
                'total_score' => 0,
                'status' => 'draft',
            ]);

            $review->items()->createMany($reviewItems->all());

            return $review->load(['employee', 'period', 'reviewer', 'items']);
        });
    }

    public function saveScores(PerformanceReview $review, array $items, ?string $notes = null): PerformanceReview
    {
        if (! in_array($review->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Nilai hanya dapat diubah ketika review berstatus draft atau rejected.'],
            ]);
        }

        DB::transaction(function () use ($review, $items, $notes): void {
            foreach ($items as $payload) {
                $item = $review->items()->findOrFail($payload['id']);
                $score = (float) $payload['score'];
                $item->update([
                    'realisasi' => $payload['realisasi'] ?? null,
                    'score' => $score,
                    'weighted_score' => round($score * (float) $item->bobot_snapshot / 100, 2),
                    'notes' => $payload['notes'] ?? null,
                ]);
            }

            $review->update([
                'notes' => $notes,
                'total_score' => round((float) $review->items()->sum('weighted_score'), 2),
            ]);
        });

        return $review->fresh(['employee', 'period', 'reviewer', 'items']);
    }

    public function submit(PerformanceReview $review): PerformanceReview
    {
        if (! in_array($review->status, ['draft', 'rejected'], true) || $review->items()->whereNull('score')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Lengkapi seluruh nilai KPI sebelum mengirim review.'],
            ]);
        }

        $review->update(['status' => 'submitted']);

        return $review->fresh(['employee', 'period', 'reviewer', 'items']);
    }

    public function findEmployeeJabatan(Karyawan $employee): MasterJabatan
    {
        $query = MasterJabatan::query()->where('nama_jabatan', $employee->jabatan)->where('is_active', true);
        $jabatan = (clone $query)->where('departemen', $employee->departement)->first();

        if (! $jabatan && (clone $query)->count() === 1) {
            $jabatan = $query->first();
        }

        if (! $jabatan) {
            throw ValidationException::withMessages([
                'employee_nik' => ['Jabatan karyawan belum terpetakan ke master jabatan aktif.'],
            ]);
        }

        return $jabatan;
    }

    private function resolveReviewerId(Karyawan $employee): ?int
    {
        if (! filled($employee->atasan_langsung_nik)) {
            return null;
        }

        return User::query()->where('username', $employee->atasan_langsung_nik)->value('id');
    }
}
