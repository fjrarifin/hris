<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidateReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PublicReferenceEvaluationController extends Controller
{
    public function showShort(string $code): JsonResponse
    {
        $reference = RecruitmentCandidateReference::query()
            ->with('candidate.vacancy')
            ->where('public_code', $code)
            ->firstOrFail();

        return $this->evaluationResponse($reference);
    }

    public function show(string $type, string $token): JsonResponse
    {
        $this->assertType($type);
        $reference = RecruitmentCandidateReference::query()
            ->with('candidate.vacancy')
            ->where('public_token', $token)
            ->where('form_type', $type)
            ->firstOrFail();

        return $this->evaluationResponse($reference);
    }

    private function evaluationResponse(RecruitmentCandidateReference $reference): JsonResponse
    {
        abort_if($reference->submitted_at, 410, 'Reference Check telah selesai atau link sudah tidak berlaku.');

        return response()->json([
            'data' => [
                'type' => $reference->form_type,
                'candidate_name' => $reference->candidate->name,
                'vacancy_title' => $reference->candidate->vacancy?->title ?? 'Umum',
                'reference' => [
                    'name' => $reference->name,
                    'position' => $reference->position,
                    'relationship' => $reference->relationship,
                    'company' => $reference->company,
                ],
            ],
        ]);
    }

    public function submitShort(Request $request, string $code): JsonResponse
    {
        $reference = RecruitmentCandidateReference::query()->where('public_code', $code)->firstOrFail();

        return $this->storeEvaluation($request, $reference->form_type, null, $code);
    }

    public function submit(Request $request, string $type, string $token): JsonResponse
    {
        $this->assertType($type);
        return $this->storeEvaluation($request, $type, $token);
    }

    private function storeEvaluation(Request $request, string $type, ?string $token = null, ?string $code = null): JsonResponse
    {
        $rules = [
            'reference_name' => ['required', 'string', 'max:150'],
            'reference_position' => ['required', 'string', 'max:150'],
            'work_relationship' => ['required', Rule::in(['Peer', 'Direct Report', 'Subordinate'])],
            'worked_together_duration' => ['required', 'string', 'max:150'],
            'company_together' => ['required', 'string', 'max:150'],
            'candidate_last_position' => ['required', 'string', 'max:150'],
            'candidate_exit_reason' => ['required', 'string'],
            'exit_initiator' => ['required', Rule::in(['candidate', 'company'])],
            'achievements' => ['required', 'string'],
            'top_strengths' => ['required', 'string'],
            'teamwork' => ['required', 'string'],
            'learning_adaptability' => ['required', 'string'],
            'conflict_handling' => ['required', 'string'],
            'improvement_areas' => ['required', 'string'],
            'reliability' => ['required', 'string'],
            'pressure_handling' => ['required', 'string'],
            'commitment_attendance' => ['required', 'string'],
            'work_again' => ['required', 'string'],
            'recommendation' => ['required', Rule::in(['yes', 'no'])],
            'additional_notes' => ['required', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ];
        if ($type === 'managerial') {
            $rules += [
                'leadership' => ['required', 'string'],
                'leadership_conflict' => ['required', 'string'],
                'team_relationship' => ['required', 'string'],
            ];
        }
        $payload = $request->validate($rules);

        DB::transaction(function () use ($token, $code, $type, $payload): void {
            $reference = RecruitmentCandidateReference::query()
                ->where('form_type', $type)
                ->when($code, fn ($query) => $query->where('public_code', $code))
                ->when(!$code, fn ($query) => $query->where('public_token', $token))
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($reference->submitted_at, 410, 'Reference Check telah selesai atau link sudah tidak berlaku.');
            $reference->update([
                'name' => $payload['reference_name'],
                'position' => $payload['reference_position'],
                'company' => $payload['company_together'],
                'relationship' => $payload['work_relationship'],
                'answers' => $payload,
                'submitted_at' => now(),
                'public_token' => null,
                'public_code' => null,
            ]);
        });

        return response()->json(['message' => 'Terima kasih. Jawaban Reference Check telah berhasil diterima.']);
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, ['staff', 'managerial'], true), 404);
    }
}
