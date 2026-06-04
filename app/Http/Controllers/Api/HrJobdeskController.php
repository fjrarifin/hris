<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jobdesk;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class HrJobdeskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Jobdesk::query()
            ->with('jabatan')
            ->when($request->integer('master_jabatan_id'), fn ($query, $id) => $query->where('master_jabatan_id', $id))
            ->latest()
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        $document = $request->file('document')?->store('jobdesk-documents', 'local');

        try {
            $jobdesk = Jobdesk::query()->create($payload + ['document' => $document, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            app(HrdAuditLogService::class)->record(
                $request,
                'Jobdesk',
                'created',
                "Jobdesk #{$jobdesk->id}",
                null,
                $jobdesk,
                Jobdesk::class,
                $jobdesk->id
            );
        } catch (Throwable $exception) {
            if ($document) {
                Storage::disk('local')->delete($document);
            }

            throw $exception;
        }

        return response()->json(['message' => 'Jobdesk berhasil dibuat.', 'data' => $jobdesk->load('jabatan')], 201);
    }

    public function update(Request $request, Jobdesk $jobdesk): JsonResponse
    {
        $payload = $this->payload($request);
        $document = $request->file('document')?->store('jobdesk-documents', 'local');
        $oldDocument = $jobdesk->document;
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($jobdesk);

        try {
            $jobdesk->update($payload + array_filter([
                'document' => $document,
                'updated_by' => $request->user()->id,
            ], fn ($value) => $value !== null));
        } catch (Throwable $exception) {
            if ($document) {
                Storage::disk('local')->delete($document);
            }

            throw $exception;
        }

        if ($document && $oldDocument) {
            Storage::disk('local')->delete($oldDocument);
        }
        app(HrdAuditLogService::class)->record(
            $request,
            'Jobdesk',
            'updated',
            "Jobdesk #{$jobdesk->id}",
            $beforeAudit,
            $jobdesk->fresh(),
            Jobdesk::class,
            $jobdesk->id
        );

        return response()->json(['message' => 'Jobdesk berhasil diperbarui.', 'data' => $jobdesk->load('jabatan')]);
    }

    public function destroy(Request $request, Jobdesk $jobdesk): JsonResponse
    {
        $document = $jobdesk->document;
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($jobdesk);
        $subjectId = $jobdesk->id;
        $jobdesk->delete();

        if ($document) {
            Storage::disk('local')->delete($document);
        }
        app(HrdAuditLogService::class)->record(
            $request,
            'Jobdesk',
            'deleted',
            "Jobdesk #{$subjectId}",
            $beforeAudit,
            null,
            Jobdesk::class,
            $subjectId
        );

        return response()->json(['message' => 'Jobdesk berhasil dihapus.']);
    }

    public function previewPdf(Jobdesk $jobdesk): JsonResponse
    {
        abort_unless($jobdesk->document && Storage::disk('local')->exists($jobdesk->document), 404);

        return response()->json([
            'filename' => 'Jobdesk-'.$jobdesk->jabatan->nama_jabatan.'.pdf',
            'mime_type' => 'application/pdf',
            'content_base64' => base64_encode(Storage::disk('local')->get($jobdesk->document)),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function payload(Request $request): array
    {
        return $request->validate([
            'master_jabatan_id' => ['required', 'exists:master_jabatans,id'],
            'kategori' => ['required', 'string', 'max:100'],
            'deskripsi' => ['required', 'string'],
            'tipe_tugas' => ['required', Rule::in(['harian', 'mingguan', 'bulanan', 'tahunan', 'insidental'])],
            'is_active' => ['required', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
        ]);
    }
}
