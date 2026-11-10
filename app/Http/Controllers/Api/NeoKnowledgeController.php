<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NeoKnowledge;
use App\Models\NeoKnowledgeRevision;
use App\Services\NeoKnowledgeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NeoKnowledgeController extends Controller
{
    public function index(): JsonResponse
    {
        $sections = NeoKnowledge::query()->orderBy('sort_order')->orderBy('id')->get();
        $activeChars = $sections->where('is_active', true)->sum(fn (NeoKnowledge $s) => mb_strlen($s->content));

        return response()->json([
            'data' => $sections->map(fn (NeoKnowledge $s) => $this->serialize($s))->values(),
            'kpi' => [
                'total_count' => $sections->count(),
                'active_count' => $sections->where('is_active', true)->count(),
                'active_chars' => $activeChars,
                'char_limit' => NeoKnowledgeRepository::SOFT_CHAR_LIMIT,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $key = Str::limit(Str::slug($validated['title']) ?: 'seksi', 140, '');
        $base = $key;
        $i = 2;
        while (NeoKnowledge::where('section_key', $key)->exists()) {
            $key = $base . '-' . $i++;
        }

        $section = NeoKnowledge::create([
            'section_key' => $key,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'sort_order' => $validated['sort_order'] ?? ((int) NeoKnowledge::max('sort_order') + 10),
            'is_active' => $validated['is_active'] ?? true,
            'updated_by' => $this->actor($request),
        ]);

        NeoKnowledgeRepository::forget();

        return response()->json([
            'message' => 'Seksi knowledge berhasil ditambahkan.',
            'data' => $this->serialize($section),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $section = NeoKnowledge::findOrFail($id);
        $validated = $this->validated($request);

        DB::transaction(function () use ($section, $validated, $request): void {
            if ($section->title !== $validated['title'] || $section->content !== $validated['content']) {
                $this->snapshot($section, $request);
            }

            $section->update([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'sort_order' => $validated['sort_order'] ?? $section->sort_order,
                'is_active' => $validated['is_active'] ?? $section->is_active,
                'updated_by' => $this->actor($request),
            ]);
        });

        NeoKnowledgeRepository::forget();

        return response()->json([
            'message' => 'Seksi knowledge berhasil diperbarui.',
            'data' => $this->serialize($section->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $section = NeoKnowledge::findOrFail($id);

        DB::transaction(function () use ($section): void {
            NeoKnowledgeRevision::where('neo_knowledge_id', $section->id)->delete();
            $section->delete();
        });

        NeoKnowledgeRepository::forget();

        return response()->json(['message' => 'Seksi knowledge berhasil dihapus.']);
    }

    public function revisions(int $id): JsonResponse
    {
        $section = NeoKnowledge::findOrFail($id);

        $revisions = NeoKnowledgeRevision::where('neo_knowledge_id', $section->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (NeoKnowledgeRevision $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'content' => $r->content,
                'changed_by' => $r->changed_by,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $revisions]);
    }

    public function restore(Request $request, int $id, int $revisionId): JsonResponse
    {
        $section = NeoKnowledge::findOrFail($id);
        $revision = NeoKnowledgeRevision::where('neo_knowledge_id', $section->id)->findOrFail($revisionId);

        DB::transaction(function () use ($section, $revision, $request): void {
            $this->snapshot($section, $request);
            $section->update([
                'title' => $revision->title,
                'content' => $revision->content,
                'updated_by' => $this->actor($request),
            ]);
        });

        NeoKnowledgeRepository::forget();

        return response()->json([
            'message' => 'Revisi berhasil dipulihkan.',
            'data' => $this->serialize($section->fresh()),
        ]);
    }

    private function snapshot(NeoKnowledge $section, Request $request): void
    {
        NeoKnowledgeRevision::create([
            'neo_knowledge_id' => $section->id,
            'title' => $section->title,
            'content' => $section->content,
            'changed_by' => $this->actor($request),
        ]);

        // Simpan maksimal 30 revisi terbaru per seksi.
        $keepIds = NeoKnowledgeRevision::where('neo_knowledge_id', $section->id)
            ->orderByDesc('id')->limit(30)->pluck('id');
        NeoKnowledgeRevision::where('neo_knowledge_id', $section->id)
            ->whereNotIn('id', $keepIds)->delete();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Judul seksi wajib diisi.',
            'content.required' => 'Isi knowledge wajib diisi.',
            'content.max' => 'Isi knowledge maksimal 20.000 karakter per seksi.',
        ]);
    }

    private function actor(Request $request): ?string
    {
        return $request->user()?->username;
    }

    private function serialize(NeoKnowledge $s): array
    {
        return [
            'id' => $s->id,
            'section_key' => $s->section_key,
            'title' => $s->title,
            'content' => $s->content,
            'content_length' => mb_strlen($s->content),
            'sort_order' => $s->sort_order,
            'is_active' => $s->is_active,
            'updated_by' => $s->updated_by,
            'updated_at' => $s->updated_at?->toIso8601String(),
        ];
    }
}

