<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerformancePeriod;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrPerformancePeriodController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PerformancePeriod::query()->latest('start_date')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $period = PerformancePeriod::query()->create($this->payload($request));
        app(HrdAuditLogService::class)->record(
            $request,
            'Periode Review',
            'created',
            $period->nama_periode,
            null,
            $period,
            PerformancePeriod::class,
            $period->id
        );

        return response()->json(['message' => 'Periode review berhasil dibuat.', 'data' => $period], 201);
    }

    public function update(Request $request, PerformancePeriod $performancePeriod): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($performancePeriod);
        $performancePeriod->update($this->payload($request));
        app(HrdAuditLogService::class)->record(
            $request,
            'Periode Review',
            'updated',
            $performancePeriod->nama_periode,
            $beforeAudit,
            $performancePeriod->fresh(),
            PerformancePeriod::class,
            $performancePeriod->id
        );

        return response()->json(['message' => 'Periode review berhasil diperbarui.', 'data' => $performancePeriod]);
    }

    private function payload(Request $request): array
    {
        return $request->validate([
            'nama_periode' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['draft', 'active', 'closed'])],
        ]);
    }
}
