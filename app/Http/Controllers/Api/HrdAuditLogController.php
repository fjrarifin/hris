<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrdAuditLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrdAuditLogController extends Controller
{
    private const HIDDEN_CHANGE_FIELDS = [
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $start = Carbon::parse($validated['start_date'] ?? now()->subDays(30)->toDateString())->startOfDay();
        $end = Carbon::parse($validated['end_date'] ?? now()->toDateString())->endOfDay();
        $keyword = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = HrdAuditLog::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->when(filled($validated['module'] ?? null), fn ($logQuery) => $logQuery->where('module', $validated['module']))
            ->when(filled($validated['action'] ?? null), fn ($logQuery) => $logQuery->where('action', $validated['action']))
            ->when($keyword !== '', function ($logQuery) use ($keyword): void {
                $logQuery->where(function ($search) use ($keyword): void {
                    $search->where('module', 'like', "%{$keyword}%")
                        ->orWhere('subject_label', 'like', "%{$keyword}%")
                        ->orWhere('actor_name', 'like', "%{$keyword}%")
                        ->orWhere('actor_username', 'like', "%{$keyword}%");
                });
            })
            ->latest('occurred_at');

        $logs = $query->paginate($perPage);

        return response()->json([
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'module' => $validated['module'] ?? '',
                'action' => $validated['action'] ?? '',
                'q' => $keyword,
            ],
            'modules' => HrdAuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'records' => collect($logs->items())->map(fn (HrdAuditLog $log): array => [
                'id' => $log->id,
                'module' => $log->module,
                'action' => $log->action,
                'action_label' => $this->actionLabel($log->action),
                'subject_label' => $log->subject_label,
                'actor_name' => $log->actor_name ?: 'User tidak diketahui',
                'actor_username' => $log->actor_username,
                'changes' => $this->visibleChanges($log->changes ?? []),
                'before_snapshot' => $log->before_snapshot ?? [],
                'after_snapshot' => $log->after_snapshot ?? [],
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])->values(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem() ?? 0,
                'to' => $logs->lastItem() ?? 0,
            ],
        ]);
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'Dibuat',
            'deleted' => 'Dihapus',
            default => 'Diubah',
        };
    }

    private function visibleChanges(array $changes): array
    {
        return collect($changes)
            ->reject(fn (array $change): bool => in_array($change['field'] ?? '', self::HIDDEN_CHANGE_FIELDS, true))
            ->values()
            ->all();
    }
}
