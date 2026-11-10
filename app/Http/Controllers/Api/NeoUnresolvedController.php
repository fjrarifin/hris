<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaAgentUnresolvedQuery;
use App\Models\WhatsappBotFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NeoUnresolvedController extends Controller
{
    private const STATUSES = ['pending', 'resolved', 'ignored'];

    public function index(Request $request): JsonResponse
    {
        $query = WaAgentUnresolvedQuery::query();

        if (in_array($request->input('status'), self::STATUSES, true)) {
            $query->where('status', $request->input('status'));
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%")
                    ->orWhere('karyawan_nik', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $items = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('ask_count')
            ->orderByDesc('last_asked_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'kpi' => [
                'pending_count' => WaAgentUnresolvedQuery::where('status', 'pending')->count(),
                'resolved_count' => WaAgentUnresolvedQuery::where('status', 'resolved')->count(),
                'ignored_count' => WaAgentUnresolvedQuery::where('status', 'ignored')->count(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = WaAgentUnresolvedQuery::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update($validated);

        return response()->json(['message' => 'Pertanyaan berhasil diperbarui.', 'data' => $item->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        WaAgentUnresolvedQuery::findOrFail($id)->delete();

        return response()->json(['message' => 'Pertanyaan berhasil dihapus.']);
    }

    /**
     * Jadikan pertanyaan yang belum terjawab sebagai FAQ, lalu tandai selesai.
     */
    public function toFaq(Request $request, int $id): JsonResponse
    {
        $item = WaAgentUnresolvedQuery::findOrFail($id);

        $validated = $request->validate([
            'topic' => ['nullable', 'string', 'max:255'],
            'keywords' => ['required', 'string', 'max:2000'],
            'answer' => ['required', 'string', 'max:4000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_user_level' => ['nullable', 'integer', Rule::in([0, 1, 2, 3])],
            'match_type' => ['nullable', Rule::in(['contains', 'exact'])],
        ], [
            'keywords.required' => 'Kata kunci wajib diisi (pisahkan dengan koma).',
            'answer.required' => 'Jawaban wajib diisi.',
        ]);

        $faq = DB::transaction(function () use ($validated, $item, $request): WhatsappBotFaq {
            $faq = WhatsappBotFaq::create([
                'topic' => $validated['topic'] ?? null,
                'keywords' => $validated['keywords'],
                'answer' => $validated['answer'],
                'priority' => $validated['priority'] ?? 0,
                'max_user_level' => $validated['max_user_level'] ?? 3,
                'match_type' => $validated['match_type'] ?? 'contains',
                'is_active' => true,
                'updated_by' => $request->user()?->username,
            ]);

            $item->update([
                'status' => 'resolved',
                'admin_notes' => trim(($item->admin_notes ? $item->admin_notes . "\n" : '') . "Dijadikan FAQ #{$faq->id}"),
            ]);

            return $faq;
        });

        return response()->json(['message' => 'FAQ berhasil dibuat dari pertanyaan ini.', 'data' => $faq], 201);
    }
}

