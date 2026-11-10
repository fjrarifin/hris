<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappBotFaq;
use App\Services\HrisWhatsAppAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NeoFaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WhatsappBotFaq::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('topic', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $faqs = $query->orderByDesc('priority')->orderBy('id')->paginate($perPage);

        return response()->json([
            'data' => $faqs->items(),
            'meta' => [
                'current_page' => $faqs->currentPage(),
                'last_page' => $faqs->lastPage(),
                'per_page' => $faqs->perPage(),
                'total' => $faqs->total(),
            ],
            'kpi' => [
                'total_count' => WhatsappBotFaq::count(),
                'active_count' => WhatsappBotFaq::where('is_active', true)->count(),
                'inactive_count' => WhatsappBotFaq::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $validated['updated_by'] = $request->user()?->username;

        $faq = WhatsappBotFaq::create($validated);

        return response()->json(['message' => 'FAQ berhasil ditambahkan.', 'data' => $faq], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $faq = WhatsappBotFaq::findOrFail($id);
        $validated = $this->validated($request);
        $validated['updated_by'] = $request->user()?->username;

        $faq->update($validated);

        return response()->json(['message' => 'FAQ berhasil diperbarui.', 'data' => $faq->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        WhatsappBotFaq::findOrFail($id)->delete();

        return response()->json(['message' => 'FAQ berhasil dihapus.']);
    }

    /**
     * Simulasi: FAQ mana yang akan menjawab pertanyaan tertentu untuk level akses tertentu.
     */
    public function test(Request $request, HrisWhatsAppAgent $agent): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'level' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
        ]);

        $faq = $agent->testFaqMatch($validated['question'], (int) $validated['level']);

        return response()->json([
            'matched' => $faq !== null,
            'data' => $faq,
        ]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'topic' => ['nullable', 'string', 'max:255'],
            'keywords' => ['required', 'string', 'max:2000'],
            'answer' => ['required', 'string', 'max:4000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_user_level' => ['nullable', 'integer', Rule::in([0, 1, 2, 3])],
            'match_type' => ['nullable', Rule::in(['contains', 'exact'])],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'keywords.required' => 'Kata kunci wajib diisi (pisahkan dengan koma).',
            'answer.required' => 'Jawaban wajib diisi.',
        ]);

        $validated['priority'] = $validated['priority'] ?? 0;
        $validated['max_user_level'] = $validated['max_user_level'] ?? 3;
        $validated['match_type'] = $validated['match_type'] ?? 'contains';
        $validated['is_active'] = $validated['is_active'] ?? true;

        return $validated;
    }
}

