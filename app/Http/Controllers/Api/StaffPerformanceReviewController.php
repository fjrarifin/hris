<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerformanceReview;
use App\Services\PerformanceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPerformanceReviewController extends Controller
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(PerformanceReview::query()
            ->where('reviewer_id', $request->user()->id)
            ->with(['employee', 'period'])
            ->latest()
            ->get());
    }

    public function show(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $this->ensureReviewer($request, $performanceReview);

        return response()->json($performanceReview->load(['employee', 'period', 'items']));
    }

    public function update(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $this->ensureReviewer($request, $performanceReview);
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.realisasi' => ['nullable', 'numeric'],
            'items.*.score' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        return response()->json(['message' => 'Nilai review berhasil disimpan.', 'data' => $this->service->saveScores($performanceReview, $validated['items'], $validated['notes'] ?? null)]);
    }

    public function submit(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $this->ensureReviewer($request, $performanceReview);

        return response()->json(['message' => 'Review berhasil dikirim ke HRD.', 'data' => $this->service->submit($performanceReview)]);
    }

    private function ensureReviewer(Request $request, PerformanceReview $review): void
    {
        abort_unless($review->reviewer_id === $request->user()->id, 403);
    }
}
