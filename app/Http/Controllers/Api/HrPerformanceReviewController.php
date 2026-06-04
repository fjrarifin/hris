<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Services\HrdAuditLogService;
use App\Services\PerformanceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrPerformanceReviewController extends Controller
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(PerformanceReview::query()
            ->with(['employee', 'period', 'reviewer'])
            ->when($request->integer('performance_period_id'), fn ($query, $id) => $query->where('performance_period_id', $id))
            ->latest()
            ->get());
    }

    public function show(PerformanceReview $performanceReview): JsonResponse
    {
        return response()->json($performanceReview->load(['employee', 'period', 'reviewer', 'items']));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'performance_period_id' => ['required', 'exists:performance_periods,id'],
            'employee_nik' => ['required', 'exists:m_karyawan,nik'],
            'reviewer_id' => ['nullable', 'exists:users,id'],
        ]);
        $review = $this->service->generateReview(
            PerformancePeriod::query()->findOrFail($validated['performance_period_id']),
            Karyawan::query()->where('nik', $validated['employee_nik'])->firstOrFail(),
            $validated['reviewer_id'] ?? null
        );
        app(HrdAuditLogService::class)->record(
            $request,
            'Performance Review',
            'created',
            "Review {$review->employee_nik}",
            null,
            $review,
            PerformanceReview::class,
            $review->id
        );

        return response()->json(['message' => 'Review berhasil dibuat dari snapshot KPI aktif.', 'data' => $review], 201);
    }

    public function updateStatus(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'notes' => ['nullable', 'string']]);

        if ($performanceReview->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => ['Hanya review submitted yang dapat disetujui atau ditolak.'],
            ]);
        }

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($performanceReview);
        $performanceReview->update($validated);
        app(HrdAuditLogService::class)->record(
            $request,
            'Performance Review',
            'updated',
            "Review {$performanceReview->employee_nik}",
            $beforeAudit,
            $performanceReview->fresh(),
            PerformanceReview::class,
            $performanceReview->id
        );

        return response()->json(['message' => 'Status review berhasil diperbarui.', 'data' => $performanceReview]);
    }
}
