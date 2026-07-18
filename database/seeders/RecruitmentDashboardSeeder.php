<?php

namespace Database\Seeders;

use App\Models\HrdAuditLog;
use App\Models\Karyawan;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidatePkbSigner;
use App\Models\RecruitmentCandidateReference;
use App\Models\RecruitmentCandidateStageHistory;
use App\Models\RecruitmentCandidateUserInterview;
use App\Models\RecruitmentRequest;
use App\Models\RecruitmentUserInterviewEvaluation;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RecruitmentDashboardSeeder extends Seeder
{
    private const MARKER = '[RECRUITMENT_DASHBOARD_DEMO_V1]';

    private const EMAIL_DOMAIN = 'recruitment-dashboard.demo';

    private const DOCUMENT_DIRECTORY = 'recruitment-dashboard-demo';

    private const PIPELINE = [
        'applied',
        'screening',
        'interview_hr',
        'case_study',
        'interview_user',
        'reference_check',
        'offering',
        'pkb',
        'hired',
    ];

    /** @var array<string, string> */
    private array $documents = [];

    /** @var array<int, string> */
    private array $employeeNiks = [];

    private ?int $actorUserId = null;

    public function run(): void
    {
        $this->documents = $this->writeDemoDocuments();
        $this->employeeNiks = $this->resolveEmployeeNiks();
        $this->actorUserId = User::query()->orderBy('id')->value('id');

        DB::transaction(function (): void {
            $this->removePreviousDemoData();

            $vacancies = $this->createVacancies();
            $this->createRecruitmentRequests($vacancies);
            $this->createCandidates($vacancies);
        });

        $this->command?->info('Recruitment dashboard demo berhasil dibuat: 7 lowongan dan 54 kandidat dengan workflow lengkap.');
        $this->command?->warn('PIN demo public link: 123456 (reference, case study, offering) dan 654321 (onboarding).');
    }

    private function removePreviousDemoData(): void
    {
        $candidateIds = RecruitmentCandidate::query()
            ->where('email', 'like', '%@'.self::EMAIL_DOMAIN)
            ->pluck('id');

        if ($candidateIds->isNotEmpty()) {
            HrdAuditLog::query()
                ->where('subject_type', RecruitmentCandidate::class)
                ->whereIn('subject_id', $candidateIds->map(fn ($id) => (string) $id))
                ->delete();

            RecruitmentCandidate::query()->whereIn('id', $candidateIds)->delete();
        }

        RecruitmentRequest::query()
            ->where('description', 'like', self::MARKER.'%')
            ->delete();

        RecruitmentVacancy::query()
            ->where('description', 'like', self::MARKER.'%')
            ->delete();
    }

    /**
     * @return array<int, RecruitmentVacancy>
     */
    private function createVacancies(): array
    {
        $definitions = [
            ['IT Programmer', 'Information Technology', 'Head Office', 'open', 21, 3, 'Laravel, Vue.js, REST API, SQL, dan pengembangan aplikasi internal.'],
            ['Staff HRBP', 'Human Resources', 'Head Office', 'open', 18, 2, 'Recruitment, employee relations, administrasi HR, dan komunikasi organisasi.'],
            ['Supervisor Store', 'Operations', 'Bandung', 'open', 26, 2, 'Kepemimpinan operasional toko, target penjualan, dan pengelolaan shift.'],
            ['Finance Analyst', 'Finance', 'Head Office', 'open', 15, 2, 'Budgeting, financial modelling, rekonsiliasi, dan reporting management.'],
            ['Project Engineer', 'Project', 'Jakarta', 'open', 12, 3, 'Koordinasi proyek, estimasi, drawing, pengawasan vendor, dan laporan progres.'],
            ['Digital Marketing Specialist', 'Marketing', 'Head Office', 'open', 9, 2, 'Performance marketing, content planning, analytics, dan campaign optimization.'],
            ['Customer Service', 'Commercial', 'Surabaya', 'closed', 28, 2, 'Pelayanan pelanggan, complaint handling, komunikasi, dan administrasi penjualan.'],
        ];

        $vacancies = [];
        foreach ($definitions as $index => [$title, $department, $unit, $status, $ageDays, $target, $description]) {
            $vacancy = RecruitmentVacancy::query()->create([
                'title' => $title,
                'department' => $department,
                'unit' => $unit,
                'description' => self::MARKER."\n{$description}",
                'status' => $status,
            ]);
            $createdAt = now()->subDays($ageDays)->setTime(9 + ($index % 3), 0);
            $vacancy->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
            $vacancy->setAttribute('demo_target', $target);
            $vacancies[] = $vacancy;
        }

        return $vacancies;
    }

    /**
     * @param  array<int, RecruitmentVacancy>  $vacancies
     */
    private function createRecruitmentRequests(array $vacancies): void
    {
        foreach ($vacancies as $index => $vacancy) {
            $request = RecruitmentRequest::query()->create([
                'requester_nik' => $this->employeeNiks[($index + 2) % count($this->employeeNiks)],
                'title' => 'Kebutuhan '.$vacancy->title,
                'department' => $vacancy->department,
                'unit' => $vacancy->unit,
                'quantity' => (int) $vacancy->getAttribute('demo_target'),
                'description' => self::MARKER.' Kebutuhan headcount untuk data dashboard recruitment.',
                'status' => 'approved',
                'vacancy_id' => $vacancy->id,
                'hrd_notes' => 'Disetujui untuk pemenuhan sesuai manpower planning.',
            ]);
            $createdAt = Carbon::parse($vacancy->created_at)->addDay();
            $request->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt->copy()->addHours(5)])->saveQuietly();
        }

        RecruitmentRequest::query()->create([
            'requester_nik' => $this->employeeNiks[0],
            'title' => 'Graphic Designer Tambahan',
            'department' => 'Marketing',
            'unit' => 'Head Office',
            'quantity' => 1,
            'description' => self::MARKER.' Contoh permintaan yang masih menunggu persetujuan.',
            'status' => 'pending',
            'hrd_notes' => null,
        ]);
    }

    /**
     * @param  array<int, RecruitmentVacancy>  $vacancies
     */
    private function createCandidates(array $vacancies): void
    {
        $statuses = [
            ...array_fill(0, 5, 'applied'),
            ...array_fill(0, 5, 'screening'),
            ...array_fill(0, 6, 'interview_hr'),
            ...array_fill(0, 6, 'case_study'),
            ...array_fill(0, 7, 'interview_user'),
            ...array_fill(0, 6, 'reference_check'),
            ...array_fill(0, 6, 'offering'),
            ...array_fill(0, 5, 'pkb'),
            ...array_fill(0, 5, 'hired'),
            ...array_fill(0, 3, 'rejected'),
        ];

        $names = [
            'Aditya Pranata', 'Alya Maharani', 'Andika Saputra', 'Anisa Rahmawati', 'Ardiansyah Putra',
            'Bella Novitasari', 'Bima Kurniawan', 'Cahya Permata', 'Citra Lestari', 'Daffa Ramadhan',
            'Deni Firmansyah', 'Dewi Kartika', 'Dimas Prakoso', 'Eka Wulandari', 'Fahmi Akbar', 'Farah Nabila',
            'Fikri Maulana', 'Gina Apriliani', 'Gilang Ramadhan', 'Hana Safitri', 'Hendra Gunawan', 'Intan Puspita',
            'Irfan Setiawan', 'Jasmine Putri', 'Kevin Alamsyah', 'Larasati Ayu', 'Lukman Hakim', 'Maya Salsabila',
            'Muhammad Rizky', 'Nadia Oktaviani', 'Naufal Hidayat', 'Nisa Aulia', 'Putra Mahendra', 'Rahma Fitriani',
            'Raka Pratama', 'Rani Kusumawati', 'Reza Kurnia', 'Rifqi Fauzan', 'Salma Nuraini', 'Satria Nugroho',
            'Shinta Maharani', 'Taufik Hidayat', 'Tiara Anindita', 'Vina Melati', 'Wahyu Firmansyah', 'Yasmin Azzahra',
            'Yoga Prasetyo', 'Zahra Ramadhani', 'Bagas Pamungkas', 'Desi Anggraini', 'Erwin Kurnia', 'Fitri Handayani',
            'Guntur Prabowo', 'Helmi Fadillah',
        ];

        $sources = ['LinkedIn', 'JobStreet', 'Website Karier', 'Referensi Karyawan', 'Instagram', 'Kalibrr', 'Campus Hiring'];
        $education = [
            ['S1', 'Teknik Informatika'], ['S1', 'Psikologi'], ['D3', 'Manajemen'],
            ['S1', 'Akuntansi'], ['SMK', 'Administrasi Perkantoran'], ['S1', 'Teknik Sipil'],
            ['S1', 'Ilmu Komunikasi'],
        ];

        foreach ($statuses as $index => $status) {
            $vacancy = $vacancies[$index % count($vacancies)];
            $pipelineIndex = $status === 'rejected'
                ? 1 + (($index - 51) * 2)
                : array_search($status, self::PIPELINE, true);
            $createdDaysAgo = min(28, max($pipelineIndex + 2, 2 + (($index * 7 + $pipelineIndex * 3) % 27)));
            $createdAt = now()->subDays($createdDaysAgo)->setTime(8 + ($index % 9), ($index * 7) % 60);
            $emailSlug = strtolower(str_replace(' ', '.', $names[$index]));
            [$educationLevel, $educationMajor] = $education[$index % count($education)];

            $candidate = RecruitmentCandidate::query()->create([
                'vacancy_id' => $vacancy->id,
                'name' => $names[$index],
                'email' => $emailSlug.'@'.self::EMAIL_DOMAIN,
                'phone' => '0812'.str_pad((string) (70000000 + $index), 8, '0', STR_PAD_LEFT),
                'resume_path' => $index % 13 === 0 ? null : $this->documents['resume'],
                'status' => $status,
                'notes' => self::MARKER.' Kandidat demo untuk pengujian dashboard dan pipeline recruitment.',
                'last_company' => ['PT Maju Bersama', 'CV Karya Utama', 'PT Nusantara Digital', 'PT Sentosa Abadi'][$index % 4],
                'expected_salary' => 4500000 + (($index % 8) * 750000),
                'offered_salary' => $this->hasReached($status, $pipelineIndex, 'offering') ? 5000000 + (($index % 8) * 700000) : null,
                'join_date' => in_array($status, ['offering', 'pkb', 'hired'], true) ? now()->addDays(5 + ($index % 25))->toDateString() : null,
                'previous_salary' => 3800000 + (($index % 7) * 600000),
                'education_level' => $educationLevel,
                'education_major' => $educationMajor,
                'marital_status' => $index % 3 === 0 ? 'Menikah' : 'Belum Menikah',
                'known_person' => $index % 9 === 0 ? 'Referensi internal tersedia' : 'Tidak ada',
                'referred_from' => $sources[$index % count($sources)],
                'pic_nik' => $this->employeeNiks[$index % min(3, count($this->employeeNiks))],
            ]);

            $timeline = $this->createStageHistory($candidate, $status, $pipelineIndex, $createdAt, $index);
            $this->populateWorkflow($candidate, $status, $pipelineIndex, $index, $timeline);

            $lastEnteredAt = collect($timeline)->last();
            $inactiveAt = now()->subDays(4 + ($index % 3));
            $updatedAt = $index % 8 === 0 && $inactiveAt->gte($lastEnteredAt)
                ? $inactiveAt
                : Carbon::parse($lastEnteredAt)->addHours(1 + ($index % 5))->min(now());

            $candidate->forceFill(['created_at' => $createdAt, 'updated_at' => $updatedAt])->saveQuietly();
            $this->createAuditLog($candidate, $updatedAt, $index);
        }
    }

    /**
     * @return array<string, Carbon>
     */
    private function createStageHistory(
        RecruitmentCandidate $candidate,
        string $status,
        int $reachedIndex,
        Carbon $createdAt,
        int $candidateIndex,
    ): array {
        $visited = array_slice(self::PIPELINE, 0, $reachedIndex + 1);
        if ($status === 'rejected') {
            $visited[] = 'rejected';
        }

        // Sisakan waktu tinggal pada tahap aktif yang bervariasi (termasuk kandidat stagnan > 3 hari).
        $currentDwellHours = 12 + (($candidateIndex * 37) % 160);
        $availableHours = max(4, $createdAt->diffInHours(now()) - $currentDwellHours);
        $closedStageCount = max(1, count($visited) - 1);
        $hoursPerStage = max(3, (int) floor($availableHours / $closedStageCount));
        $enteredAt = $createdAt->copy();
        $timeline = [];

        foreach ($visited as $stageIndex => $stage) {
            $timeline[$stage] = $enteredAt->copy();
            $isCurrent = $stageIndex === count($visited) - 1;
            $exitedAt = $isCurrent
                ? null
                : $enteredAt->copy()->addHours($hoursPerStage + (($candidateIndex + $stageIndex) % 5));

            if ($exitedAt?->gt(now())) {
                $exitedAt = now()->copy()->subHour();
            }

            RecruitmentCandidateStageHistory::query()->create([
                'candidate_id' => $candidate->id,
                'stage' => $stage,
                'entered_at' => $enteredAt,
                'exited_at' => $exitedAt,
                'actor_user_id' => $this->actorUserId,
                'reason' => $stage === 'rejected' ? 'Tidak dilanjutkan berdasarkan hasil evaluasi tahap sebelumnya.' : null,
                'metadata' => [
                    'source' => 'recruitment_dashboard_seeder',
                    'demo' => true,
                    'sequence' => $stageIndex + 1,
                ],
            ]);

            if ($exitedAt) {
                $enteredAt = $exitedAt->copy();
            }
        }

        return $timeline;
    }

    /**
     * @param  array<string, Carbon>  $timeline
     */
    private function populateWorkflow(
        RecruitmentCandidate $candidate,
        string $status,
        int $reachedIndex,
        int $index,
        array $timeline,
    ): void {
        $updates = [];

        if ($this->hasReached($status, $reachedIndex, 'interview_hr')) {
            $hrEntered = $timeline['interview_hr'] ?? now()->subDays(3);
            $isCurrentUnscheduled = $status === 'interview_hr' && $index % 3 === 0;
            $isUpcoming = $status === 'interview_hr' && ! $isCurrentUnscheduled && $index % 2 === 0;
            $scheduledAt = $isUpcoming ? now()->addDays(1 + ($index % 6))->setTime(10 + ($index % 5), 0) : Carbon::parse($hrEntered)->addHours(4);
            $hrCompleted = ! $isCurrentUnscheduled && ! $isUpcoming;
            $hasSummary = $status !== 'interview_hr' ? $index % 5 !== 0 : ($hrCompleted && $index % 3 === 2);

            $updates += [
                'interview_hr_date' => $isCurrentUnscheduled ? null : $scheduledAt->toDateString(),
                'interview_hr_time' => $isCurrentUnscheduled ? null : $scheduledAt->format('H:i:s'),
                'interview_hr_type' => $index % 2 === 0 ? 'online' : 'offline',
                'interview_hr_location' => $index % 2 === 0 ? null : 'Ruang Interview HR, Head Office',
                'interview_hr_meet_link' => $index % 2 === 0 ? 'https://meet.example.test/hr-'.$candidate->id : null,
                'interview_hr_completed_at' => $hrCompleted ? Carbon::parse($scheduledAt)->addHours(2) : null,
                'interview_hr_completed_by' => $hrCompleted ? $this->actorUserId : null,
                'interview_hr_summary_path' => $hasSummary && $index % 2 === 0 ? $this->documents['hr_summary'] : null,
                'interview_hr_text_summary' => $hasSummary && $index % 2 !== 0
                    ? 'Komunikasi baik, motivasi sesuai, pengalaman relevan, dan dapat dilanjutkan ke tahap berikutnya.'
                    : null,
                'interview_hr_email_sent_at' => $isCurrentUnscheduled ? null : Carbon::parse($scheduledAt)->subDay(),
                'interview_hr_wa_sent_at' => $isCurrentUnscheduled ? null : Carbon::parse($scheduledAt)->subDay()->addMinutes(15),
            ];
        }

        if ($this->hasReached($status, $reachedIndex, 'case_study')) {
            $caseEntered = $timeline['case_study'] ?? now()->subDays(2);
            $hasSubmission = $status !== 'case_study' || $index % 3 !== 1;
            $updates += [
                'case_study_document_path' => $this->documents['case_brief'],
                'case_study_link' => 'https://recruitment.example.test/case-study/'.$candidate->id,
                'case_study_sent_at' => Carbon::parse($caseEntered)->addHour(),
                'case_study_wa_sent_at' => Carbon::parse($caseEntered)->addHours(2),
                'case_study_submitted_file_path' => $hasSubmission ? $this->documents['case_result'] : null,
                'case_study_submitted_at' => $hasSubmission ? Carbon::parse($caseEntered)->addHours(20 + ($index % 15)) : null,
                'case_study_token' => hash('sha256', 'case-'.$candidate->email),
                'case_study_password' => Hash::make('123456'),
            ];
        }

        if ($this->hasReached($status, $reachedIndex, 'interview_user')) {
            $this->createUserInterviewsAndEvaluations($candidate, $status, $index, $timeline['interview_user'] ?? now()->subDay());
        }

        if ($this->hasReached($status, $reachedIndex, 'reference_check')) {
            $referenceEntered = $timeline['reference_check'] ?? now()->subDay();
            $referenceComplete = $status !== 'reference_check' || $index % 3 === 0;
            $updates += [
                'reference_check_token' => hash('sha256', 'reference-'.$candidate->email),
                'reference_check_password' => Hash::make('123456'),
                'reference_check_email_sent_at' => Carbon::parse($referenceEntered)->addHour(),
                'reference_check_wa_sent_at' => Carbon::parse($referenceEntered)->addHours(2),
                'reference_check_submitted_at' => $referenceComplete ? Carbon::parse($referenceEntered)->addHours(18) : null,
                'reference_check_summary_path' => $referenceComplete ? $this->documents['reference_summary'] : null,
            ];
            $this->createReferences($candidate, str_contains($candidate->vacancy?->title ?? '', 'Supervisor') ? 3 : 2);
        }

        if ($this->hasReached($status, $reachedIndex, 'offering')) {
            $offeringEntered = $timeline['offering'] ?? now()->subDay();
            $hasLetter = $status !== 'offering' || $index % 3 !== 0;
            $isSigned = in_array($status, ['pkb', 'hired'], true) || ($status === 'offering' && $index % 3 === 2);
            $sentAt = $hasLetter ? Carbon::parse($offeringEntered)->addHours(2) : null;
            $updates += [
                'offering_letter_path' => $hasLetter ? $this->documents['offering'] : null,
                'offering_letter_token' => $hasLetter ? hash('sha256', 'offering-'.$candidate->email) : null,
                'offering_letter_password' => $hasLetter ? Hash::make('123456') : null,
                'offering_letter_sent_at' => $sentAt,
                'offering_letter_wa_sent_at' => $sentAt?->copy()->addMinutes(10),
                'offering_letter_signed_path' => $isSigned ? $this->documents['offering_signed'] : null,
                'offering_letter_signature_data' => $isSigned ? 'data:image/png;base64,'.base64_encode('DEMO-SIGNATURE') : null,
                'offering_letter_signed_at' => $isSigned && $sentAt ? $sentAt->copy()->addHours(4 + ($index % 18)) : null,
            ];
        }

        if ($this->hasReached($status, $reachedIndex, 'pkb')) {
            $this->createPkbSigners($candidate, $status, $index, $timeline['pkb'] ?? now()->subDay());
        }

        if ($status === 'hired') {
            $hiredEntered = $timeline['hired'] ?? now()->subDay();
            $completed = $index % 5 < 3;
            $sentAt = Carbon::parse($hiredEntered)->addHour();
            $updates += [
                'onboarding_token' => hash('sha256', 'onboarding-'.$candidate->email),
                'onboarding_password' => '654321',
                'onboarding_sent_at' => $sentAt,
                'onboarding_wa_sent_at' => $sentAt->copy()->addMinutes(5),
                'onboarding_completed_at' => $completed ? $sentAt->copy()->addHours(20 + ($index % 24)) : null,
                'onboarding_data' => $completed ? $this->onboardingData($candidate, $index) : null,
                'employee_nik' => $completed ? 'DEMO-EMP-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) : null,
            ];
        }

        if ($updates !== []) {
            $candidate->forceFill($updates)->saveQuietly();
        }
    }

    private function createUserInterviewsAndEvaluations(
        RecruitmentCandidate $candidate,
        string $status,
        int $index,
        Carbon $enteredAt,
    ): void {
        $roundCount = $index % 4 === 0 ? 2 : 1;
        $futureSchedule = $status === 'interview_user' && $index % 3 === 0;

        for ($round = 1; $round <= $roundCount; $round++) {
            $scheduledAt = $futureSchedule
                ? now()->addDays(1 + (($index + $round) % 6))->setTime(9 + $round, 30)
                : Carbon::parse($enteredAt)->addHours(5 + ($round * 4));
            $hasSummary = ! $futureSchedule && ($status !== 'interview_user' || $index % 4 !== 1);

            RecruitmentCandidateUserInterview::query()->create([
                'candidate_id' => $candidate->id,
                'round' => $round,
                'interview_date' => $scheduledAt->toDateString(),
                'interview_time' => $scheduledAt->format('H:i:s'),
                'interviewer_nik' => implode(',', array_slice($this->employeeNiks, 1, 2)),
                'interview_type' => $round % 2 === 0 ? 'online' : 'offline',
                'interview_location' => $round % 2 === 0 ? null : 'Ruang Meeting User',
                'interview_meet_link' => $round % 2 === 0 ? 'https://meet.example.test/user-'.$candidate->id.'-'.$round : null,
                'completed_at' => $futureSchedule ? null : $scheduledAt->copy()->addHours(2),
                'completed_by' => $futureSchedule ? null : $this->actorUserId,
                'summary_path' => $hasSummary ? $this->documents['user_summary'] : null,
                'email_sent_at' => $scheduledAt->copy()->subDay(),
                'wa_sent_at' => $scheduledAt->copy()->subDay()->addMinutes(10),
            ]);

            foreach (array_slice($this->employeeNiks, 1, 2) as $evaluatorIndex => $interviewerNik) {
                $pending = $futureSchedule
                    || ($status === 'interview_user' && (($index + $round + $evaluatorIndex) % 4 === 0));
                $evaluationSent = ! $futureSchedule
                    && (! $pending || (($index + $round + $evaluatorIndex) % 2 === 0));
                $scores = $this->evaluationScores($index, $round, $evaluatorIndex);
                RecruitmentUserInterviewEvaluation::query()->create([
                    'candidate_id' => $candidate->id,
                    'round' => $round,
                    'interviewer_nik' => $interviewerNik,
                    'token' => hash('sha256', "evaluation-{$candidate->email}-{$round}-{$interviewerNik}"),
                    'sent_at' => $evaluationSent ? $scheduledAt->copy()->addHours(2)->addMinutes(5 + $evaluatorIndex) : null,
                    ...($pending ? array_fill_keys(array_keys($scores), null) : $scores),
                    'interview_total_score' => $pending ? null : array_sum($scores),
                    'interview_evaluation_notes' => $pending ? null : 'Kompetensi utama sesuai kebutuhan. Kandidat komunikatif dan memiliki potensi berkembang.',
                    'interview_recommendation' => $pending ? null : ['disarankan', 'dipertimbangkan', 'disarankan'][$index % 3],
                    'submitted_at' => $pending ? null : $scheduledAt->copy()->addHours(2 + $evaluatorIndex),
                ]);
            }
        }
    }

    /** @return array<string, int> */
    private function evaluationScores(int $candidateIndex, int $round, int $evaluatorIndex): array
    {
        $fields = [
            'interview_appearance', 'interview_attitude', 'interview_communication',
            'interview_motivation', 'interview_initiative', 'interview_teamwork',
            'interview_domain_experience', 'interview_general_knowledge', 'interview_growth_potential',
        ];

        return collect($fields)->mapWithKeys(fn (string $field, int $fieldIndex) => [
            $field => 3 + (($candidateIndex + $round + $evaluatorIndex + $fieldIndex) % 3),
        ])->all();
    }

    private function createReferences(RecruitmentCandidate $candidate, int $count): void
    {
        $names = ['Bambang Setiawan', 'Ratna Dewi', 'Yusuf Maulana'];
        $relationships = ['Atasan Langsung', 'Rekan Kerja', 'HR Perusahaan Sebelumnya'];

        for ($i = 0; $i < $count; $i++) {
            RecruitmentCandidateReference::query()->create([
                'candidate_id' => $candidate->id,
                'name' => $names[$i],
                'phone' => '08137770000'.($i + 1),
                'company' => ['PT Maju Bersama', 'CV Karya Utama', 'PT Nusantara Digital'][$i],
                'position' => ['Manager', 'Senior Staff', 'HR Business Partner'][$i],
                'relationship' => $relationships[$i],
            ]);
        }
    }

    private function createPkbSigners(RecruitmentCandidate $candidate, string $status, int $index, Carbon $enteredAt): void
    {
        $signerCount = min(3, count($this->employeeNiks));
        foreach (array_slice($this->employeeNiks, 0, $signerCount) as $signerIndex => $nik) {
            $sentAt = Carbon::parse($enteredAt)->addHours(1 + $signerIndex);
            $signed = $status === 'hired' || (($index + $signerIndex) % 4 !== 0);
            RecruitmentCandidatePkbSigner::query()->create([
                'candidate_id' => $candidate->id,
                'employee_nik' => $nik,
                'sent_at' => $sentAt,
                'signed_at' => $signed ? $sentAt->copy()->addHours(3 + $signerIndex) : null,
                'signature_data' => $signed ? 'data:image/png;base64,'.base64_encode('DEMO-PKB-SIGNATURE-'.$nik) : null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function onboardingData(RecruitmentCandidate $candidate, int $index): array
    {
        return [
            'nama_lengkap' => $candidate->name,
            'email' => $candidate->email,
            'no_hp' => $candidate->phone,
            'tanggal_lahir' => now()->subYears(23 + ($index % 12))->subDays($index * 3)->toDateString(),
            'tempat_lahir' => ['Bandung', 'Jakarta', 'Semarang', 'Surabaya'][$index % 4],
            'jenis_kelamin' => $index % 2 === 0 ? 'L' : 'P',
            'alamat' => 'Alamat lengkap kandidat demo nomor '.($index + 1),
            'no_ktp' => '3273'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT),
            'agama' => 'Islam',
            'kewarganegaraan' => 'Indonesia',
            'status_pernikahan' => $index % 3 === 0 ? 'Menikah' : 'Belum Menikah',
            'golongan_darah' => ['A', 'B', 'AB', 'O'][$index % 4],
            'bank' => ['BCA', 'BRI', 'Mandiri'][$index % 3],
            'no_rekening' => '12345000'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'pendidikan_terakhir' => $candidate->education_level,
            'nama_institusi' => 'Universitas Demo Indonesia',
            'jurusan' => $candidate->education_major,
            'nama_ayah' => 'Nama Ayah Kandidat',
            'nama_ibu' => 'Nama Ibu Kandidat',
            'kontak_darurat_nama' => 'Kontak Darurat Kandidat',
            'kontak_darurat_hubungan' => 'Orang Tua',
            'kontak_darurat_no_hp' => '081399900001',
            'children' => $index % 3 === 0 ? [['name' => 'Anak Kandidat Demo']] : [],
            'no_npwp' => null,
            'no_bpjs' => null,
        ];
    }

    private function createAuditLog(RecruitmentCandidate $candidate, Carbon $occurredAt, int $index): void
    {
        $actions = ['created', 'updated', 'stage_changed', 'document_uploaded', 'notification_sent'];
        HrdAuditLog::query()->create([
            'module' => 'Recruitment',
            'action' => $actions[$index % count($actions)],
            'subject_type' => RecruitmentCandidate::class,
            'subject_id' => (string) $candidate->id,
            'subject_label' => $candidate->name.' · '.($candidate->vacancy?->title ?? 'Umum'),
            'actor_user_id' => $this->actorUserId,
            'actor_name' => ['HR Recruitment', 'HR Business Partner', 'Recruitment Administrator'][$index % 3],
            'actor_username' => 'recruitment.demo',
            'changes' => [['field' => 'status', 'old' => null, 'new' => $candidate->status]],
            'metadata' => ['seed' => self::MARKER, 'demo' => true],
            'occurred_at' => $occurredAt,
        ]);
    }

    private function hasReached(string $status, int $reachedIndex, string $target): bool
    {
        $targetIndex = array_search($target, self::PIPELINE, true);

        return $targetIndex !== false && $reachedIndex >= $targetIndex;
    }

    /** @return array<int, string> */
    private function resolveEmployeeNiks(): array
    {
        $niks = Karyawan::query()
            ->whereNotNull('nik')
            ->where('nik', '<>', '')
            ->orderBy('nik')
            ->limit(5)
            ->pluck('nik')
            ->map(fn ($nik) => (string) $nik)
            ->values()
            ->all();

        $fallbacks = ['DEMO-HR-001', 'DEMO-USER-001', 'DEMO-USER-002', 'DEMO-MGR-001', 'DEMO-DIR-001'];
        foreach ($fallbacks as $fallback) {
            if (count($niks) >= 5) {
                break;
            }
            $niks[] = $fallback;
        }

        return array_values(array_unique($niks));
    }

    /** @return array<string, string> */
    private function writeDemoDocuments(): array
    {
        $disk = Storage::disk('local');
        $disk->deleteDirectory(self::DOCUMENT_DIRECTORY);

        $documents = [
            'resume' => 'CV Kandidat Demo',
            'hr_summary' => 'Summary Wawancara HR',
            'case_brief' => 'Brief Case Study',
            'case_result' => 'Hasil Case Study Kandidat',
            'user_summary' => 'Summary Wawancara User',
            'reference_summary' => 'Summary Reference Check',
            'offering' => 'Offering Letter',
            'offering_signed' => 'Offering Letter Ditandatangani',
        ];

        return collect($documents)->mapWithKeys(function (string $title, string $key) use ($disk): array {
            $path = self::DOCUMENT_DIRECTORY.'/'.$key.'.pdf';
            $disk->put($path, $this->minimalPdf($title));

            return [$key => $path];
        })->all();
    }

    private function minimalPdf(string $title): string
    {
        $safeTitle = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);
        $stream = "BT\n/F1 18 Tf\n72 770 Td\n({$safeTitle}) Tj\n/F1 11 Tf\n0 -28 Td\n(Dokumen placeholder dari RecruitmentDashboardSeeder.) Tj\nET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj',
            '4 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}\nendstream\nendobj",
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
