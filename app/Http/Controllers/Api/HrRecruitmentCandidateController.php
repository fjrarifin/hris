<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\WhatsAppService;
use App\Mail\CandidateCaseStudyMail;
use App\Mail\CandidateOnboardingMail;
use App\Mail\CandidateReferenceCheckMail;
use App\Mail\HrdNewEmployeeNotificationMail;
use App\Mail\OfferingLetterMail;
use App\Models\Karyawan;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidatePkbSigner;
use App\Models\RecruitmentCandidateUserInterview;
use App\Models\RecruitmentUserInterviewEvaluation;
use App\Services\HrdAuditLogService;
use App\Services\RecruitmentStageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HrRecruitmentCandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            RecruitmentCandidate::query()
                ->with(['vacancy', 'interviewer', 'pic'])
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
            'phone' => ['required', 'string', 'max:30'],
            'status' => ['required', Rule::in(RecruitmentStageService::STAGES)],
            'notes' => ['nullable', 'string'],
            'expected_salary' => ['required', 'integer', 'min:0'],
            'previous_salary' => ['required', 'integer', 'min:0'],
            'education_level' => ['required', 'string', 'max:50'],
            'education_major' => ['required', 'string', 'max:150'],
            'marital_status' => ['required', 'string', 'max:50'],
            'known_person' => ['nullable', 'string', 'max:150'],
            'referred_from' => ['nullable', 'string', 'max:150'],
            'pic_nik' => ['required', 'string', 'exists:m_karyawan,nik'],
            'last_company' => ['nullable', 'string', 'max:255'],
            'resume' => ['required', 'file', 'mimes:pdf', 'max:5120'],

            // Interview fields
            'interview_date' => ['nullable', 'date'],
            'interview_time' => ['nullable', 'string'],
            'interviewer_nik' => ['nullable', 'string', 'exists:m_karyawan,nik'],
            'interview_type' => ['nullable', 'in:online,offline'],
            'interview_location' => ['nullable', 'string', 'max:255'],
            'interview_meet_link' => ['nullable', 'string', 'max:255'],
            'interview_is_locked' => ['nullable', 'boolean'],
            'interview_appearance' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_attitude' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_communication' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_motivation' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_initiative' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_teamwork' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_domain_experience' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_general_knowledge' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_growth_potential' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_total_score' => ['nullable', 'integer', 'min:9', 'max:36'],
            'interview_evaluation_notes' => ['nullable', 'string'],
            'interview_recommendation' => ['nullable', 'string', 'in:tidak_disarankan,dipertimbangkan,disarankan'],
        ]);

        if ($request->hasFile('resume')) {
            $payload['resume_path'] = $request->file('resume')->store('recruitment-resumes', 'local');
        }

        $candidate = RecruitmentCandidate::query()->create($payload);
        app(RecruitmentStageService::class)->recordInitial($candidate, $request->user());

        // If status is interview and interviewer is assigned

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

        return response()->json(['message' => 'Kandidat berhasil ditambahkan.', 'data' => $candidate->load(['vacancy', 'interviewer', 'pic'])], 201);
    }

    public function show(RecruitmentCandidate $candidate): JsonResponse
    {
        $candidate->load(['vacancy', 'interviewer', 'pic', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']);

        // Auto-generate missing evaluation records for pre-existing scheduled interviews
        foreach ($candidate->userInterviews as $userInterview) {
            $rawNiks = $userInterview->interviewer_nik;
            $selectedNiks = $this->parseInterviewerNiks($rawNiks);

            foreach ($selectedNiks as $nik) {
                $exists = RecruitmentUserInterviewEvaluation::where('candidate_id', $candidate->id)
                    ->where('round', $userInterview->round)
                    ->where('interviewer_nik', $nik)
                    ->exists();

                if (! $exists) {
                    RecruitmentUserInterviewEvaluation::create([
                        'candidate_id' => $candidate->id,
                        'round' => $userInterview->round,
                        'interviewer_nik' => $nik,
                        'token' => \Illuminate\Support\Str::random(40),
                    ]);
                }
            }
        }

        // Reload relationships to capture the new evaluations
        $candidate->unsetRelation('userInterviewEvaluations');
        $candidate->load('userInterviewEvaluations.interviewer');

        $logs = \App\Models\HrdAuditLog::query()
            ->where('subject_type', RecruitmentCandidate::class)
            ->where('subject_id', $candidate->id)
            ->latest()
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor_name' => $log->actor_name,
                'actor_username' => $log->actor_username,
                'changes' => $log->changes,
                'occurred_at' => $log->occurred_at ? $log->occurred_at->toIso8601String() : $log->created_at->toIso8601String(),
            ]);

        return response()->json([
            'candidate' => $candidate,
            'change_logs' => $logs,
        ]);
    }

    public function update(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $payload = $request->validate([
            'vacancy_id' => ['nullable', 'exists:recruitment_vacancies,id'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(RecruitmentStageService::STAGES)],
            'stage_change_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'expected_salary' => ['nullable', 'integer', 'min:0'],
            'previous_salary' => ['nullable', 'integer', 'min:0'],
            'education_level' => ['nullable', 'string', 'max:50'],
            'education_major' => ['nullable', 'string', 'max:150'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'known_person' => ['nullable', 'string', 'max:150'],
            'referred_from' => ['nullable', 'string', 'max:150'],
            'pic_nik' => ['nullable', 'string', 'exists:m_karyawan,nik'],
            'last_company' => ['nullable', 'string', 'max:255'],
            'interview_hr_summary_path' => ['nullable', 'string', 'max:255'],
            'interview_hr_text_summary' => ['nullable', 'string'],
            'case_study_submitted_file_path' => ['nullable', 'string', 'max:255'],

            // Interview fields
            'interview_date' => ['nullable', 'date'],
            'interview_time' => ['nullable', 'string'],
            'interviewer_nik' => ['nullable', 'string', 'exists:m_karyawan,nik'],
            'interview_type' => ['nullable', 'in:online,offline'],
            'interview_location' => ['nullable', 'string', 'max:255'],
            'interview_meet_link' => ['nullable', 'string', 'max:255'],
            'interview_is_locked' => ['nullable', 'boolean'],
            'interview_appearance' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_attitude' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_communication' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_motivation' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_initiative' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_teamwork' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_domain_experience' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_general_knowledge' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_growth_potential' => ['nullable', 'integer', 'min:1', 'max:4'],
            'interview_total_score' => ['nullable', 'integer', 'min:9', 'max:36'],
            'interview_evaluation_notes' => ['nullable', 'string'],
            'interview_recommendation' => ['nullable', 'string', 'in:tidak_disarankan,dipertimbangkan,disarankan'],
        ]);

        $addsHrSummary = ! empty($payload['interview_hr_summary_path']) || ! empty($payload['interview_hr_text_summary']);
        abort_if(
            $addsHrSummary && ! $candidate->interview_hr_completed_at,
            422,
            'Tandai wawancara HR sebagai selesai sebelum mengisi summary.',
        );

        $nextStatus = $payload['status'];
        $stageChangeReason = $payload['stage_change_reason'] ?? null;
        unset($payload['status'], $payload['stage_change_reason']);
        if ($candidate->status === 'reference_check' && $nextStatus === 'offering') {
            $references = $candidate->references()->get();
            abort_if($references->isEmpty() || $references->contains(fn ($reference) => ! $reference->submitted_at), 422, 'Seluruh pemberi referensi harus menyelesaikan formulir Reference Check terlebih dahulu.');
        }
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $candidate->update($payload);
        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            $nextStatus,
            $request->user(),
            $stageChangeReason,
            ['source' => 'candidate_update'],
        );

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

        return response()->json(['message' => 'Kandidat berhasil diperbarui.', 'data' => $candidate->load(['vacancy', 'interviewer'])]);
    }

    public function destroy(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $resume = $candidate->resume_path;
        $photo = $candidate->photo_path;
        $offering = $candidate->offering_letter_path;

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $subjectId = $candidate->id;
        $subjectLabel = "Candidate #{$candidate->id}: {$candidate->name}";

        $candidate->delete();

        if ($resume) {
            Storage::disk('local')->delete($resume);
        }
        if ($photo) {
            Storage::disk('local')->delete($photo);
        }
        if ($offering) {
            Storage::disk('local')->delete($offering);
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
            'data' => $candidate->load(['vacancy', 'interviewer']),
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

    public function previewHrInterviewSummary(RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->interview_hr_summary_path && Storage::disk('local')->exists($candidate->interview_hr_summary_path), 404);

        $path = $candidate->interview_hr_summary_path;
        $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return response()->json([
            'filename' => basename($path),
            'mime_type' => $mime,
            'content_base64' => base64_encode(Storage::disk('local')->get($path)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function previewCaseStudySubmission(RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->case_study_submitted_file_path && Storage::disk('local')->exists($candidate->case_study_submitted_file_path), 404);

        $path = $candidate->case_study_submitted_file_path;
        $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return response()->json([
            'filename' => basename($path),
            'mime_type' => $mime,
            'content_base64' => base64_encode(Storage::disk('local')->get($path)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function uploadPhoto(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'], // Max 2MB Image
        ]);

        $photoPath = $request->file('photo')->store('recruitment-photos', 'local');
        $oldPhoto = $candidate->photo_path;

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $candidate->update(['photo_path' => $photoPath]);

        if ($oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Uploaded Photo)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => $candidate->load(['vacancy', 'interviewer']),
        ]);
    }

    public function previewPhoto(RecruitmentCandidate $candidate)
    {
        abort_unless($candidate->photo_path && Storage::disk('local')->exists($candidate->photo_path), 404);

        $content = Storage::disk('local')->get($candidate->photo_path);

        return response($content)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, no-store');
    }

    public function uploadOfferingLetter(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $request->validate([
            'offering_letter' => ['required', 'file', 'mimes:pdf', 'max:5120'], // Max 5MB PDF
        ]);

        $offeringPath = $request->file('offering_letter')->store('recruitment-offerings', 'local');
        $oldOffering = $candidate->offering_letter_path;

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $candidate->update([
            'offering_letter_path' => $offeringPath,
        ]);
        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            'offering',
            $request->user(),
            null,
            ['source' => 'upload_offering_letter'],
        );

        if ($oldOffering) {
            Storage::disk('local')->delete($oldOffering);
        }

        // Send Email offering to candidate
        try {
            Mail::to($candidate->email)->send(new \App\Mail\OfferingLetterMail($candidate));
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email offering letter', ['error' => $e->getMessage()]);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Uploaded Offering Letter & Moved to Offered)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Offering letter berhasil diunggah dan dikirim ke kandidat.',
            'data' => $candidate->load(['vacancy', 'interviewer']),
        ]);
    }

    public function previewOfferingLetter(RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->offering_letter_path && Storage::disk('local')->exists($candidate->offering_letter_path), 404);

        return response()->json([
            'filename' => 'Offering-Letter-'.str($candidate->name)->slug().'.pdf',
            'mime_type' => 'application/pdf',
            'content_base64' => base64_encode(Storage::disk('local')->get($candidate->offering_letter_path)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function lockInterview(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->status === 'interview', 400, 'Kandidat tidak berada dalam tahapan interview.');
        abort_unless($candidate->interview_date && $candidate->interview_time, 400, 'Jadwal interview belum diatur.');

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $candidate->update(['interview_is_locked' => true]);

        // Send email invitation to candidate
        try {
            Mail::to($candidate->email)->send(new \App\Mail\InterviewInvitationMail($candidate));
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email undangan interview', ['error' => $e->getMessage()]);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Locked Interview Schedule & Sent Invitation)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Jadwal interview berhasil dikunci dan undangan email terkirim.',
            'data' => $candidate->load(['vacancy', 'interviewer']),
        ]);
    }

    public function sendWaToInterviewer(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->status === 'interview', 400, 'Kandidat tidak berada dalam tahapan interview.');
        abort_unless($candidate->interviewer_nik, 400, 'Pewawancara belum diatur.');

        $this->sendInterviewWhatsAppNotification($candidate);

        // Record audit log
        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Sent Interview WhatsApp Invitation to Interviewer)",
            app(HrdAuditLogService::class)->snapshot($candidate),
            $candidate,
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Undangan WhatsApp berhasil dikirim ke pewawancara.',
            'data' => $candidate->load(['vacancy', 'interviewer']),
        ]);
    }

    public function scheduleHrInterview(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_if(
            $candidate->interview_hr_completed_at,
            422,
            'Wawancara HR sudah ditandai selesai dan jadwal tidak dapat diubah.',
        );

        $payload = $request->validate([
            'interview_hr_date' => ['required', 'date'],
            'interview_hr_time' => ['required', 'string'],
            'interview_hr_type' => ['required', 'in:online,offline'],
            'interview_hr_location' => ['nullable', 'string', 'max:255'],
            'interview_hr_meet_link' => ['nullable', 'string', 'max:255'],
        ]);

        $this->assertInterviewScheduleIsFuture(
            $payload['interview_hr_date'],
            $payload['interview_hr_time'],
            'Wawancara HR',
        );

        // Check if the PIC Screening has another interview on the same day within 90 minutes
        $picNik = $candidate->pic_nik;
        if ($picNik) {
            $proposedTime = \Carbon\Carbon::parse($payload['interview_hr_time']);
            
            $conflictingCandidate = RecruitmentCandidate::where('id', '!=', $candidate->id)
                ->where('pic_nik', $picNik)
                ->where('interview_hr_date', $payload['interview_hr_date'])
                ->whereNotNull('interview_hr_time')
                ->get()
                ->first(function ($c) use ($proposedTime) {
                    $existingTime = \Carbon\Carbon::parse($c->interview_hr_time);
                    return abs($proposedTime->diffInMinutes($existingTime)) < 90;
                });

            if ($conflictingCandidate) {
                $existingTimeFormatted = \Carbon\Carbon::parse($conflictingCandidate->interview_hr_time)->format('H:i');
                return response()->json([
                    'message' => "PIC Screening sudah memiliki jadwal wawancara HR lain pada tanggal tersebut pukul {$existingTimeFormatted} (Kandidat: {$conflictingCandidate->name}). Harap berikan jeda minimal 90 menit.",
                ], 422);
            }
        }

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $updateData = $payload;

        if ($candidate->interview_hr_date && (
            $candidate->interview_hr_date !== $payload['interview_hr_date'] ||
            $candidate->interview_hr_time !== $payload['interview_hr_time'] ||
            $candidate->interview_hr_type !== $payload['interview_hr_type']
        )) {
            $updateData['interview_hr_prev_date'] = $candidate->interview_hr_date;
            $updateData['interview_hr_prev_time'] = $candidate->interview_hr_time;
        }

        $candidate->update($updateData);
        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            'interview_hr',
            $request->user(),
            null,
            ['source' => 'schedule_hr_interview'],
        );

        $emailSent = false;
        try {
            Mail::to($candidate->email)->send(new \App\Mail\InterviewInvitationMail($candidate, true));
            $emailSent = true;
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email undangan wawancara HR', ['error' => $e->getMessage()]);
        }

        if ($emailSent) {
            $candidate->update(['interview_hr_email_sent_at' => now()]);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Scheduled HR Interview)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Jadwal wawancara HR berhasil disimpan dan email undangan dikirim.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function sendWaToCandidate(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->interview_hr_date, 400, 'Jadwal wawancara HR belum diatur.');
        abort_unless($candidate->phone, 400, 'Nomor telepon kandidat tidak ditemukan.');

        $candidate->loadMissing('pic');
        $picPhone = $candidate->pic ? $candidate->pic->no_hp : '-';
        $picName = $candidate->pic ? ($candidate->pic->nama_karyawan ?? $candidate->pic->name) : 'Tim HRD';

        $isReschedule = !empty($candidate->interview_hr_prev_date);

        if ($isReschedule) {
            $message = "Yth. Sdr/i. *{$candidate->name}*,\n\n".
                       "Mohon maaf, terdapat perubahan jadwal untuk tahapan *Wawancara HR* Anda.\n\n".
                       "Rincian lengkap dan tautan/lokasi wawancara terbaru telah kami kirimkan ke email Anda: *{$candidate->email}*.\n\n".
                       "Mohon konfirmasi kehadiran Anda dengan menghubungi PIC Screening Anda (*{$picName}*) di nomor *{$picPhone}*.\n\n".
                       "Hormat kami,\n".
                       "HRBP Team – Hompim Play\n\n".
                       "_*Catatan:* Mohon tidak membalas pesan ini secara langsung karena dikirim otomatis oleh sistem. Hubungi nomor PIC HRD di atas untuk konfirmasi._";
        } else {
            $message = "Yth. Sdr/i. *{$candidate->name}*,\n\n".
                       "Selamat! Kami menginformasikan bahwa Anda dinyatakan lolos seleksi berkas dan melanjutkan ke tahapan *Wawancara HR*.\n\n".
                       "Rincian lengkap jadwal dan tautan/lokasi wawancara telah kami kirimkan ke email Anda: *{$candidate->email}*.\n\n".
                       "Mohon konfirmasi kehadiran Anda dengan menghubungi PIC Screening Anda (*{$picName}*) di nomor *{$picPhone}*.\n\n".
                       "Hormat kami,\n".
                       "HRBP Team – Hompim Play\n\n".
                       "_*Catatan:* Mohon tidak membalas pesan ini secara langsung karena dikirim otomatis oleh sistem. Hubungi nomor PIC HRD di atas untuk konfirmasi._";
        }

        $success = false;
        try {
            $success = app(WhatsAppService::class)->sendMessage($candidate->phone, $message);
        } catch (\Exception $e) {
            Log::error('Failed sending WA notification to candidate', ['error' => $e->getMessage()]);
        }

        if ($success) {
            // Update tracking columns
            $candidate->update([
                'interview_hr_wa_sent_at' => now(),
                'interview_hr_wa_sent_date' => $candidate->interview_hr_date,
                'interview_hr_wa_sent_time' => $candidate->interview_hr_time,
                'interview_hr_wa_sent_type' => $candidate->interview_hr_type,
            ]);

            // Record audit log
            app(HrdAuditLogService::class)->record(
                $request,
                'RecruitmentCandidate',
                'updated',
                "Candidate #{$candidate->id}: {$candidate->name} (Sent HR Interview WhatsApp Invitation to Candidate)",
                app(HrdAuditLogService::class)->snapshot($candidate),
                $candidate,
                RecruitmentCandidate::class,
                $candidate->id
            );

            return response()->json([
                'message' => 'Undangan WhatsApp berhasil dikirim ke kandidat.',
                'data' => $candidate->fresh()->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim undangan WhatsApp. Periksa log atau konfigurasi service.',
        ], 500);
    }

    public function sendWaCaseStudyToCandidate(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->phone, 422, 'Nomor HP kandidat tidak tersedia.');
        abort_unless($candidate->case_study_sent_at, 422, 'Harap kirimkan soal/instruksi case study terlebih dahulu.');

        $candidate->loadMissing(['vacancy', 'pic']);
        $picPhone = $candidate->pic ? $candidate->pic->no_hp : '-';
        $picName = $candidate->pic ? ($candidate->pic->nama_karyawan ?? $candidate->pic->name) : 'Tim HRD';

        $message = "Yth. Sdr/i. *{$candidate->name}*,\n\n".
                   "Selamat! Kami menginformasikan bahwa Anda dinyatakan lolos ke tahapan selanjutnya, yaitu *Case Study*.\n\n".
                   "Soal studi kasus, batas waktu, dan instruksi pengerjaan lengkap telah kami kirimkan ke email Anda: *{$candidate->email}*.\n\n".
                   "Jika Anda memiliki pertanyaan lebih lanjut, silakan hubungi PIC HRD Anda (*{$picName}*) di nomor *{$picPhone}*.\n\n".
                   "Hormat kami,\n".
                   "HRBP Team – Hompim Play\n\n".
                   "_*Catatan:* Mohon tidak membalas pesan ini secara langsung karena dikirim otomatis oleh sistem. Hubungi nomor PIC HRD di atas untuk pertanyaan lebih lanjut._";

        $success = false;
        try {
            $success = app(WhatsAppService::class)->sendMessage($candidate->phone, $message);
        } catch (\Exception $e) {
            Log::error('Failed sending WA notification for Case Study to candidate', ['error' => $e->getMessage()]);
        }

        if ($success) {
            $candidate->update([
                'case_study_wa_sent_at' => now(),
            ]);

            app(HrdAuditLogService::class)->record(
                $request,
                'RecruitmentCandidate',
                'updated',
                "Candidate #{$candidate->id}: {$candidate->name} (Sent Case Study WhatsApp Notification to Candidate)",
                app(HrdAuditLogService::class)->snapshot($candidate),
                $candidate,
                RecruitmentCandidate::class,
                $candidate->id
            );

            return response()->json([
                'message' => 'Notifikasi WhatsApp berhasil dikirim ke kandidat.',
                'data' => $candidate->fresh()->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim notifikasi WhatsApp. Periksa log atau konfigurasi service.',
        ], 500);
    }

    public function completeHrInterview(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $payload = $request->validate([
            'completed_time' => ['required', 'date_format:H:i'],
        ], [
            'completed_time.required' => 'Jam selesai wawancara HR wajib dipilih.',
            'completed_time.date_format' => 'Format jam selesai wawancara HR tidak valid.',
        ]);

        abort_unless(
            $candidate->interview_hr_date && $candidate->interview_hr_time,
            422,
            'Jadwal wawancara HR belum diatur.',
        );

        if ($candidate->interview_hr_completed_at) {
            return response()->json([
                'message' => 'Wawancara HR sudah ditandai selesai.',
                'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
            ]);
        }

        $scheduledAt = Carbon::parse(Carbon::parse($candidate->interview_hr_date)->toDateString().' '.$candidate->interview_hr_time);
        $completionAvailableAt = $scheduledAt->copy()->addHour();
        abort_if(
            now()->lt($completionAvailableAt),
            422,
            'Wawancara HR hanya dapat ditandai selesai paling cepat 1 jam setelah jadwal interview.',
        );

        $completedAt = Carbon::parse(Carbon::parse($candidate->interview_hr_date)->toDateString().' '.$payload['completed_time']);
        abort_if($completedAt->lte($scheduledAt), 422, 'Jam selesai wawancara HR harus lebih akhir dari jam mulai wawancara.');
        abort_if($completedAt->isFuture(), 422, 'Jam selesai wawancara HR tidak boleh melebihi waktu sekarang.');

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $candidate->update([
            'interview_hr_completed_at' => $completedAt,
            'interview_hr_completed_by' => $request->user()?->id,
        ]);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Completed HR Interview)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id,
        );

        return response()->json([
            'message' => 'Wawancara HR berhasil ditandai selesai. Summary sekarang dapat diisi.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function uploadHrInterviewSummary(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless(
            $candidate->interview_hr_completed_at,
            422,
            'Tandai wawancara HR sebagai selesai sebelum mengisi atau mengunggah summary.',
        );

        $request->validate([
            'summary' => ['nullable', 'file', 'mimes:pdf,docx,doc,txt', 'max:5120'],
            'summary_text' => ['nullable', 'string'],
        ], [
            'summary.mimes' => 'Format file summary tidak valid. File harus berupa: pdf, docx, doc, atau txt.',
            'summary.max' => 'Ukuran berkas summary tidak boleh melebihi 5 MB.',
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $updateData = [];

        if ($request->hasFile('summary')) {
            $path = $request->file('summary')->store('recruitment-hr-summaries', 'local');
            $oldPath = $candidate->interview_hr_summary_path;
            $updateData['interview_hr_summary_path'] = $path;

            if ($oldPath) {
                Storage::disk('local')->delete($oldPath);
            }
        }

        if ($request->has('summary_text')) {
            $updateData['interview_hr_text_summary'] = $request->input('summary_text');
        }

        if (! empty($updateData)) {
            $candidate->update($updateData);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Updated HR Interview Summary)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Summary wawancara HR berhasil disimpan.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function sendCaseStudy(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $request->validate([
            'document' => ['nullable', 'file', 'mimes:pdf,docx,doc,zip', 'max:10240'],
            'link' => ['nullable', 'url', 'max:255'],
        ], [
            'document.mimes' => 'Format file dokumen tidak valid. File harus berupa: pdf, docx, doc, atau zip.',
            'document.max' => 'Ukuran berkas dokumen tidak boleh melebihi 10 MB.',
            'link.url' => 'Format tautan harus berupa URL yang valid.',
        ]);

        if (! $request->hasFile('document') && ! $request->filled('link')) {
            return response()->json([
                'message' => 'Harap isi tautan soal atau unggah file dokumen studi kasus.',
            ], 422);
        }

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('recruitment-case-studies', 'local');
        }

        $token = Str::random(40);
        $caseStudyPassword = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $candidate->update([
            'case_study_document_path' => $documentPath ?: $candidate->case_study_document_path,
            'case_study_link' => $request->input('link') ?: $candidate->case_study_link,
            'case_study_sent_at' => now(),
            'case_study_token' => $token,
            'case_study_password' => Hash::make($caseStudyPassword),
        ]);
        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            'case_study',
            $request->user(),
            null,
            ['source' => 'send_case_study'],
        );

        try {
            $uploadLinkLong = rtrim((string) config('app.frontend_url'), '/')."/public/case-study/{$token}";
            $uploadLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($uploadLinkLong, now()->addDays(3));
            $hasAttachment = !empty($candidate->case_study_document_path);
            Mail::to($candidate->email)->send(new CandidateCaseStudyMail(
                $candidate,
                $candidate->case_study_link,
                $hasAttachment ? basename($candidate->case_study_document_path) : null,
                $uploadLink,
                $caseStudyPassword
            ));
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email studi kasus', ['error' => $e->getMessage()]);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Sent Case Study)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Studi kasus berhasil dikirim ke kandidat.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function uploadCaseStudySubmission(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->case_study_sent_at, 422, 'Harap kirimkan soal/instruksi case study terlebih dahulu.');

        $request->validate([
            'submission' => ['required', 'file', 'mimes:pdf,docx,doc,zip,rar', 'max:15360'],
        ], [
            'submission.required' => 'Berkas pengumpulan studi kasus wajib diunggah.',
            'submission.mimes' => 'Format file tidak valid. File harus berupa: pdf, docx, doc, zip, atau rar.',
            'submission.max' => 'Ukuran berkas pengumpulan tidak boleh melebihi 15 MB.',
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $path = $request->file('submission')->store('recruitment-case-study-submissions', 'local');
        $oldPath = $candidate->case_study_submitted_file_path;

        $candidate->update([
            'case_study_submitted_file_path' => $path,
            'case_study_submitted_at' => now(),
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Uploaded Case Study Submission)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Penyelesaian studi kasus berhasil diunggah.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function completeUserInterviewRound(Request $request, RecruitmentCandidate $candidate, int $round): JsonResponse
    {
        abort_unless($round >= 1 && $round <= 3, 404);

        $payload = $request->validate([
            'completed_time' => ['required', 'date_format:H:i'],
        ], [
            'completed_time.required' => "Jam selesai Wawancara User Tahap {$round} wajib dipilih.",
            'completed_time.date_format' => "Format jam selesai Wawancara User Tahap {$round} tidak valid.",
        ]);

        $userInterview = RecruitmentCandidateUserInterview::query()
            ->where('candidate_id', $candidate->id)
            ->where('round', $round)
            ->firstOrFail();

        abort_unless(
            $userInterview->interview_date && $userInterview->interview_time,
            422,
            "Jadwal Wawancara User Tahap {$round} belum lengkap.",
        );

        if ($userInterview->completed_at) {
            return response()->json([
                'message' => "Wawancara User Tahap {$round} sudah ditandai selesai.",
                'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
            ]);
        }

        $scheduledAt = Carbon::parse($userInterview->interview_date.' '.$userInterview->interview_time);
        $completionAvailableAt = $scheduledAt->copy()->addHour();
        abort_if(
            now()->lt($completionAvailableAt),
            422,
            "Wawancara User Tahap {$round} hanya dapat ditandai selesai paling cepat 1 jam setelah jadwal interview.",
        );

        $completedAt = Carbon::parse($userInterview->interview_date.' '.$payload['completed_time']);
        abort_if($completedAt->lte($scheduledAt), 422, "Jam selesai Wawancara User Tahap {$round} harus lebih akhir dari jam mulai wawancara.");
        abort_if($completedAt->isFuture(), 422, "Jam selesai Wawancara User Tahap {$round} tidak boleh melebihi waktu sekarang.");

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);
        $userInterview->update([
            'completed_at' => $completedAt,
            'completed_by' => $request->user()?->id,
        ]);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Completed User Interview Round {$round})",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id,
        );

        return response()->json([
            'message' => "Wawancara User Tahap {$round} berhasil ditandai selesai. Link evaluasi sekarang dapat dikirim.",
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function scheduleUserInterviewRound(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $payload = $request->validate([
            'round' => ['required', 'integer', 'min:1', 'max:3'],
            'interview_date' => ['required', 'date'],
            'interview_time' => ['required', 'string'],
            'interviewer_nik' => ['required', 'string', 'max:255'],
            'interview_type' => ['required', 'in:online,offline'],
            'interview_location' => ['nullable', 'string', 'max:255'],
            'interview_meet_link' => ['nullable', 'string', 'max:255'],
        ]);

        $this->assertInterviewScheduleIsFuture(
            $payload['interview_date'],
            $payload['interview_time'],
            "Wawancara User Tahap {$payload['round']}",
        );

        $existingInterview = RecruitmentCandidateUserInterview::query()
            ->where('candidate_id', $candidate->id)
            ->where('round', $payload['round'])
            ->first();
        abort_if(
            $existingInterview?->completed_at,
            422,
            "Wawancara User Tahap {$payload['round']} sudah ditandai selesai dan jadwal tidak dapat diubah.",
        );

        if ($payload['round'] > 1) {
            $previousRound = $payload['round'] - 1;
            $previousInterview = RecruitmentCandidateUserInterview::query()
                ->where('candidate_id', $candidate->id)
                ->where('round', $previousRound)
                ->first();

            abort_unless(
                $previousInterview?->completed_at,
                422,
                "Wawancara User Tahap {$previousRound} harus ditandai selesai sebelum membuat Tahap {$payload['round']}.",
            );

            $previousEvaluations = RecruitmentUserInterviewEvaluation::query()
                ->where('candidate_id', $candidate->id)
                ->where('round', $previousRound)
                ->get(['submitted_at']);

            abort_if(
                $previousEvaluations->isEmpty() || $previousEvaluations->contains(fn ($evaluation) => ! $evaluation->submitted_at),
                422,
                "Seluruh evaluasi Wawancara User Tahap {$previousRound} harus diselesaikan sebelum membuat Tahap {$payload['round']}.",
            );
        }

        $selectedNiks = $this->parseInterviewerNiks($payload['interviewer_nik']);
        $conflictReason = $this->getConflictReason($candidate->id, $payload['round'], $payload['interview_date'], $payload['interview_time'], $selectedNiks);
        if ($conflictReason) {
            return response()->json([
                'message' => $conflictReason,
            ], 422);
        }

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            'interview_user',
            $request->user(),
            null,
            ['source' => 'schedule_user_interview'],
        );

        $userInterview = RecruitmentCandidateUserInterview::updateOrCreate(
            ['candidate_id' => $candidate->id, 'round' => $payload['round']],
            $payload
        );

        // Synchronize evaluations for each selected interviewer
        $existingEvaluations = RecruitmentUserInterviewEvaluation::where('candidate_id', $candidate->id)
            ->where('round', $payload['round'])
            ->get();

        foreach ($existingEvaluations as $eval) {
            if (! in_array($eval->interviewer_nik, $selectedNiks)) {
                if (! $eval->submitted_at) {
                    $eval->delete();
                }
            }
        }

        foreach ($selectedNiks as $nik) {
            $exists = $existingEvaluations->contains('interviewer_nik', $nik);
            if (! $exists) {
                RecruitmentUserInterviewEvaluation::create([
                    'candidate_id' => $candidate->id,
                    'round' => $payload['round'],
                    'interviewer_nik' => $nik,
                    'token' => Str::random(40),
                ]);
            }
        }

        try {
            Mail::to($candidate->email)->send(new \App\Mail\CandidateUserInterviewMail($candidate, $userInterview));
            $userInterview->update(['email_sent_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email undangan wawancara user', ['error' => $e->getMessage()]);
        }

        $firstNik = $selectedNiks[0] ?? null;
        $interviewer = $firstNik ? Karyawan::where('nik', $firstNik)->first() : null;
        if ($interviewer && $interviewer->no_hp) {
            try {
                $formattedDate = Carbon::parse($userInterview->interview_date)->locale('id')->translatedFormat('l, d F Y');
                $time = substr($userInterview->interview_time, 0, 5);
                $type = $userInterview->interview_type === 'online' ? 'Online (Tautan Meet)' : 'Offline (Lokasi Fisik)';
                $details = $userInterview->interview_type === 'online' ? $userInterview->interview_meet_link : $userInterview->interview_location;
                $vacancyTitle = $candidate->vacancy?->title ?? 'Umum';

                $eval = RecruitmentUserInterviewEvaluation::where('candidate_id', $candidate->id)
                    ->where('round', $payload['round'])
                    ->where('interviewer_nik', $firstNik)
                    ->first();
                $cvLink = '';
                if ($eval) {
                    $frontendUrl = config('app.frontend_url');
                    $longCvLink = rtrim((string) $frontendUrl, '/')."/public/evaluation/{$eval->token}/resume";
                    $cvLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($longCvLink);
                }

                $waMessage = "Halo Bapak/Ibu {$interviewer->nama_karyawan},\n\n".
                             "Akan ada jadwal interview rekrutmen baru dengan detail seperti berikut :\n\n".
                             "- Kandidat: {$candidate->name}\n".
                             "- Posisi Dilamar: {$vacancyTitle}\n".
                             "- Tanggal: {$formattedDate}\n".
                             "- Waktu: {$time} WIB\n".
                             "- Tipe: {$type}\n".
                             "- Lokasi/Link: {$details}\n\n".
                             "- Link CV : {$cvLink}\n".
                             "- Password CV : 123456\n\n".
                             'Mohon konfirmasi kepada HRD jika pada tanggal tersebut tidak bisa melakukan interview. Terima kasih.';

                app(WhatsAppService::class)->sendMessage($interviewer->no_hp, $waMessage);
            } catch (\Exception $e) {
                Log::error('Failed sending WA notification to user interviewer', ['error' => $e->getMessage()]);
            }
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Scheduled User Interview Round {$userInterview->round})",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => "Jadwal wawancara User Round {$userInterview->round} berhasil disimpan.",
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function saveUserInterviewRoundEvaluation(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $payload = $request->validate([
            'round' => ['required', 'integer', 'min:1', 'max:3'],
            'interview_appearance' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_attitude' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_communication' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_motivation' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_initiative' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_teamwork' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_domain_experience' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_general_knowledge' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_growth_potential' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_total_score' => ['required', 'integer', 'min:9', 'max:36'],
            'interview_evaluation_notes' => ['nullable', 'string'],
            'interview_recommendation' => ['required', 'string', 'in:tidak_disarankan,dipertimbangkan,disarankan'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $userInterview = RecruitmentCandidateUserInterview::where('candidate_id', $candidate->id)
            ->where('round', $payload['round'])
            ->firstOrFail();

        abort_unless(
            $userInterview->completed_at,
            422,
            "Tandai Wawancara User Tahap {$payload['round']} sebagai selesai sebelum mengisi evaluasi.",
        );

        $userInterview->update($payload);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Evaluated User Interview Round {$payload['round']})",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Penilaian evaluasi wawancara user berhasil disimpan.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function uploadUserInterviewRoundSummary(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $request->validate([
            'round' => ['required', 'integer', 'min:1', 'max:3'],
            'summary' => ['required', 'file', 'mimes:pdf,docx,doc,txt', 'max:5120'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $userInterview = RecruitmentCandidateUserInterview::where('candidate_id', $candidate->id)
            ->where('round', $request->input('round'))
            ->firstOrFail();

        abort_unless(
            $userInterview->completed_at,
            422,
            "Tandai Wawancara User Tahap {$userInterview->round} sebagai selesai sebelum mengunggah summary.",
        );

        $path = $request->file('summary')->store('recruitment-user-summaries', 'local');
        $oldPath = $userInterview->summary_path;

        $userInterview->update([
            'summary_path' => $path,
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Uploaded User Interview Summary Round {$userInterview->round})",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Summary wawancara user berhasil diunggah.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function sendReferenceCheckRequest(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $token = Str::random(40);
        $referencePassword = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $requiredReferenceCount = $this->requiredReferenceCount($candidate);
        $candidate->update([
            'reference_check_token' => $token,
            'reference_check_password' => Hash::make($referencePassword),
        ]);
        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            'reference_check',
            $request->user(),
            null,
            ['source' => 'send_reference_check'],
        );

        $referenceLinkLong = rtrim((string) config('app.frontend_url'), '/')."/public/reference-check/{$token}";
        $referenceLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($referenceLinkLong, now()->addDays(7));

        $emailSent = false;
        try {
            Mail::to($candidate->email)->send(new CandidateReferenceCheckMail(
                $candidate,
                $referenceLink,
                $referencePassword,
                $requiredReferenceCount
            ));
            $emailSent = true;
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email permintaan referensi check', ['error' => $e->getMessage()]);
        }

        if ($emailSent) {
            $candidate->update(['reference_check_email_sent_at' => now()]);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Sent Reference Check Request)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Tautan formulir referensi kerja berhasil dikirim ke kandidat.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function sendReferenceCheckWa(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->phone, 422, 'Nomor HP kandidat tidak tersedia.');
        abort_unless($candidate->reference_check_token, 422, 'Harap kirimkan email permintaan referensi check terlebih dahulu.');

        $candidate->loadMissing(['vacancy', 'pic']);
        $picPhone = $candidate->pic ? $candidate->pic->no_hp : '-';
        $picName = $candidate->pic ? ($candidate->pic->nama_karyawan ?? $candidate->pic->name) : 'Tim HRD';

        $message = "Yth. Sdr/i. *{$candidate->name}*,\n\n".
                   "Selamat! Kami menginformasikan bahwa Anda dinyatakan lolos ke tahapan selanjutnya, yaitu *Reference Check*.\n\n".
                   "Tautan pengisian formulir referensi kerja dan instruksi lengkap telah kami kirimkan ke email Anda: *{$candidate->email}*.\n\n".
                   "Jika Anda memiliki pertanyaan lebih lanjut, silakan hubungi PIC HRD Anda (*{$picName}*) di nomor *{$picPhone}*.\n\n".
                   "Hormat kami,\n".
                   "HRBP Team – Hompim Play\n\n".
                   "_*Catatan:* Mohon tidak membalas pesan ini secara langsung karena dikirim otomatis oleh sistem. Hubungi nomor PIC HRD di atas untuk pertanyaan lebih lanjut._";

        $success = false;
        try {
            $success = app(WhatsAppService::class)->sendMessage($candidate->phone, $message);
        } catch (\Exception $e) {
            Log::error('Failed sending WA notification for Reference Check to candidate', ['error' => $e->getMessage()]);
        }

        if ($success) {
            $candidate->update([
                'reference_check_wa_sent_at' => now(),
            ]);

            app(HrdAuditLogService::class)->record(
                $request,
                'RecruitmentCandidate',
                'updated',
                "Candidate #{$candidate->id}: {$candidate->name} (Sent Reference Check WhatsApp Notification to Candidate)",
                app(HrdAuditLogService::class)->snapshot($candidate),
                $candidate,
                RecruitmentCandidate::class,
                $candidate->id
            );

            return response()->json([
                'message' => 'Notifikasi WhatsApp berhasil dikirim ke kandidat.',
                'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim notifikasi WhatsApp ke kandidat.',
        ], 500);
    }

    public function submitCandidateReferences(Request $request, $token): JsonResponse
    {
        $candidate = RecruitmentCandidate::with('vacancy')
            ->where('reference_check_token', $token)
            ->firstOrFail();
        $requiredReferenceCount = $this->requiredReferenceCount($candidate);

        $payload = $request->validate([
            'password' => ['required', 'digits:6'],
            'references' => ['required', 'array', "min:{$requiredReferenceCount}", 'max:10'],
            'references.*.name' => ['required', 'string', 'max:150', 'distinct:ignore_case'],
            'references.*.phone' => ['required', 'string', 'max:50', 'distinct'],
            'references.*.company' => ['required', 'string', 'max:150'],
            'references.*.position' => ['required', 'string', 'max:150'],
            'references.*.relationship' => ['required', 'string', 'max:50'],
        ]);

        abort_unless(
            $candidate->reference_check_password
                && Hash::check($payload['password'], $candidate->reference_check_password),
            403,
            'Password formulir tidak valid.'
        );

        $candidate->references()->delete();

        foreach ($payload['references'] as $ref) {
            $candidate->references()->create($ref + [
                'form_type' => $this->isManagerialReference($candidate) ? 'managerial' : 'staff',
                'public_token' => Str::random(64),
                'public_code' => Str::random(12),
            ]);
        }

        $candidate->update([
            'reference_check_submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Referensi kerja berhasil disimpan.',
        ]);
    }

    public function previewReferenceCheckSummary(RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->reference_check_summary_path && Storage::disk('local')->exists($candidate->reference_check_summary_path), 404);

        $path = $candidate->reference_check_summary_path;
        $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return response()->json([
            'filename' => basename($path),
            'mime_type' => $mime,
            'content_base64' => base64_encode(Storage::disk('local')->get($path)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function previewUserInterviewRoundSummary(RecruitmentCandidate $candidate, int $round): JsonResponse
    {
        $userInterview = $candidate->userInterviews()->where('round', $round)->firstOrFail();

        abort_unless($userInterview->summary_path && Storage::disk('local')->exists($userInterview->summary_path), 404);

        $path = $userInterview->summary_path;

        return response()->json([
            'filename' => basename($path),
            'mime_type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
            'content_base64' => base64_encode(Storage::disk('local')->get($path)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function uploadReferenceCheckSummary(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $references = $candidate->references()->get();
        abort_if($references->isEmpty() || $references->contains(fn ($reference) => ! $reference->submitted_at), 422, 'Summary hanya dapat diunggah setelah seluruh pemberi referensi menyelesaikan formulir.');

        $request->validate([
            'summary' => ['required', 'file', 'mimes:pdf,docx,doc,txt', 'max:5120'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $path = $request->file('summary')->store('recruitment-reference-summaries', 'local');
        $oldPath = $candidate->reference_check_summary_path;

        $candidate->update([
            'reference_check_summary_path' => $path,
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Uploaded Reference Check Summary)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Summary reference check berhasil diunggah.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function sendOfferingLetterWithSignature(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->status === 'offering', 422, 'Ubah status kandidat ke Offering terlebih dahulu.');

        $request->validate([
            'offering_letter' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'last_company' => ['required', 'string', 'max:255'],
            'offered_salary' => ['required', 'integer', 'min:0'],
            'join_date' => ['required', 'date'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $path = $request->file('offering_letter')->store('recruitment-offerings', 'local');
        $oldPath = $candidate->offering_letter_path;

        $token = Str::random(40);
        $offeringPassword = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $candidate->update([
            'offering_letter_path' => $path,
            'offering_letter_token' => $token,
            'offering_letter_password' => Hash::make($offeringPassword),
            'offering_letter_sent_at' => null,
            'offering_letter_signature_data' => null,
            'offering_letter_signed_at' => null,
            'last_company' => $request->input('last_company'),
            'offered_salary' => $request->input('offered_salary'),
            'join_date' => $request->input('join_date'),
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        try {
            $signLinkLong = rtrim((string) config('app.frontend_url'), '/')."/public/offering/review/{$token}";
            $signLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($signLinkLong, now()->addDays(3));
            Mail::to($candidate->email)->send(new OfferingLetterMail($candidate, $signLink, $offeringPassword));
            $candidate->update(['offering_letter_sent_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email offering letter', ['error' => $e->getMessage()]);

            app(HrdAuditLogService::class)->record(
                $request,
                'RecruitmentCandidate',
                'updated',
                "Candidate #{$candidate->id}: {$candidate->name} (Uploaded Offering Letter - Email Failed)",
                $beforeAudit,
                $candidate->fresh(),
                RecruitmentCandidate::class,
                $candidate->id
            );

            return response()->json([
                'message' => 'Offering letter tersimpan, tetapi email gagal dikirim. Silakan coba kirim ulang.',
            ], 500);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Uploaded Offering Letter & Sent Signature Request)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Offering letter berhasil diunggah dan dikirim ke kandidat.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function sendOfferingLetterWa(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->phone, 422, 'Nomor HP kandidat tidak tersedia.');
        abort_unless($candidate->offering_letter_token, 422, 'Harap unggah dan kirim email offering letter terlebih dahulu.');

        $candidate->loadMissing(['vacancy', 'pic']);
        $picPhone = $candidate->pic ? $candidate->pic->no_hp : '-';
        $picName = $candidate->pic ? ($candidate->pic->nama_karyawan ?? $candidate->pic->name) : 'Tim HRD';

        $message = "Yth. Sdr/i. *{$candidate->name}*,\n\n".
                   "Selamat! Kami menginformasikan bahwa Anda dinyatakan lolos ke tahapan selanjutnya, yaitu *Offering Letter* (Penawaran Kerja).\n\n".
                   "Dokumen offering letter dan instruksi lengkap persetujuan telah kami kirimkan ke email Anda: *{$candidate->email}*.\n\n".
                   "Jika Anda memiliki pertanyaan lebih lanjut, silakan hubungi PIC HRD Anda (*{$picName}*) di nomor *{$picPhone}*.\n\n".
                   "Hormat kami,\n".
                   "HRBP Team – Hompim Play\n\n".
                   "_*Catatan:* Mohon tidak membalas pesan ini secara langsung karena dikirim otomatis oleh sistem. Hubungi nomor PIC HRD di atas untuk pertanyaan lebih lanjut._";

        $success = false;
        try {
            $success = app(WhatsAppService::class)->sendMessage($candidate->phone, $message);
        } catch (\Exception $e) {
            Log::error('Failed sending WA notification for Offering Letter to candidate', ['error' => $e->getMessage()]);
        }

        if ($success) {
            $candidate->update([
                'offering_letter_wa_sent_at' => now(),
            ]);

            app(HrdAuditLogService::class)->record(
                $request,
                'RecruitmentCandidate',
                'updated',
                "Candidate #{$candidate->id}: {$candidate->name} (Sent Offering Letter WhatsApp Notification to Candidate)",
                app(HrdAuditLogService::class)->snapshot($candidate),
                $candidate,
                RecruitmentCandidate::class,
                $candidate->id
            );

            return response()->json([
                'message' => 'Notifikasi WhatsApp berhasil dikirim ke kandidat.',
                'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim notifikasi WhatsApp ke kandidat.',
        ], 500);
    }

    public function submitCandidateOfferingSignature(Request $request, $token): JsonResponse
    {
        $candidate = RecruitmentCandidate::query()
            ->where('offering_letter_token', $token)
            ->firstOrFail();

        $payload = $request->validate([
            'password' => ['required', 'digits:6'],
            'signature_data' => ['required', 'string'],
        ]);

        abort_unless(
            $candidate->offering_letter_password
                && Hash::check($payload['password'], $candidate->offering_letter_password),
            403,
            'PIN Offering Letter tidak valid.'
        );

        $updates = [
            'offering_letter_signature_data' => $payload['signature_data'],
            'offering_letter_signed_at' => now(),
            'offering_letter_token' => null,
            'offering_letter_password' => null,
        ];

        if ($candidate->reference_check_token === $token) {
            $updates['reference_check_token'] = null;
        }

        $candidate->update($updates);

        try {
            $hrdEmails = \App\Models\User::where('level', 2)->pluck('email')->filter()->all();

            if ($hrdEmails === []) {
                $hrdEmails = ['hrd@biensi.co.id'];
            }

            Mail::raw(
                "Offering letter untuk kandidat {$candidate->name} telah ditandatangani pada ".now()->format('d-m-Y H:i').' WIB.',
                function ($message) use ($candidate, $hrdEmails) {
                    $message->to($hrdEmails)
                        ->subject('Offering Letter Telah Ditandatangani - '.$candidate->name);
                }
            );
        } catch (\Exception $e) {
            Log::error('Gagal mengirim notifikasi offering letter ke HRD', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Offering letter berhasil ditandatangani.',
        ]);
    }

    public function getPublicCaseStudy(Request $request, $token): JsonResponse
    {
        $validated = validator([
            'password' => $request->header('X-Case-Study-Password'),
        ], [
            'password' => ['required', 'digits:6'],
        ])->validate();

        $candidate = RecruitmentCandidate::query()
            ->where('case_study_token', $token)
            ->firstOrFail();

        abort_unless(
            $candidate->case_study_password
                && Hash::check($validated['password'], $candidate->case_study_password),
            403,
            'PIN Case Study tidak valid.'
        );

        return response()->json([
            'name' => $candidate->name,
            'vacancy_title' => $candidate->vacancy?->title ?? 'Umum',
            'case_study_link' => $candidate->case_study_link,
            'case_study_submitted' => !empty($candidate->case_study_submitted_file_path),
        ]);
    }

    public function submitPublicCaseStudy(Request $request, $token): JsonResponse
    {
        $candidate = RecruitmentCandidate::query()
            ->where('case_study_token', $token)
            ->firstOrFail();

        $payload = $request->validate([
            'password' => ['required', 'digits:6'],
            'submission' => ['required', 'file', 'mimes:pdf,docx,doc,zip,rar', 'max:15360'],
        ], [
            'submission.required' => 'Berkas penyelesaian studi kasus wajib diunggah.',
            'submission.mimes' => 'Format file tidak valid. File harus berupa: pdf, docx, doc, zip, atau rar.',
            'submission.max' => 'Ukuran berkas tidak boleh melebihi 15 MB.',
        ]);

        abort_unless(
            $candidate->case_study_password
                && Hash::check($payload['password'], $candidate->case_study_password),
            403,
            'PIN Case Study tidak valid.'
        );

        if (!empty($candidate->case_study_submitted_file_path)) {
            return response()->json([
                'message' => 'Jawaban case study Anda sudah pernah dikirimkan sebelumnya dan tidak dapat diubah.',
            ], 422);
        }

        $path = $request->file('submission')->store('recruitment-case-study-submissions', 'local');
        $oldPath = $candidate->case_study_submitted_file_path;

        $candidate->update([
            'case_study_submitted_file_path' => $path,
            'case_study_submitted_at' => now(),
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Penyelesaian studi kasus Anda berhasil diunggah.',
        ]);
    }

    public function sendPkbApprovalRequest(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $payload = $request->validate([
            'employee_niks' => ['required', 'array', 'min:1'],
            'employee_niks.*' => ['required', 'string', 'exists:m_karyawan,nik'],
            'previous_salary' => ['nullable', 'integer', 'min:0'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $previousSalary = $payload['previous_salary'] ?? $candidate->previous_salary;
        abort_unless($previousSalary !== null, 422, 'Gaji saat ini/perusahaan sebelumnya harus diisi.');

        $candidate->update([
            'previous_salary' => $previousSalary,
        ]);
        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            'pkb',
            $request->user(),
            null,
            ['source' => 'send_pkb_approval'],
        );

        $failedRecipients = [];

        foreach ($payload['employee_niks'] as $nik) {
            $exists = $candidate->pkbSigners()->where('employee_nik', $nik)->exists();
            if ($exists) {
                continue;
            }

            $signer = $candidate->pkbSigners()->create([
                'employee_nik' => $nik,
            ]);

            $employee = Karyawan::where('nik', $nik)->first();
            if (! $employee || ! $employee->no_hp) {
                $failedRecipients[] = $employee?->nama_karyawan ?? $nik;

                continue;
            }

            $signLinkLong = rtrim((string) config('app.frontend_url'), '/')."/public/pkb/sign-request/{$signer->id}";
            $signLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($signLinkLong, now()->addDays(7));
            $joinDateFormatted = $candidate->join_date ? \Carbon\Carbon::parse($candidate->join_date)->translatedFormat('d F Y') : '-';
            $vacancyTitle = $candidate->vacancy?->title ?? 'Umum';
            $lastCompany = $candidate->last_company ?? '-';
            $previousSalary = number_format($candidate->previous_salary, 0, ',', '.');
            $expectedSalary = number_format($candidate->expected_salary, 0, ',', '.');
            $offeredSalary = number_format($candidate->offered_salary, 0, ',', '.');

            $message = "Dear Bapak/Ibu *{$employee->nama_karyawan}*,\n\n".
                "HRD telah menugaskan Anda untuk meninjau dan menyetujui dokumen PKB (Persetujuan Kontrak Baru) untuk kandidat berikut:\n\n".
                "*Detail Kandidat:*\n".
                "• Nama Pelamar: *{$candidate->name}*\n".
                "• Posisi / Lowongan: *{$vacancyTitle}*\n".
                "• Perusahaan Terakhir: *{$lastCompany}*\n".
                "• Tanggal Mulai Kerja: *{$joinDateFormatted}*\n\n".
                "*Komparasi Finansial:*\n".
                "• Gaji Perusahaan Sebelumnya: *Rp {$previousSalary}*\n".
                "• Gaji yang Diharapkan: *Rp {$expectedSalary}*\n".
                "• Gaji yang Ditawarkan: *Rp {$offeredSalary}*\n\n".
                "Silakan tinjau kembali detail komparasi di atas dan lakukan tanda tangan persetujuan secara digital melalui tautan resmi berikut:\n".
                "👉 {$signLink}\n\n".
                "Terima kasih atas perhatian dan kerja samanya.";

            try {
                if (app(WhatsAppService::class)->sendMessage($employee->no_hp, $message)) {
                    $signer->update(['sent_at' => now()]);
                } else {
                    $failedRecipients[] = $employee->nama_karyawan;
                }
            } catch (\Exception $e) {
                $failedRecipients[] = $employee->nama_karyawan;
                Log::error("Gagal mengirim WhatsApp permintaan ttd PKB ke {$employee->no_hp}", ['error' => $e->getMessage()]);
            }
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Sent PKB Sign Requests)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => $failedRecipients === []
                ? 'Permintaan tanda tangan PKB berhasil dikirim melalui WhatsApp.'
                : 'Permintaan PKB dibuat, tetapi WhatsApp gagal dikirim ke: '.implode(', ', $failedRecipients).'.',
            'failed_recipients' => $failedRecipients,
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function resendPkbSignerWa(Request $request, RecruitmentCandidate $candidate, RecruitmentCandidatePkbSigner $signer): JsonResponse
    {
        $employee = Karyawan::where('nik', $signer->employee_nik)->first();
        abort_unless($employee && $employee->no_hp, 422, 'Nomor HP karyawan penyetuju tidak tersedia.');

        $signLinkLong = rtrim((string) config('app.frontend_url'), '/')."/public/pkb/sign-request/{$signer->id}";
        $signLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($signLinkLong, now()->addDays(7));
        $joinDateFormatted = $candidate->join_date ? \Carbon\Carbon::parse($candidate->join_date)->translatedFormat('d F Y') : '-';
        $vacancyTitle = $candidate->vacancy?->title ?? 'Umum';
        $lastCompany = $candidate->last_company ?? '-';
        $previousSalary = number_format($candidate->previous_salary, 0, ',', '.');
        $expectedSalary = number_format($candidate->expected_salary, 0, ',', '.');
        $offeredSalary = number_format($candidate->offered_salary, 0, ',', '.');

        $message = "Dear Bapak/Ibu *{$employee->nama_karyawan}*,\n\n".
            "HRD telah menugaskan Anda untuk meninjau dan menyetujui dokumen PKB (Persetujuan Kontrak Baru) untuk kandidat berikut:\n\n".
            "*Detail Kandidat:*\n".
            "• Nama Pelamar: *{$candidate->name}*\n".
            "• Posisi / Lowongan: *{$vacancyTitle}*\n".
            "• Perusahaan Terakhir: *{$lastCompany}*\n".
            "• Tanggal Mulai Kerja: *{$joinDateFormatted}*\n\n".
            "*Komparasi Finansial:*\n".
            "• Gaji Perusahaan Sebelumnya: *Rp {$previousSalary}*\n".
            "• Gaji yang Diharapkan: *Rp {$expectedSalary}*\n".
            "• Gaji yang Ditawarkan: *Rp {$offeredSalary}*\n\n".
            "Silakan tinjau kembali detail komparasi di atas dan lakukan tanda tangan persetujuan secara digital melalui tautan resmi berikut:\n".
            "👉 {$signLink}\n\n".
            "Terima kasih atas perhatian dan kerja samanya.";

        try {
            if (app(WhatsAppService::class)->sendMessage($employee->no_hp, $message)) {
                $signer->update(['sent_at' => now()]);

                return response()->json([
                    'message' => 'Permintaan tanda tangan PKB berhasil dikirim ulang melalui WhatsApp.',
                    'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Gagal mengirim ulang WhatsApp permintaan ttd PKB ke {$employee->no_hp}", ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Gagal mengirim ulang WhatsApp ke karyawan penyetuju.',
        ], 500);
    }

    public function submitPkbSignerSignature(Request $request, $id): JsonResponse
    {
        $signer = RecruitmentCandidatePkbSigner::findOrFail($id);

        $request->validate([
            'signature_data' => ['required', 'string'],
        ]);

        $signer->update([
            'signature_data' => $request->input('signature_data'),
            'signed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Persetujuan PKB berhasil ditandatangani.',
        ]);
    }

    public function sendOnboardingFormLink(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $token = Str::random(40);
        $password = (string) random_int(100000, 999999);

        $candidate->update([
            'onboarding_token' => $token,
            'onboarding_password' => $password,
            'onboarding_sent_at' => now(),
        ]);
        $candidate = app(RecruitmentStageService::class)->transition(
            $candidate,
            'hired',
            $request->user(),
            null,
            ['source' => 'send_onboarding_link'],
        );

        $onboardingLinkLong = rtrim((string) config('app.frontend_url'), '/')."/public/onboarding/{$token}";
        $onboardingLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($onboardingLinkLong, now()->addDays(3));

        try {
            Mail::to($candidate->email)->send(new CandidateOnboardingMail($candidate, $onboardingLink, $password));
        } catch (\Exception $e) {
            Log::error('Gagal mengirim email link onboarding', ['error' => $e->getMessage()]);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Sent Onboarding Form)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Tautan pengisian biodata onboarding berhasil dikirim ke kandidat.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function sendOnboardingWa(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->phone, 422, 'Nomor HP kandidat tidak tersedia.');
        abort_unless($candidate->onboarding_sent_at, 422, 'Harap kirim tautan onboarding via email terlebih dahulu.');

        $candidate->loadMissing(['vacancy', 'pic']);
        $picPhone = $candidate->pic ? $candidate->pic->no_hp : '-';
        $picName = $candidate->pic ? ($candidate->pic->nama_karyawan ?? $candidate->pic->name) : 'Tim HRD';

        $message = "Yth. Sdr/i. *{$candidate->name}*,\n\n".
                   "Selamat! Kami menginformasikan bahwa Anda dinyatakan lolos dan resmi diterima untuk bergabung, serta memasuki tahapan *Onboarding*.\n\n".
                   "Tautan pengisian biodata karyawan baru dan instruksi lengkap telah kami kirimkan ke email Anda: *{$candidate->email}*.\n\n".
                   "Jika Anda memiliki pertanyaan lebih lanjut, silakan hubungi PIC HRD Anda (*{$picName}*) di nomor *{$picPhone}*.\n\n".
                   "Hormat kami,\n".
                   "HRBP Team – Hompim Play\n\n".
                   "_*Catatan:* Mohon tidak membalas pesan ini secara langsung karena dikirim otomatis oleh sistem. Hubungi nomor PIC HRD di atas untuk pertanyaan lebih lanjut._";

        $success = false;
        try {
            $success = app(WhatsAppService::class)->sendMessage($candidate->phone, $message);
        } catch (\Exception $e) {
            Log::error('Gagal mengirim WA onboarding ke kandidat', ['error' => $e->getMessage()]);
        }

        if ($success) {
            $candidate->update([
                'onboarding_wa_sent_at' => now(),
            ]);

            app(HrdAuditLogService::class)->record(
                $request,
                'RecruitmentCandidate',
                'updated',
                "Candidate #{$candidate->id}: {$candidate->name} (Sent Onboarding WhatsApp Notification to Candidate)",
                app(HrdAuditLogService::class)->snapshot($candidate),
                $candidate,
                RecruitmentCandidate::class,
                $candidate->id
            );

            return response()->json([
                'message' => 'Notifikasi WhatsApp onboarding berhasil dikirim ke kandidat.',
                'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim notifikasi WhatsApp ke kandidat.',
        ], 500);
    }

    public function submitCandidateOnboarding(Request $request, $token): JsonResponse
    {
        $candidate = RecruitmentCandidate::where('onboarding_token', $token)->firstOrFail();

        if ($candidate->onboarding_completed_at) {
            abort(400, 'Formulir onboarding ini sudah pernah diselesaikan.');
        }

        $sentAt = Carbon::parse($candidate->onboarding_sent_at);
        if ($sentAt->addDays(3)->isPast()) {
            abort(400, 'Tautan onboarding ini sudah kedaluwarsa (maksimal 3 hari). Harap hubungi HRD.');
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (strtoupper($request->input('password')) !== strtoupper($candidate->onboarding_password)) {
            return response()->json(['message' => 'Kata sandi onboarding tidak valid.'], 422);
        }

        $validator = validator($request->all(), [
            'nama_karyawan' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'no_hp' => ['required', 'string', 'max:30'],
            'tanggal_lahir' => ['required', 'date'],
            'jenis_kelamin' => ['required', 'string', 'in:L,P,Laki-laki,Perempuan'],
            'alamat' => ['required', 'string'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'no_ktp' => ['required', 'string', 'max:30'],
            'agama' => ['required', 'string', 'max:30'],
            'kewarganegaraan' => ['required', 'string', 'max:50'],
            'status_pernikahan' => ['required', 'string', 'in:Belum Menikah,Menikah,Janda/Duda'],
            'golongan_darah' => ['required', 'string', 'in:A,B,AB,O'],
            'nama_pasangan' => ['nullable', 'required_if:status_pernikahan,Menikah', 'string', 'max:150'],
            'jumlah_anak' => ['nullable', 'required_if:status_pernikahan,Menikah', 'integer', 'min:0', 'max:20'],
            'children' => ['nullable', 'array', 'max:20'],
            'children.*' => ['required', 'string', 'max:150'],
            'no_npwp' => ['nullable', 'string', 'max:30'],
            'no_bpjs' => ['nullable', 'string', 'max:50'],
            'bank' => ['required', 'string', 'max:50'],
            'no_rekening' => ['required', 'string', 'max:50'],
            'pendidikan_terakhir' => ['required', 'string', 'max:50'],
            'nama_institusi' => ['required', 'string', 'max:150'],
            'jurusan' => ['required', 'string', 'max:100'],
            'nama_ayah' => ['required', 'string', 'max:150'],
            'nama_ibu' => ['required', 'string', 'max:150'],
            'kontak_darurat_nama' => ['required', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['required', 'string', 'max:50'],
            'kontak_darurat_no_hp' => ['required', 'string', 'max:30'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($request->input('status_pernikahan') !== 'Menikah') {
                return;
            }

            $expectedChildren = (int) $request->input('jumlah_anak', 0);
            $children = is_array($request->input('children')) ? $request->input('children') : [];

            if (count($children) !== $expectedChildren) {
                $validator->errors()->add(
                    'children',
                    'Jumlah nama anak yang diisi harus sesuai dengan jumlah anak.'
                );
            }
        });

        $payload = $validator->validate();
        $payload['jenis_kelamin'] = match ($payload['jenis_kelamin']) {
            'Laki-laki' => 'L',
            'Perempuan' => 'P',
            default => $payload['jenis_kelamin'],
        };

        if ($payload['status_pernikahan'] === 'Menikah') {
            $payload['children'] = collect($payload['children'] ?? [])
                ->map(fn ($name) => trim((string) $name))
                ->values()
                ->all();
            $payload['jumlah_anak'] = count($payload['children']);
        } else {
            $payload['nama_pasangan'] = null;
            $payload['jumlah_anak'] = 0;
            $payload['children'] = [];
        }

        foreach ([1, 2, 3] as $index) {
            $payload['nama_anak_'.$index] = $payload['children'][$index - 1] ?? null;
        }
        unset($payload['children']);

        $candidate->update([
            'onboarding_data' => $payload,
            'onboarding_completed_at' => now(),
            'onboarding_token' => null,
        ]);

        return response()->json([
            'message' => 'Formulir onboarding berhasil dikirim. Selamat bergabung!',
        ]);
    }

    public function importCandidateOnboarding(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->onboarding_completed_at !== null, 400, 'Kandidat belum menyelesaikan pengisian data onboarding.');
        abort_unless($candidate->employee_nik === null, 400, 'Kandidat ini sudah pernah diimpor ke data karyawan.');

        $payload = $request->validate([
            'nik' => ['required', 'string', 'max:50', 'unique:m_karyawan,nik'],
            'pin' => ['required', 'string', 'max:20'],
            'nama_karyawan' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'no_hp' => ['required', 'string', 'max:30'],
            'tanggal_lahir' => ['required', 'date'],
            'jenis_kelamin' => ['required', 'string', 'in:L,P'],
            'alamat' => ['required', 'string'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'no_ktp' => ['required', 'string', 'max:30'],
            'agama' => ['required', 'string', 'max:30'],
            'kewarganegaraan' => ['required', 'string', 'max:50'],
            'status_pernikahan' => ['required', 'string', 'in:Belum Menikah,Menikah,Janda/Duda'],
            'golongan_darah' => ['required', 'string', 'in:A,B,AB,O'],
            'nama_pasangan' => ['nullable', 'string', 'max:150'],
            'jumlah_anak' => ['nullable', 'integer', 'min:0', 'max:20'],
            'nama_anak_1' => ['nullable', 'string', 'max:150'],
            'nama_anak_2' => ['nullable', 'string', 'max:150'],
            'nama_anak_3' => ['nullable', 'string', 'max:150'],
            'no_npwp' => ['nullable', 'string', 'max:30'],
            'no_bpjs' => ['nullable', 'string', 'max:50'],
            'bank' => ['required', 'string', 'max:50'],
            'no_rekening' => ['required', 'string', 'max:50'],
            'pendidikan_terakhir' => ['required', 'string', 'max:50'],
            'nama_institusi' => ['required', 'string', 'max:150'],
            'jurusan' => ['required', 'string', 'max:100'],
            'nama_ayah' => ['required', 'string', 'max:150'],
            'nama_ibu' => ['required', 'string', 'max:150'],
            'kontak_darurat_nama' => ['required', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['required', 'string', 'max:50'],
            'kontak_darurat_no_hp' => ['required', 'string', 'max:30'],
            'status_karyawan' => ['required', 'string', 'max:50'],
            'join_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'jabatan' => ['required', 'string', 'max:100'],
            'posisi' => ['required', 'string', 'max:100'],
            'posisi_level' => ['nullable', 'string', 'max:50'],
            'posisi_title' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable', 'string', 'max:100'],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'atasan_langsung' => ['nullable', 'string', 'max:50'],
            'atasan_tidak_langsung' => ['nullable', 'string', 'max:50'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $employee = DB::transaction(function () use ($payload, $candidate): Karyawan {
            // Prevent storing contract end_date on the m_karyawan record
            $employeePayload = $payload;
            if (array_key_exists('end_date', $employeePayload)) {
                unset($employeePayload['end_date']);
            }

            // Force status_karyawan to 'AKTIF' for newly imported employees
            $employeePayload['status_karyawan'] = 'AKTIF';

            // Map supervisor NIKs to employee payload columns and resolve names when available
            if (! empty($payload['atasan_langsung'])) {
                $employeePayload['atasan_langsung_nik'] = $payload['atasan_langsung'];
                $super = Karyawan::where('nik', $payload['atasan_langsung'])->first();
                $employeePayload['nama_atasan_langsung'] = $super?->nama_karyawan;
            }
            if (! empty($payload['atasan_tidak_langsung'])) {
                $employeePayload['atasan_tidak_langsung_nik'] = $payload['atasan_tidak_langsung'];
                $super2 = Karyawan::where('nik', $payload['atasan_tidak_langsung'])->first();
                $employeePayload['atasan_tidak_langsung'] = $super2?->nama_karyawan;
            }

            $employee = Karyawan::create($employeePayload);

            // Create initial PKWT contract record in t_kontrak_karyawan
            $kontrakKe = ((int) DB::table('t_kontrak_karyawan')->where('nik', $employee->nik)->max('kontrak_ke')) + 1;

            // Compute duration in calendar months when both dates are provided
            $startDate = isset($payload['join_date']) && $payload['join_date'] ? Carbon::parse($payload['join_date']) : null;
            $endDate = isset($payload['end_date']) && $payload['end_date'] ? Carbon::parse($payload['end_date']) : null;
            $durasiBulan = null;
            if ($startDate && $endDate) {
                $yearDiff = $endDate->year - $startDate->year;
                $monthDiff = $endDate->month - $startDate->month;
                $dayAdd = $endDate->day >= $startDate->day ? 1 : 0;
                $durasiBulan = ($yearDiff * 12) + $monthDiff + $dayAdd;
                $durasiBulan = max(1, (int) $durasiBulan);
            }

            DB::table('t_kontrak_karyawan')->insert([
                'nik' => $employee->nik,
                'kontrak_ke' => $kontrakKe,
                'jenis_kontrak' => 'PKWT',
                'status_kontrak' => 'AKTIF',
                'start_date' => $payload['join_date'] ?? null,
                'end_date' => $payload['end_date'] ?? null,
                'durasi_bulan' => $durasiBulan,
                'keterangan' => 'Kontrak awal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $candidate->update([
                'employee_nik' => $employee->nik,
            ]);

            return $employee;
        });

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Imported to Employee with NIK {$employee->nik})",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        $hrdEmails = \App\Models\User::where('level', 2)->pluck('email')->filter()->toArray();
        if (empty($hrdEmails)) {
            $hrdEmails = ['hrd@biensi.co.id'];
        }

        try {
            Mail::to($hrdEmails)->send(new HrdNewEmployeeNotificationMail($candidate, $employee));
        } catch (\Exception $e) {
            Log::error('Failed sending HRD onboarding notification email', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Data onboarding kandidat berhasil diimpor ke data karyawan.',
            'employee' => $employee,
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function saveCandidateOnboardingData(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        abort_unless($candidate->onboarding_completed_at !== null, 400, 'Kandidat belum menyelesaikan pengisian data onboarding.');
        abort_unless($candidate->employee_nik === null, 400, 'Kandidat ini sudah pernah diimpor ke data karyawan.');

        $payload = $request->validate([
            'nama_karyawan' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'tanggal_lahir' => ['nullable', 'string'],
            'jenis_kelamin' => ['nullable', 'string'],
            'alamat' => ['nullable', 'string'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'no_ktp' => ['nullable', 'string', 'max:30'],
            'agama' => ['nullable', 'string', 'max:30'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'status_pernikahan' => ['nullable', 'string'],
            'golongan_darah' => ['nullable', 'string'],
            'nama_pasangan' => ['nullable', 'string', 'max:150'],
            'jumlah_anak' => ['nullable', 'integer', 'min:0', 'max:20'],
            'nama_anak_1' => ['nullable', 'string', 'max:150'],
            'nama_anak_2' => ['nullable', 'string', 'max:150'],
            'nama_anak_3' => ['nullable', 'string', 'max:150'],
            'no_npwp' => ['nullable', 'string', 'max:30'],
            'no_bpjs' => ['nullable', 'string', 'max:50'],
            'bank' => ['nullable', 'string', 'max:50'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50'],
            'nama_institusi' => ['nullable', 'string', 'max:150'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'nama_ayah' => ['nullable', 'string', 'max:150'],
            'nama_ibu' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_nama' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['nullable', 'string', 'max:50'],
            'kontak_darurat_no_hp' => ['nullable', 'string', 'max:30'],
            'nik' => ['nullable', 'string', 'max:50'],
            'pin' => ['nullable', 'string', 'max:20'],
            'status_karyawan' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'string'],
            'end_date' => ['nullable', 'string'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'posisi' => ['nullable', 'string', 'max:100'],
            'posisi_level' => ['nullable', 'string', 'max:50'],
            'posisi_title' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable', 'string', 'max:100'],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'atasan_langsung' => ['nullable', 'string', 'max:50'],
            'atasan_tidak_langsung' => ['nullable', 'string', 'max:50'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($candidate);

        $candidate->update([
            'onboarding_data' => $payload,
        ]);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentCandidate',
            'updated',
            "Candidate #{$candidate->id}: {$candidate->name} (Saved Onboarding Draft)",
            $beforeAudit,
            $candidate->fresh(),
            RecruitmentCandidate::class,
            $candidate->id
        );

        return response()->json([
            'message' => 'Draf data onboarding berhasil disimpan.',
            'candidate' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }

    public function getPublicReferenceCheck(Request $request, $token): JsonResponse
    {
        $validated = validator([
            'password' => $request->header('X-Reference-Password'),
        ], [
            'password' => ['required', 'digits:6'],
        ])->validate();

        $candidate = RecruitmentCandidate::with('vacancy')
            ->where('reference_check_token', $token)
            ->firstOrFail();

        abort_unless(
            $candidate->reference_check_password
                && Hash::check($validated['password'], $candidate->reference_check_password),
            403,
            'Password formulir tidak valid.'
        );

        return response()->json([
            'name' => $candidate->name,
            'vacancy_title' => $candidate->vacancy?->title ?? 'Umum',
            'required_reference_count' => $this->requiredReferenceCount($candidate),
            'reference_check_submitted' => !empty($candidate->reference_check_submitted_at),
        ]);
    }

    public function getPublicOffering(Request $request, $token): JsonResponse
    {
        $validated = validator([
            'password' => $request->header('X-Offering-Password'),
        ], [
            'password' => ['required', 'digits:6'],
        ])->validate();

        $candidate = RecruitmentCandidate::query()
            ->where('offering_letter_token', $token)
            ->firstOrFail();

        abort_unless(
            $candidate->offering_letter_password
                && Hash::check($validated['password'], $candidate->offering_letter_password),
            403,
            'PIN Offering Letter tidak valid.'
        );

        $pdfBase64 = '';
        if ($candidate->offering_letter_path && Storage::disk('local')->exists($candidate->offering_letter_path)) {
            $pdfBase64 = base64_encode(Storage::disk('local')->get($candidate->offering_letter_path));
        }

        return response()->json([
            'name' => $candidate->name,
            'vacancy_title' => $candidate->vacancy?->title ?? 'Umum',
            'expected_salary' => $candidate->expected_salary,
            'pdf_base64' => $pdfBase64,
        ]);
    }

    public function getPublicPkbSigner(Request $request, $id): JsonResponse
    {
        $signer = RecruitmentCandidatePkbSigner::with('employee')->findOrFail($id);
        $candidate = $signer->candidate;

        return response()->json([
            'signer_name' => $signer->employee?->nama_karyawan,
            'candidate_name' => $candidate->name,
            'vacancy_title' => $candidate->vacancy?->title ?? 'Umum',
            'last_company' => $candidate->last_company,
            'previous_salary' => $candidate->previous_salary,
            'expected_salary' => $candidate->expected_salary,
            'offered_salary' => $candidate->offered_salary,
            'join_date' => $candidate->join_date,
            'signed_at' => $signer->signed_at,
        ]);
    }

    public function getPublicOnboarding(Request $request, $token): JsonResponse
    {
        $candidate = RecruitmentCandidate::where('onboarding_token', $token)->firstOrFail();

        if ($candidate->onboarding_completed_at) {
            abort(400, 'Formulir onboarding ini sudah pernah diselesaikan.');
        }

        $sentAt = Carbon::parse($candidate->onboarding_sent_at);
        if ($sentAt->addDays(3)->isPast()) {
            abort(400, 'Tautan onboarding ini sudah kedaluwarsa (maksimal 3 hari). Harap hubungi HRD.');
        }

        return response()->json([
            'name' => $candidate->name,
            'vacancy_title' => $candidate->vacancy?->title ?? 'Umum',
            'email' => $candidate->email,
            'phone' => $candidate->phone,
        ]);
    }

    private function sendInterviewWhatsAppNotification(RecruitmentCandidate $candidate): void
    {
        $interviewer = \App\Models\Karyawan::where('nik', $candidate->interviewer_nik)->first();
        if ($interviewer && $interviewer->no_hp) {
            $formattedDate = $candidate->interview_date;
            try {
                $formattedDate = Carbon::parse($candidate->interview_date)->locale('id')->translatedFormat('l, d F Y');
            } catch (\Exception $e) {
            }

            $time = substr($candidate->interview_time, 0, 5);
            $type = $candidate->interview_type === 'online' ? 'Online (Tautan Meet)' : 'Offline (Lokasi Fisik)';
            $details = $candidate->interview_type === 'online' ? $candidate->interview_meet_link : $candidate->interview_location;
            $vacancyTitle = $candidate->vacancy?->title ?? 'Umum';

            $message = "Halo {$interviewer->nama_karyawan},\n\n".
                       "Akan ada jadwal interview rekrutmen baru:\n".
                       "- Kandidat: {$candidate->name}\n".
                       "- Posisi Dilamar: {$vacancyTitle}\n".
                       "- Tanggal: {$formattedDate}\n".
                       "- Waktu: {$time} WIB\n".
                       "- Tipe: {$type}\n".
                       "- Lokasi/Link: {$details}\n\n".
                       'Mohon konfirmasi kepada HRD jika pada tanggal tersebut tidak bisa melakukan interview. Terima kasih.';

            try {
                app(WhatsAppService::class)->sendMessage($interviewer->no_hp, $message);
            } catch (\Exception $e) {
                Log::error('Failed sending WA notification to interviewer', ['error' => $e->getMessage()]);
            }
        }
    }

    public function checkScheduleConflict(Request $request): JsonResponse
    {
        $request->validate([
            'candidate_id' => ['nullable', 'integer'],
            'round' => ['nullable', 'integer'],
            'interview_date' => ['required', 'date'],
            'interview_time' => ['required', 'string'],
            'interviewer_niks' => ['required', 'array'],
            'interviewer_niks.*' => ['required', 'string'],
        ]);

        $date = $request->input('interview_date');
        $timeInput = $request->input('interview_time'); // e.g. "14:00"
        $proposedTime = Carbon::parse($timeInput);

        $candidateId = $request->input('candidate_id');
        $round = $request->input('round');

        $selectedNiks = $request->input('interviewer_niks');
        $conflicts = [];

        // 1. Check in recruitment_candidates (HR Interviews or old interviews)
        $candidates = RecruitmentCandidate::query()
            ->where('interview_date', $date)
            ->get();

        foreach ($candidates as $c) {
            if (! $c->interview_time) {
                continue;
            }

            $existingTime = Carbon::parse($c->interview_time);
            $diffInMinutes = abs($proposedTime->diffInMinutes($existingTime));

            if ($diffInMinutes < 120) {
                // If it is the SAME candidate, it's a conflict
                if ($candidateId && $c->id == $candidateId) {
                    $conflicts[] = [
                        'nik' => 'CANDIDATE',
                        'interviewer_name' => 'Kandidat sendiri (Wawancara HR)',
                        'conflict_type' => 'Jadwal Wawancara HR Kandidat',
                        'candidate_name' => $c->name,
                        'time' => Carbon::parse($c->interview_time)->format('H:i'),
                    ];
                } else {
                    // Check if any interviewer overlaps
                    $niks = $this->parseInterviewerNiks($c->interviewer_nik);
                    $intersect = array_intersect($selectedNiks, $niks);
                    if (! empty($intersect)) {
                        foreach ($intersect as $nik) {
                            $employee = Karyawan::where('nik', $nik)->first();
                            $name = $employee ? $employee->nama_karyawan : $nik;
                            $conflicts[] = [
                                'nik' => $nik,
                                'interviewer_name' => $name,
                                'conflict_type' => 'HR Wawancara / Umum',
                                'candidate_name' => $c->name,
                                'time' => Carbon::parse($c->interview_time)->format('H:i'),
                            ];
                        }
                    }
                }
            }
        }

        // 2. Check in recruitment_candidate_user_interviews
        $userInterviews = RecruitmentCandidateUserInterview::query()
            ->where('interview_date', $date)
            ->get();

        foreach ($userInterviews as $ui) {
            // Skip checking the current round of the current candidate being updated/edited
            if ($candidateId && $ui->candidate_id == $candidateId && $round && $ui->round == $round) {
                continue;
            }
            if (! $ui->interview_time) {
                continue;
            }

            $existingTime = Carbon::parse($ui->interview_time);
            $diffInMinutes = abs($proposedTime->diffInMinutes($existingTime));

            if ($diffInMinutes < 120) {
                // If it is the SAME candidate, it's a conflict
                if ($candidateId && $ui->candidate_id == $candidateId) {
                    $conflicts[] = [
                        'nik' => 'CANDIDATE',
                        'interviewer_name' => "Kandidat sendiri (Wawancara User Tahap {$ui->round})",
                        'conflict_type' => "Jadwal Wawancara User Tahap {$ui->round} Kandidat",
                        'candidate_name' => $ui->candidate?->name ?? $c->name,
                        'time' => Carbon::parse($ui->interview_time)->format('H:i'),
                    ];
                } else {
                    // Check if any interviewer overlaps
                    $niks = $this->parseInterviewerNiks($ui->interviewer_nik);
                    $intersect = array_intersect($selectedNiks, $niks);
                    if (! empty($intersect)) {
                        foreach ($intersect as $nik) {
                            $employee = Karyawan::where('nik', $nik)->first();
                            $name = $employee ? $employee->nama_karyawan : $nik;
                            $conflicts[] = [
                                'nik' => $nik,
                                'interviewer_name' => $name,
                                'conflict_type' => "Wawancara User Tahap {$ui->round}",
                                'candidate_name' => $ui->candidate?->name ?? 'Kandidat lain',
                                'time' => Carbon::parse($ui->interview_time)->format('H:i'),
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'has_conflict' => ! empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    public function sendInterviewerEvaluationLink(Request $request, RecruitmentCandidate $candidate, $round, $evaluationId): JsonResponse
    {
        $evaluation = RecruitmentUserInterviewEvaluation::where('candidate_id', $candidate->id)
            ->where('round', $round)
            ->findOrFail($evaluationId);

        $userInterview = RecruitmentCandidateUserInterview::query()
            ->where('candidate_id', $candidate->id)
            ->where('round', $round)
            ->firstOrFail();
        abort_unless(
            $userInterview->completed_at,
            422,
            "Tandai Wawancara User Tahap {$round} sebagai selesai sebelum mengirim link evaluasi.",
        );

        $interviewer = $evaluation->interviewer;
        abort_unless($interviewer && $interviewer->no_hp, 400, 'Nomor HP pewawancara tidak ditemukan.');

        $vacancyTitle = $candidate->vacancy?->title ?? 'Umum';
        $formattedDate = $candidate->interview_date;
        try {
            if ($userInterview && $userInterview->interview_date) {
                $formattedDate = Carbon::parse($userInterview->interview_date)->locale('id')->translatedFormat('l, d F Y');
            }
        } catch (\Exception $e) {
        }

        // Construct the link using front-end app URL
        $frontendUrl = config('app.frontend_url', $request->getSchemeAndHttpHost());
        $longLink = rtrim($frontendUrl, '/').'/public/evaluation/'.$evaluation->token;
        $longCvLink = rtrim($frontendUrl, '/').'/public/evaluation/'.$evaluation->token.'/resume';
        
        $link = app(\App\Services\RecruitmentShortUrlService::class)->shorten($longLink);
        $cvLink = app(\App\Services\RecruitmentShortUrlService::class)->shorten($longCvLink);

        $message = "Halo Bapak/Ibu *{$interviewer->nama_karyawan}*,\n\n".
                   "Mohon berikan evaluasi hasil wawancara kandidat *{$candidate->name}* untuk posisi *{$vacancyTitle}* (Wawancara User Tahap *{$evaluation->round}*) yang sudah dilaksanakan pada *{$formattedDate}* melalui tautan berikut:\n\n".
                   "Tautan Evaluasi: {$link}\n\n".
                   'Terima kasih atas kerja samanya.';

        try {
            app(WhatsAppService::class)->sendMessage($interviewer->no_hp, $message);
            $evaluation->update(['sent_at' => now()]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengirim WhatsApp: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Tautan evaluasi berhasil dikirim ke WhatsApp pewawancara.',
        ]);
    }

    public function getPublicEvaluation(Request $request, $token): JsonResponse
    {
        $evaluation = RecruitmentUserInterviewEvaluation::where('token', $token)->firstOrFail();
        $candidate = $evaluation->candidate;
        $userInterview = RecruitmentCandidateUserInterview::query()
            ->where('candidate_id', $evaluation->candidate_id)
            ->where('round', $evaluation->round)
            ->firstOrFail();
        abort_unless($userInterview->completed_at, 403, 'Formulir evaluasi belum tersedia karena wawancara belum ditandai selesai.');

        return response()->json([
            'evaluation' => $evaluation,
            'candidate_name' => $candidate->name,
            'vacancy_title' => $candidate->vacancy?->title ?? 'Umum',
            'round' => $evaluation->round,
            'interviewer_name' => $evaluation->interviewer?->nama_karyawan ?? $evaluation->interviewer_nik,
            'submitted_at' => $evaluation->submitted_at,
        ]);
    }

    public function submitPublicEvaluation(Request $request, $token): JsonResponse
    {
        $evaluation = RecruitmentUserInterviewEvaluation::where('token', $token)->firstOrFail();

        $userInterview = RecruitmentCandidateUserInterview::query()
            ->where('candidate_id', $evaluation->candidate_id)
            ->where('round', $evaluation->round)
            ->firstOrFail();
        abort_unless($userInterview->completed_at, 403, 'Evaluasi belum dapat diisi karena wawancara belum ditandai selesai.');

        if ($evaluation->submitted_at) {
            return response()->json([
                'message' => 'Evaluasi ini sudah diselesaikan sebelumnya.',
            ], 400);
        }

        $payload = $request->validate([
            'interview_appearance' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_attitude' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_communication' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_motivation' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_initiative' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_teamwork' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_domain_experience' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_general_knowledge' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_growth_potential' => ['required', 'integer', 'min:1', 'max:4'],
            'interview_total_score' => ['required', 'integer'],
            'interview_evaluation_notes' => ['required', 'string'],
            'interview_recommendation' => ['required', 'string', 'in:disarankan,dipertimbangkan,tidak_disarankan'],
        ]);

        $evaluation->update(array_merge($payload, [
            'submitted_at' => now(),
        ]));

        return response()->json([
            'message' => 'Evaluasi berhasil disimpan. Terima kasih atas partisipasi Anda.',
        ]);
    }

    public function previewUserInterviewEvaluation(Request $request, $evaluationId): JsonResponse
    {
        $evaluation = RecruitmentUserInterviewEvaluation::findOrFail($evaluationId);
        $candidate = $evaluation->candidate;

        $aspects = [
            'Penampilan / Kerapihan' => $evaluation->interview_appearance,
            'Sikap / Kepribadian' => $evaluation->interview_attitude,
            'Kemampuan Komunikasi' => $evaluation->interview_communication,
            'Motivasi Kerja' => $evaluation->interview_motivation,
            'Inisiatif' => $evaluation->interview_initiative,
            'Kerjasama Tim' => $evaluation->interview_teamwork,
            'Keahlian Bidang / Pengalaman' => $evaluation->interview_domain_experience,
            'Pengetahuan Umum' => $evaluation->interview_general_knowledge,
            'Potensi Berkembang' => $evaluation->interview_growth_potential,
        ];

        $recLabel = '-';
        if ($evaluation->interview_recommendation === 'disarankan') {
            $recLabel = 'Disarankan';
        }
        if ($evaluation->interview_recommendation === 'dipertimbangkan') {
            $recLabel = 'Dipertimbangkan';
        }
        if ($evaluation->interview_recommendation === 'tidak_disarankan') {
            $recLabel = 'Tidak Disarankan';
        }

        $html = "<div style='font-family: sans-serif; padding: 25px; line-height: 1.5; color: #1e293b;'>";
        $html .= "<h2 style='text-align: center; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; color: #0f172a; margin-bottom: 20px;'>LAPORAN EVALUASI WAWANCARA USER</h2>";
        $html .= "<table style='width: 100%; margin-bottom: 20px; font-size: 14px;'>";
        $html .= "<tr><td style='width: 150px; font-weight: bold;'>Nama Kandidat:</td><td>{$candidate->name}</td></tr>";
        $html .= "<tr><td style='font-weight: bold;'>Posisi:</td><td>".($candidate->vacancy?->title ?? 'Umum').'</td></tr>';
        $html .= "<tr><td style='font-weight: bold;'>Wawancara Round:</td><td>Round #{$evaluation->round}</td></tr>";
        $html .= "<tr><td style='font-weight: bold;'>Pewawancara:</td><td>".($evaluation->interviewer?->nama_karyawan ?? $evaluation->interviewer_nik).' ('.$evaluation->interviewer_nik.')</td></tr>';
        $html .= "<tr><td style='font-weight: bold;'>Tanggal Pengisian:</td><td>".($evaluation->submitted_at ? $evaluation->submitted_at->format('d-m-Y H:i') : '-').' WIB</td></tr>';
        $html .= '</table>';

        $html .= "<h3 style='border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; color: #0f172a;'>Aspek Penilaian (Skor 1 - 4)</h3>";
        $html .= "<table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;'>";
        $html .= "<tr style='background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;'><th style='text-align: left; padding: 8px;'>Aspek</th><th style='text-align: center; padding: 8px; width: 80px;'>Skor</th></tr>";
        foreach ($aspects as $name => $score) {
            $html .= "<tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 8px;'>{$name}</td><td style='text-align: center; padding: 8px; font-weight: bold;'>{$score}</td></tr>";
        }
        $html .= '</table>';

        $html .= "<div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;'>";
        $html .= "<p style='margin: 0 0 5px 0; font-size: 14px;'><strong>Total Skor:</strong> <span style='font-size: 18px; color: #2563eb; font-weight: bold;'>{$evaluation->interview_total_score} / 36</span></p>";
        $html .= "<p style='margin: 0; font-size: 14px;'><strong>Rekomendasi:</strong> <span style='font-size: 16px; font-weight: bold; color: ".($evaluation->interview_recommendation === 'disarankan' ? '#10b981' : ($evaluation->interview_recommendation === 'dipertimbangkan' ? '#f59e0b' : '#ef4444')).";'>{$recLabel}</span></p>";
        $html .= '</div>';

        $html .= "<h3 style='border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; color: #0f172a;'>Catatan Evaluasi / Umpan Balik</h3>";
        $html .= "<div style='font-size: 13px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; white-space: pre-wrap; min-height: 80px;'>".e($evaluation->interview_evaluation_notes).'</div>';
        $html .= '</div>';

        return response()->json([
            'filename' => 'Evaluasi-Round-'.$evaluation->round.'-'.str($candidate->name)->slug().'.html',
            'mime_type' => 'text/html',
            'content_base64' => base64_encode($html),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function previewUserInterviewEvaluationRecap(RecruitmentCandidate $candidate, int $round): JsonResponse
    {
        $evaluations = $candidate->userInterviewEvaluations()
            ->with('interviewer')
            ->where('round', $round)
            ->whereNotNull('submitted_at')
            ->get();

        abort_if($evaluations->isEmpty(), 404, 'Belum ada evaluasi yang selesai pada round ini.');

        $aspects = [
            'interview_appearance' => 'Penampilan / Kerapihan',
            'interview_attitude' => 'Sikap / Kepribadian',
            'interview_communication' => 'Kemampuan Komunikasi',
            'interview_motivation' => 'Motivasi Kerja',
            'interview_initiative' => 'Inisiatif',
            'interview_teamwork' => 'Kerjasama Tim',
            'interview_domain_experience' => 'Keahlian Bidang / Pengalaman',
            'interview_general_knowledge' => 'Pengetahuan Umum',
            'interview_growth_potential' => 'Potensi Berkembang',
        ];
        $recommendationLabels = [
            'disarankan' => 'Disarankan',
            'dipertimbangkan' => 'Dipertimbangkan',
            'tidak_disarankan' => 'Tidak Disarankan',
        ];
        $recommendationCounts = $evaluations->countBy('interview_recommendation')->sortDesc();
        $consensus = $recommendationLabels[$recommendationCounts->keys()->first()] ?? '-';
        $averageTotal = number_format((float) $evaluations->avg('interview_total_score'), 2, ',', '.');

        $html = "<div style='font-family:Arial,sans-serif;padding:28px;color:#1e293b;line-height:1.5'>";
        $html .= "<h2 style='text-align:center;border-bottom:2px solid #2563eb;padding-bottom:12px;color:#0f172a'>REKAP EVALUASI WAWANCARA USER</h2>";
        $html .= "<table style='width:100%;font-size:14px;margin-bottom:20px'>";
        $html .= "<tr><td style='width:170px;font-weight:bold'>Nama Kandidat</td><td>".e($candidate->name).'</td></tr>';
        $html .= "<tr><td style='font-weight:bold'>Posisi</td><td>".e($candidate->vacancy?->title ?? 'Umum').'</td></tr>';
        $html .= "<tr><td style='font-weight:bold'>Round</td><td>{$round}</td></tr>";
        $html .= "<tr><td style='font-weight:bold'>Jumlah Evaluator</td><td>{$evaluations->count()} orang</td></tr>";
        $html .= '</table>';
        $html .= "<div style='display:flex;gap:12px;margin-bottom:22px'>";
        $html .= "<div style='flex:1;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:15px'><div style='font-size:12px;color:#64748b'>Rata-rata Total</div><div style='font-size:24px;font-weight:bold;color:#2563eb'>{$averageTotal} / 36</div></div>";
        $html .= "<div style='flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px'><div style='font-size:12px;color:#64748b'>Konsensus Rekomendasi</div><div style='font-size:18px;font-weight:bold;color:#0f172a'>".e($consensus).'</div></div>';
        $html .= '</div>';

        $html .= "<h3 style='color:#0f172a'>Rata-rata Nilai per Aspek</h3><table style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:26px'>";
        $html .= "<tr style='background:#f8fafc'><th style='text-align:left;padding:8px;border:1px solid #e2e8f0'>Aspek</th><th style='padding:8px;border:1px solid #e2e8f0;width:100px'>Rata-rata</th></tr>";
        foreach ($aspects as $field => $label) {
            $average = number_format((float) $evaluations->avg($field), 2, ',', '.');
            $html .= "<tr><td style='padding:8px;border:1px solid #e2e8f0'>".e($label)."</td><td style='text-align:center;padding:8px;border:1px solid #e2e8f0;font-weight:bold'>{$average} / 4</td></tr>";
        }
        $html .= '</table>';

        foreach ($evaluations as $index => $evaluation) {
            $interviewerName = $evaluation->interviewer?->nama_karyawan ?? $evaluation->interviewer_nik;
            $recommendation = $recommendationLabels[$evaluation->interview_recommendation] ?? '-';
            $html .= "<div style='border:1px solid #cbd5e1;border-radius:10px;margin-bottom:20px;overflow:hidden'>";
            $html .= "<div style='background:#f8fafc;padding:14px;border-bottom:1px solid #e2e8f0'><strong>Evaluator #".($index + 1).': '.e($interviewerName)."</strong><div style='font-size:12px;color:#64748b'>NIK: ".e($evaluation->interviewer_nik).'</div></div>';
            $html .= "<div style='padding:14px'><p><strong>Total:</strong> {$evaluation->interview_total_score} / 36 &nbsp; | &nbsp; <strong>Rekomendasi:</strong> ".e($recommendation).'</p>';
            $html .= "<table style='width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px'>";
            foreach ($aspects as $field => $label) {
                $html .= "<tr><td style='padding:6px;border-bottom:1px solid #f1f5f9'>".e($label)."</td><td style='padding:6px;text-align:center;border-bottom:1px solid #f1f5f9;font-weight:bold'>{$evaluation->{$field}} / 4</td></tr>";
            }
            $html .= "</table><div style='background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:12px'><strong>Catatan Evaluator:</strong><div style='margin-top:6px;white-space:pre-wrap'>".e($evaluation->interview_evaluation_notes).'</div></div></div></div>';
        }
        $html .= '</div>';

        return response()->json([
            'filename' => 'Rekap-Evaluasi-User-Round-'.$round.'-'.str($candidate->name)->slug().'.html',
            'mime_type' => 'text/html',
            'content_base64' => base64_encode($html),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function previewPkbApprovalRecap(RecruitmentCandidate $candidate): JsonResponse
    {
        $candidate->load(['vacancy', 'pkbSigners.employee']);
        abort_if($candidate->pkbSigners->isEmpty(), 404, 'Data persetujuan PKB belum tersedia.');

        $approvedCount = $candidate->pkbSigners->whereNotNull('signed_at')->count();
        $totalApprovers = $candidate->pkbSigners->count();
        $approvalStatus = $approvedCount === $totalApprovers ? 'Disetujui Seluruh Penyetuju' : "Disetujui {$approvedCount} dari {$totalApprovers} Penyetuju";
        $previousSalary = number_format((float) $candidate->previous_salary, 0, ',', '.');
        $expectedSalary = number_format((float) $candidate->expected_salary, 0, ',', '.');
        $offeredSalary = number_format((float) $candidate->offered_salary, 0, ',', '.');
        $joinDate = $candidate->join_date ? \Carbon\Carbon::parse($candidate->join_date)->translatedFormat('d F Y') : '-';

        $html = "<div style='font-family:Arial,sans-serif;padding:30px;color:#1e293b;line-height:1.5'>";
        $html .= "<h2 style='text-align:center;border-bottom:2px solid #2563eb;padding-bottom:12px;color:#0f172a'>DOKUMEN PERSETUJUAN PKB</h2>";
        $html .= "<div style='background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:22px;text-align:center'><strong style='color:#1d4ed8'>".e($approvalStatus).'</strong></div>';
        $html .= "<table style='width:100%;font-size:14px;margin-bottom:24px'>";
        $html .= "<tr><td style='width:190px;font-weight:bold;padding:4px'>Nama Kandidat</td><td>".e($candidate->name).'</td></tr>';
        $html .= "<tr><td style='font-weight:bold;padding:4px'>Posisi</td><td>".e($candidate->vacancy?->title ?? 'Umum').'</td></tr>';
        $html .= "<tr><td style='font-weight:bold;padding:4px'>Perusahaan Terakhir</td><td>".e($candidate->last_company ?? '-').'</td></tr>';
        $html .= "<tr><td style='font-weight:bold;padding:4px'>Gaji Terakhir (Last Salary)</td><td>Rp {$previousSalary}</td></tr>";
        $html .= "<tr><td style='font-weight:bold;padding:4px'>Ekspektasi Gaji</td><td>Rp {$expectedSalary}</td></tr>";
        $html .= "<tr><td style='font-weight:bold;padding:4px'>Gaji Ditawarkan (Offered Salary)</td><td>Rp {$offeredSalary}</td></tr>";
        $html .= "<tr><td style='font-weight:bold;padding:4px'>Tanggal Mulai Kerja (Join Date)</td><td>{$joinDate}</td></tr>";
        $html .= "<tr><td style='font-weight:bold;padding:4px'>Tanggal Dokumen</td><td>".now()->format('d-m-Y H:i').' WIB</td></tr>';
        $html .= '</table>';
        $html .= "<h3 style='color:#0f172a'>Daftar Persetujuan dan Tanda Tangan</h3>";

        foreach ($candidate->pkbSigners as $index => $signer) {
            $employeeName = $signer->employee?->nama_karyawan ?? $signer->employee_nik;
            $status = $signer->signed_at ? 'Disetujui' : 'Menunggu Persetujuan';
            $signedAt = $signer->signed_at ? Carbon::parse($signer->signed_at)->format('d-m-Y H:i').' WIB' : '-';
            $html .= "<div style='border:1px solid #cbd5e1;border-radius:9px;padding:14px;margin-bottom:14px'>";
            $html .= '<strong>Penyetuju #'.($index + 1).': '.e($employeeName).'</strong>';
            $html .= "<div style='font-size:12px;color:#64748b;margin-top:3px'>NIK: ".e($signer->employee_nik).'</div>';
            $html .= "<div style='margin-top:10px;font-size:13px'><strong>Status:</strong> ".e($status)." &nbsp; | &nbsp; <strong>Waktu:</strong> {$signedAt}</div>";
            if ($signer->signature_data && str_starts_with($signer->signature_data, 'data:image/')) {
                $html .= "<div style='margin-top:12px'><div style='font-size:11px;color:#64748b;margin-bottom:5px'>Tanda Tangan Elektronik</div><img src='".e($signer->signature_data)."' style='max-width:240px;max-height:90px;border:1px solid #e2e8f0;background:#fff;padding:5px'></div>";
            }
            $html .= '</div>';
        }

        $html .= "<p style='margin-top:24px;font-size:11px;color:#64748b'>Dokumen ini dibuat otomatis oleh sistem HRIS berdasarkan data persetujuan elektronik yang tersimpan.</p></div>";

        return response()->json([
            'filename' => 'Persetujuan-PKB-'.str($candidate->name)->slug().'.html',
            'mime_type' => 'text/html',
            'content_base64' => base64_encode($html),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function parseInterviewerNiks(?string $rawNiks): array
    {
        if (empty($rawNiks)) {
            return [];
        }
        // Check if JSON
        $decoded = json_decode($rawNiks, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // Check if comma-separated
        if (str_contains($rawNiks, ',')) {
            return array_map('trim', explode(',', $rawNiks));
        }

        return [trim($rawNiks)];
    }

    private function assertInterviewScheduleIsFuture(string $date, string $time, string $label): void
    {
        $scheduledAt = Carbon::parse(Carbon::parse($date)->toDateString().' '.$time);

        abort_if(
            $scheduledAt->lte(now()),
            422,
            "Jadwal {$label} tidak boleh menggunakan tanggal atau waktu yang sudah lewat.",
        );
    }

    private function requiredReferenceCount(RecruitmentCandidate $candidate): int
    {
        $positionTitle = mb_strtolower((string) $candidate->vacancy?->title);
        $leaderAndAbovePattern = '/\b(leader|supervisor|spv|assistant\s+manager|asst\.?\s+manager|manager|general\s+manager|gm)\b/u';

        return preg_match($leaderAndAbovePattern, $positionTitle) === 1 ? 3 : 2;
    }

    private function isManagerialReference(RecruitmentCandidate $candidate): bool
    {
        $positionTitle = mb_strtolower((string) ($candidate->vacancy?->position ?: $candidate->vacancy?->title));
        return preg_match('/\b(leader|supervisor|spv|assistant\s+manager|asst\.?\s+manager|manager|general\s+manager|gm|head|director|direktur)\b/u', $positionTitle) === 1;
    }

    private function getConflictReason(int $candidateId, int $round, string $date, string $time, array $selectedNiks): ?string
    {
        $proposedTime = Carbon::parse($time);

        // Check HR Candidates
        $candidates = RecruitmentCandidate::query()
            ->where('interview_date', $date)
            ->get();

        foreach ($candidates as $c) {
            if (! $c->interview_time) {
                continue;
            }
            $existingTime = Carbon::parse($c->interview_time);
            if (abs($proposedTime->diffInMinutes($existingTime)) < 120) {
                // Conflict if same candidate
                if ($c->id == $candidateId) {
                    return 'Jadwal bentrok! Kandidat sudah memiliki jadwal Wawancara HR pada jam '.Carbon::parse($c->interview_time)->format('H:i').' WIB.';
                }
                // Conflict if any interviewer overlaps
                $niks = $this->parseInterviewerNiks($c->interviewer_nik);
                $intersect = array_intersect($selectedNiks, $niks);
                if (! empty($intersect)) {
                    $overlapNik = reset($intersect);
                    $employee = Karyawan::where('nik', $overlapNik)->first();
                    $name = $employee ? $employee->nama_karyawan : $overlapNik;
                    return 'Jadwal bentrok! Pewawancara ('.$name.') sudah memiliki jadwal Wawancara HR pada jam '.Carbon::parse($c->interview_time)->format('H:i').' WIB.';
                }
            }
        }

        // Check User Interviews
        $userInterviews = RecruitmentCandidateUserInterview::query()
            ->where('interview_date', $date)
            ->get();

        foreach ($userInterviews as $ui) {
            if ($ui->candidate_id == $candidateId && $ui->round == $round) {
                continue;
            }
            if (! $ui->interview_time) {
                continue;
            }
            $existingTime = Carbon::parse($ui->interview_time);
            if (abs($proposedTime->diffInMinutes($existingTime)) < 120) {
                // Conflict if same candidate
                if ($ui->candidate_id == $candidateId) {
                    return 'Jadwal bentrok! Kandidat sudah memiliki jadwal Wawancara User Tahap '.$ui->round.' pada jam '.Carbon::parse($ui->interview_time)->format('H:i').' WIB.';
                }
                // Conflict if any interviewer overlaps
                $niks = $this->parseInterviewerNiks($ui->interviewer_nik);
                $intersect = array_intersect($selectedNiks, $niks);
                if (! empty($intersect)) {
                    $overlapNik = reset($intersect);
                    $employee = Karyawan::where('nik', $overlapNik)->first();
                    $name = $employee ? $employee->nama_karyawan : $overlapNik;
                    return 'Jadwal bentrok! Pewawancara ('.$name.') sudah memiliki jadwal Wawancara User Tahap '.$ui->round.' pada jam '.Carbon::parse($ui->interview_time)->format('H:i').' WIB.';
                }
            }
        }

        return null;
    }

    private function hasConflict(int $candidateId, int $round, string $date, string $time, array $selectedNiks): bool
    {
        return $this->getConflictReason($candidateId, $round, $date, $time, $selectedNiks) !== null;
    }

    public function getPublicResumeByEvaluationToken(Request $request, $token): JsonResponse
    {
        $payload = $request->validate([
            'password' => ['required', 'string'],
        ]);

        abort_unless($payload['password'] === '123456', 403, 'Password CV tidak valid.');

        $evaluation = RecruitmentUserInterviewEvaluation::where('token', $token)->firstOrFail();
        $candidate = $evaluation->candidate;

        abort_unless($candidate->resume_path && Storage::disk('local')->exists($candidate->resume_path), 404, 'File CV tidak ditemukan.');

        return response()->json([
            'filename' => 'Resume-'.str($candidate->name)->slug().'.pdf',
            'mime_type' => 'application/pdf',
            'content_base64' => base64_encode(Storage::disk('local')->get($candidate->resume_path)),
        ]);
    }

    public function sendUserInterviewCandidateWa(Request $request, RecruitmentCandidate $candidate, $round): JsonResponse
    {
        $userInterview = RecruitmentCandidateUserInterview::where('candidate_id', $candidate->id)
            ->where('round', $round)
            ->firstOrFail();

        abort_unless($candidate->phone, 400, 'Nomor HP kandidat tidak ditemukan.');

        $formattedDate = $userInterview->interview_date 
            ? Carbon::parse($userInterview->interview_date)->locale('id')->translatedFormat('l, d F Y')
            : 'Belum ditentukan';
        $time = $userInterview->interview_time 
            ? substr($userInterview->interview_time, 0, 5) 
            : 'Belum ditentukan';
        $type = $userInterview->interview_type === 'online' ? 'Online (Tautan Meet)' : 'Offline (Lokasi Fisik)';
        $details = $userInterview->interview_type === 'online' ? $userInterview->interview_meet_link : $userInterview->interview_location;
        $candidate->loadMissing('pic');
        $picPhone = $candidate->pic ? $candidate->pic->no_hp : '-';
        $picName = $candidate->pic ? ($candidate->pic->nama_karyawan ?? $candidate->pic->name) : 'Tim HRD';

        $waMessage = "Yth. Sdr/i. *{$candidate->name}*,\n\n".
                     "Selamat! Kami menginformasikan bahwa Anda dinyatakan lolos ke tahapan selanjutnya, yaitu *Wawancara User Tahap {$userInterview->round}*.\n\n".
                     "Rincian lengkap mengenai hari, tanggal, waktu, serta tautan/lokasi wawancara telah kami kirimkan ke email Anda: *{$candidate->email}*.\n\n".
                     "Jika Anda memiliki pertanyaan lebih lanjut, silakan hubungi PIC HRD Anda (*{$picName}*) di nomor *{$picPhone}*.\n\n".
                     "Hormat kami,\n".
                     "HRBP Team – Hompim Play\n\n".
                     "_*Catatan:* Mohon tidak membalas pesan ini secara langsung karena dikirim otomatis oleh sistem. Hubungi nomor PIC HRD di atas untuk konfirmasi._";

        try {
            app(WhatsAppService::class)->sendMessage($candidate->phone, $waMessage);
            $userInterview->update(['wa_sent_at' => now()]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengirim WhatsApp: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Tautan wawancara berhasil dikirim ke WhatsApp kandidat.',
            'data' => $candidate->load(['vacancy', 'interviewer', 'userInterviews.interviewer', 'references', 'pkbSigners.employee']),
        ]);
    }
}
