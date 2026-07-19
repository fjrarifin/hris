<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentVacancy;
use App\Services\RecruitmentStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

class PublicCareerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = RecruitmentVacancy::query()->publiclyVisible();
        foreach (['division', 'department', 'unit', 'position'] as $field) {
            $query->when($filters[$field] ?? null, fn ($q, $value) => $q->where($field, $value));
        }
        $query->when($filters['search'] ?? null, function ($q, string $search): void {
            $term = '%'.addcslashes($search, '%_').'%' ;
            $q->where(fn ($nested) => $nested->where('title', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('location', 'like', $term)
                ->orWhere('department', 'like', $term));
        });

        $paginator = $query->orderByDesc('published_at')->orderByDesc('id')->paginate(12);
        $paginator->through(fn (RecruitmentVacancy $vacancy) => $this->resource($vacancy, false));

        $visible = RecruitmentVacancy::query()->publiclyVisible();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'divisions' => (clone $visible)->whereNotNull('division')->distinct()->orderBy('division')->pluck('division'),
                'departments' => (clone $visible)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
                'units' => (clone $visible)->whereNotNull('unit')->distinct()->orderBy('unit')->pluck('unit'),
                'positions' => (clone $visible)->whereNotNull('position')->distinct()->orderBy('position')->pluck('position'),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $vacancy = RecruitmentVacancy::query()->publiclyVisible()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $this->resource($vacancy, true)]);
    }

    public function apply(Request $request, string $slug): JsonResponse
    {
        $vacancy = RecruitmentVacancy::query()->publiclyVisible()->where('slug', $slug)->firstOrFail();
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'phone' => $this->normalizePhone((string) $request->input('phone')),
        ]);

        $payload = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:100'],
            'phone' => ['required', 'regex:/^\+62[1-9][0-9]{7,12}$/'],
            'marital_status' => ['required', 'in:Belum Menikah,Menikah,Duda / Janda'],
            'known_person' => ['nullable', 'string', 'max:150'],
            'last_company' => ['nullable', 'string', 'max:255'],
            'education_level' => ['required', 'in:SMK,SMA,D3,D4,S1'],
            'education_major' => ['required', 'string', 'max:150'],
            'previous_salary' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expected_salary' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'referred_from' => ['required', 'in:LinkedIn,JobStreet,Indeed,Instagram,Website Resmi,Lainnya'],
            'resume' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'website' => ['nullable', 'max:0'],
        ], [
            'phone.regex' => 'Nomor telepon Indonesia tidak valid.',
            'resume.mimes' => 'CV harus berupa file PDF.',
            'resume.max' => 'Ukuran CV maksimal 5 MB.',
            'website.max' => 'Lamaran tidak dapat diproses.',
        ]);

        $duplicate = RecruitmentCandidate::query()
            ->where('vacancy_id', $vacancy->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [$payload['email']])->orWhere('phone', $payload['phone']))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['email' => 'Lamaran untuk lowongan ini sudah pernah diterima.']);
        }

        $path = $request->file('resume')->store('recruitment-resumes/public', 'local');
        try {
            $candidate = DB::transaction(function () use ($payload, $vacancy, $path): RecruitmentCandidate {
                $candidate = RecruitmentCandidate::query()->create([
                    'vacancy_id' => $vacancy->id,
                    'name' => trim($payload['name']),
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                    'marital_status' => $payload['marital_status'],
                    'known_person' => $payload['known_person'] ?? null,
                    'last_company' => $payload['last_company'] ?? null,
                    'education_level' => $payload['education_level'],
                    'education_major' => trim($payload['education_major']),
                    'previous_salary' => $payload['previous_salary'],
                    'expected_salary' => $payload['expected_salary'],
                    'referred_from' => $payload['referred_from'],
                    'resume_path' => $path,
                    'status' => 'applied',
                    'notes' => 'Lamaran dari web career public.',
                ]);
                if (Schema::hasTable('recruitment_candidate_stage_histories')) {
                    app(RecruitmentStageService::class)->recordInitial($candidate);
                }
                return $candidate;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($candidate->email)->send(new \App\Mail\CandidateAppliedMail($candidate));
        } catch (\Throwable $mailException) {
            \Illuminate\Support\Facades\Log::error('Failed to send CandidateAppliedMail to ' . $candidate->email . ': ' . $mailException->getMessage());
        }

        try {
            app(\App\Services\RecruitmentStageService::class)->notifySupervisorOfChange($candidate, 'applied');
        } catch (\Throwable $err) {
            \Illuminate\Support\Facades\Log::error('Failed to notify supervisor on apply: ' . $err->getMessage());
        }

        return response()->json([
            'message' => 'Lamaran berhasil dikirim. Tim recruitment kami akan menghubungi kandidat yang sesuai.',
            'application_id' => $candidate->id,
        ], 201);
    }

    public function sitemap(): Response
    {
        $site = rtrim((string) config('app.career_frontend_url', 'http://localhost:5174'), '/');
        $urls = RecruitmentVacancy::query()->publiclyVisible()->orderByDesc('updated_at')->get(['slug', 'updated_at']);
        $escape = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $items = '<url><loc>'.$escape($site.'/').'</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>';
        $items .= '<url><loc>'.$escape($site.'/jobs').'</loc><changefreq>daily</changefreq><priority>0.9</priority></url>';
        foreach ($urls as $vacancy) {
            $items .= '<url><loc>'.$escape($site.'/jobs/'.$vacancy->slug).'</loc><lastmod>'.$vacancy->updated_at->toAtomString().'</lastmod><changefreq>daily</changefreq><priority>0.8</priority></url>';
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$items.'</urlset>', 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $backend = rtrim((string) config('app.url'), '/');

        return response("User-agent: *\nAllow: /\nDisallow: /jobs?\nSitemap: {$backend}/api/public/careers/sitemap.xml\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
        if (str_starts_with($phone, '08')) {
            return '+62'.substr($phone, 1);
        }
        if (str_starts_with($phone, '628')) {
            return '+'.$phone;
        }
        return $phone;
    }

    private function resource(RecruitmentVacancy $vacancy, bool $detail): array
    {
        $data = [
            'slug' => $vacancy->slug,
            'title' => $vacancy->title,
            'division' => $vacancy->division,
            'department' => $vacancy->department,
            'unit' => $vacancy->unit,
            'position' => $vacancy->position,
            'employment_type' => $vacancy->employment_type,
            'workplace_type' => $vacancy->workplace_type,
            'location' => $vacancy->location,
            'description' => $vacancy->description,
            'published_at' => $vacancy->published_at?->toIso8601String(),
            'application_deadline' => $vacancy->application_deadline?->toDateString(),
        ];

        return $detail ? $data + [
            'responsibilities' => $vacancy->responsibilities,
            'requirements' => $vacancy->requirements,
            'benefits' => $vacancy->benefits,
        ] : $data;
    }
}
