<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidate;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HrRecruitmentCandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            RecruitmentCandidate::query()
                ->with('vacancy')
                ->when($request->filled('vacancy_id'), fn ($query) => $query->where('vacancy_id', $request->input('vacancy_id')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->latest()
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'vacancy_id' => ['nullable', 'exists:recruitment_vacancies,id'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:applied,screening,interview,offered,hired,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $candidate = RecruitmentCandidate::query()->create($payload);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'created',
            "Candidate #{$candidate->id}: {$candidate->name}",
            null,
            $candidate,
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json(['message' => 'Kandidat berhasil ditambahkan.', 'data' => $candidate->load('vacancy')], 201);
    }

    public function show(RecruitmentCandidate $candidate): JsonResponse
    {
        return response()->json($candidate->load('vacancy'));
    }

    public function update(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $payload = $request->validate([
            'vacancy_id' => ['nullable', 'exists:recruitment_vacancies,id'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:applied,screening,interview,offered,hired,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $candidate->update($payload);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name}",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json(['message' => 'Kandidat berhasil diperbarui.', 'data' => $candidate->load('vacancy')]);
    }

    public function destroy(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $resume = $candidate->resume_path;
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $subjectId = $candidate->id;
        $subjectLabel = "Candidate #{$candidate->id}: {$candidate->name}";

        $candidate->delete();

        if ($resume) {
            Storage::disk('local')->delete($resume);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'deleted',
            $subjectLabel,
            $beforeAudit,
            null,
            RecruitmentCandidate::class,
            $subjectId
        );

        return response()->json(['message' => 'Kandidat berhasil dihapus.']);
    }

    public function uploadResume(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf', 'max:5120'], // Max 5MB PDF
        ]);

        $resumePath = $request->file('resume')->store('recruitment-resumes', 'local');
        $oldResume = $candidate->resume_path;

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $candidate->update(['resume_path' => $resumePath]);

        if ($oldResume) {
            Storage::disk('local')->delete($oldResume);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Uploaded Resume)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Resume berhasil diunggah.',
            'data' => $candidate->load('vacancy'),
        ]);
    }

    public function previewResume(RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->resume_path && Storage::disk('local')->exists($candidate->resume_path), 404);

        return response()->json([
            'filename' => 'Resume-'.str($candidate->name)->slug().'.pdf',
            'mime_type' => 'application/pdf',
            'content_base64' => base64_encode(Storage::disk('local')->get($candidate->resume_path)),
        ])->header('Cache-Control', 'private, no-store');
    }
}
