<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use App\Services\HrisDatabaseQueryAgent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NovaSupervisorMenusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        config()->set('services.hris_agent.enabled', true);
        config()->set('services.hris_agent.persona_name', 'Nova');
        config()->set('services.ninerouter.base_url', 'http://ninerouter.test/v1');
        config()->set('services.ninerouter.api_key', 'test-key');
        config()->set('services.gemini.api_key', '');
        config()->set('services.gemini.api_keys', []);
        config()->set('services.openrouter.api_key', '');

        foreach ([
            't_wa_agent_unresolved_queries',
            't_whatsapp_bot_faq',
            'recruitment_candidates',
            'recruitment_vacancies',
            'recruitment_requests',
            'kpi_template_assignments',
            'kpi_template_items',
            'kpi_templates',
            'performance_periods',
            'overtime_requests',
            'leave_requests',
            'public_holiday_requests',
            'public_holidays',
            'extra_off_requests',
            'employee_permissions',
            'employee_extra_offs',
            'employee_ph_balances',
            'employee_ph_adjustments',
            'leave_accruals',
            'fingerspot_attendance_logs',
            't_kontrak_karyawan',
            'm_karyawan',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->unsignedTinyInteger('level')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_karyawan', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('no_hp')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->string('status_karyawan')->nullable();
            $table->date('join_date')->nullable();
            $table->string('atasan_langsung_nik')->nullable();
            $table->string('atasan_tidak_langsung_nik')->nullable();
            $table->string('pin')->nullable();
            $table->timestamps();
        });

        Schema::create('t_kontrak_karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('nik');
            $table->integer('kontrak_ke')->nullable();
            $table->string('jenis_kontrak')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('durasi_bulan')->nullable();
            $table->string('status_kontrak')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable();
            $table->string('title');
            $table->string('division')->nullable();
            $table->string('department')->nullable();
            $table->string('unit')->nullable();
            $table->string('position')->nullable();
            $table->string('supervisor_nik')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('t_wa_agent_unresolved_queries', function (Blueprint $table) {
            $table->id();
            $table->string('sender_phone')->nullable();
            $table->string('karyawan_nik')->nullable();
            $table->string('karyawan_nama')->nullable();
            $table->text('question');
            $table->text('bot_response')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nik')->nullable();
            $table->decimal('days', 4, 2)->default(0);
            $table->date('expired_at')->nullable();
            $table->boolean('is_used')->default(false);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('leave_type')->default('cuti_tahunan');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('approved');
        });

        Schema::create('employee_extra_offs', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('periode_start');
            $table->date('periode_end');
            $table->integer('days')->default(0);
            $table->date('expires_at')->nullable();
            $table->unsignedBigInteger('public_holiday_request_id')->nullable();
            $table->timestamps();
        });

        Schema::create('extra_off_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('claim_date');
            $table->date('source_period_start')->nullable();
            $table->date('source_period_end')->nullable();
            $table->unsignedBigInteger('employee_extra_off_id')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('public_holiday_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('public_holiday_id');
            $table->date('claim_date');
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('employee_ph_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('karyawan_nik');
            $table->unsignedBigInteger('public_holiday_id');
            $table->date('holiday_date');
        });

        Schema::create('employee_ph_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan_nik');
            $table->unsignedBigInteger('public_holiday_id')->nullable();
            $table->integer('days')->default(0);
        });

        Schema::create('fingerspot_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 50);
            $table->dateTime('scan_date');
            $table->string('status_scan', 20);
        });
    }

    public function test_level_3_supervisor_sql_prompt_and_execution_cover_multi_subordinate_contracts_including_indirect_and_expired(): void
    {
        Karyawan::query()->insert([
            [
                'nik' => 'SUP001',
                'nama_karyawan' => 'FAJAR ARIFIN',
                'jabatan' => 'SPV IT',
                'status_karyawan' => 'AKTIF',
                'atasan_langsung_nik' => null,
                'atasan_tidak_langsung_nik' => null,
            ],
            [
                'nik' => 'SUB001',
                'nama_karyawan' => 'FERIANSYAH RUSMANA',
                'jabatan' => 'Staff IT',
                'status_karyawan' => 'AKTIF',
                'atasan_langsung_nik' => 'SUP001',
                'atasan_tidak_langsung_nik' => null,
            ],
            [
                'nik' => 'SUB002',
                'nama_karyawan' => 'DINDIN SAEPUDIN',
                'jabatan' => 'IT Support',
                'status_karyawan' => 'AKTIF',
                'atasan_langsung_nik' => null,
                'atasan_tidak_langsung_nik' => 'SUP001',
            ],
            [
                'nik' => 'NON001',
                'nama_karyawan' => 'EX STAFF',
                'jabatan' => 'Staff IT',
                'status_karyawan' => 'NONAKTIF',
                'atasan_langsung_nik' => 'SUP001',
                'atasan_tidak_langsung_nik' => null,
            ],
            [
                'nik' => 'OUT001',
                'nama_karyawan' => 'BUDI LAIN',
                'jabatan' => 'Kasir',
                'status_karyawan' => 'AKTIF',
                'atasan_langsung_nik' => 'SUP999',
                'atasan_tidak_langsung_nik' => null,
            ],
        ]);

        DB::table('t_kontrak_karyawan')->insert([
            [
                'id' => 10,
                'nik' => 'SUB001',
                'kontrak_ke' => 1,
                'status_kontrak' => 'AKTIF',
                'start_date' => '2026-07-01',
                'end_date' => '2026-09-30',
            ],
            [
                'id' => 11,
                'nik' => 'SUB002',
                'kontrak_ke' => 2,
                'status_kontrak' => 'AKTIF',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
            [
                'id' => 12,
                'nik' => 'OUT001',
                'kontrak_ke' => 1,
                'status_kontrak' => 'AKTIF',
                'start_date' => '2026-01-01',
                'end_date' => '2026-11-30',
            ],
        ]);

        $supervisor = Karyawan::where('nik', 'SUP001')->firstOrFail();

        $capturedSqlPrompt = '';
        $capturedSummaryContext = '';

        Http::fake([
            'http://ninerouter.test/*' => function ($request) use (&$capturedSqlPrompt, &$capturedSummaryContext) {
                $sys = (string) ($request->data()['messages'][0]['content'] ?? '');
                if (str_contains($sys, 'Database Text-to-SQL Engine')) {
                    $capturedSqlPrompt = $sys;

                    // Simulasi query tim multi-bawahan tanpa LIMIT 1 dan tetap memuat kontrak yang end_date-nya sudah lewat
                    return Http::response([
                        'choices' => [[
                            'message' => [
                                'content' => "SELECT k.nik, k.nama_karyawan, tk.kontrak_ke, tk.status_kontrak, tk.start_date, tk.end_date FROM m_karyawan k LEFT JOIN t_kontrak_karyawan tk ON tk.id = (SELECT t2.id FROM t_kontrak_karyawan t2 WHERE t2.nik = k.nik ORDER BY t2.end_date DESC, t2.id DESC LIMIT 1) WHERE (k.atasan_langsung_nik = 'SUP001' OR k.atasan_tidak_langsung_nik = 'SUP001') AND (k.status_karyawan IS NULL OR UPPER(k.status_karyawan) NOT IN ('RESIGN', 'NONAKTIF')) ORDER BY tk.end_date ASC",
                            ],
                        ]],
                    ]);
                }

                $capturedSummaryContext = $sys;

                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => 'Kontrak Feriansyah Rusmana berakhir 30 September 2026, sedangkan Dindin Saepudin berakhir 31 Desember 2026.',
                        ],
                    ]],
                ]);
            },
        ]);

        $dbAgent = app(HrisDatabaseQueryAgent::class);
        $answer = $dbAgent->queryAndAnswer('kapan masa berakhir kontrak tim saya?', $supervisor, [], 3);

        $this->assertStringContainsString('kpi_templates', $capturedSqlPrompt);
        $this->assertStringContainsString('recruitment_requests', $capturedSqlPrompt);
        $this->assertStringContainsString('recruitment_vacancies', $capturedSqlPrompt);
        $this->assertStringContainsString('recruitment_candidates', $capturedSqlPrompt);
        $this->assertStringContainsString('TANPA LIMIT 1', $capturedSqlPrompt);
        $this->assertStringContainsString('JANGAN memfilter `start_date <= hari_ini <= end_date`', $capturedSqlPrompt);

        $this->assertStringContainsString('FERIANSYAH RUSMANA', $capturedSummaryContext);
        $this->assertStringContainsString('2026-09-30', $capturedSummaryContext);
        $this->assertStringContainsString('DINDIN SAEPUDIN', $capturedSummaryContext);
        $this->assertStringContainsString('2026-12-31', $capturedSummaryContext);
        $this->assertStringNotContainsString('EX STAFF', $capturedSummaryContext);
        $this->assertStringNotContainsString('BUDI LAIN', $capturedSummaryContext);

        $this->assertSame(
            'Kontrak Feriansyah Rusmana berakhir 30 September 2026, sedangkan Dindin Saepudin berakhir 31 Desember 2026.',
            $answer
        );
    }

    public function test_level_3_supervisor_rbac_blocks_other_hierarchy_and_allows_menu_atasan_scopes(): void
    {
        $dbAgent = app(HrisDatabaseQueryAgent::class);
        $refl = new \ReflectionClass($dbAgent);
        $val = $refl->getMethod('validateSqlSafety');
        $val->setAccessible(true);

        $blockedOtherSupervisor = $val->invoke(
            $dbAgent,
            "SELECT nama_karyawan FROM m_karyawan WHERE atasan_langsung_nik = 'OTHER001'",
            'SUP001',
            3,
            ''
        );
        $this->assertFalse($blockedOtherSupervisor['safe']);

        $blockedInClause = $val->invoke(
            $dbAgent,
            "SELECT nama_karyawan FROM m_karyawan WHERE atasan_langsung_nik IN ('OTHER001', 'SUP001')",
            'SUP001',
            3,
            ''
        );
        $this->assertFalse($blockedInClause['safe']);

        $blockedOtherKpi = $val->invoke(
            $dbAgent,
            "SELECT nama_template FROM kpi_templates WHERE supervisor_nik = 'OTHER001'",
            'SUP001',
            3,
            ''
        );
        $this->assertFalse($blockedOtherKpi['safe']);

        $blockedOtherRecruitment = $val->invoke(
            $dbAgent,
            "SELECT title FROM recruitment_requests WHERE requester_nik = 'OTHER001'",
            'SUP001',
            3,
            ''
        );
        $this->assertFalse($blockedOtherRecruitment['safe']);

        $allowedOwnVacancyCandidates = $val->invoke(
            $dbAgent,
            "SELECT rc.name FROM recruitment_candidates rc JOIN recruitment_vacancies rv ON rc.vacancy_id = rv.id WHERE rv.supervisor_nik = 'SUP001'",
            'SUP001',
            3,
            ''
        );
        $this->assertTrue($allowedOwnVacancyCandidates['safe']);

        $allowedOwnKpi = $val->invoke(
            $dbAgent,
            "SELECT kt.nama_template FROM kpi_templates kt WHERE kt.creator_nik = 'SUP001' OR kt.supervisor_nik = 'SUP001'",
            'SUP001',
            3,
            ''
        );
        $this->assertTrue($allowedOwnKpi['safe']);

        $allowedOwnRecruitment = $val->invoke(
            $dbAgent,
            "SELECT rr.title FROM recruitment_requests rr WHERE rr.requester_nik = 'SUP001'",
            'SUP001',
            3,
            ''
        );
        $this->assertTrue($allowedOwnRecruitment['safe']);
    }

    public function test_empty_db_result_and_general_fallback_include_exact_menu_atasan_navigation_paths(): void
    {
        Karyawan::query()->insert([
            [
                'nik' => 'SUP001',
                'nama_karyawan' => 'FAJAR ARIFIN',
                'jabatan' => 'SPV IT',
                'status_karyawan' => 'AKTIF',
                'atasan_langsung_nik' => null,
            ],
            [
                'nik' => 'SUB001',
                'nama_karyawan' => 'FERIANSYAH RUSMANA',
                'jabatan' => 'Staff IT',
                'status_karyawan' => 'AKTIF',
                'atasan_langsung_nik' => 'SUP001',
            ],
        ]);

        RecruitmentVacancy::query()->create([
            'slug' => 'it-staff',
            'title' => 'IT Staff',
            'supervisor_nik' => 'SUP001',
            'status' => 'open',
        ]);

        $supervisorUser = User::query()->create([
            'username' => 'SUP001',
            'name' => 'FAJAR ARIFIN',
            'email' => 'fajar@example.test',
            'password' => Hash::make('Password123'),
            'level' => 3,
            'is_active' => true,
        ]);

        $capturedSummarySysPrompt = '';
        $capturedGeneralSysPrompt = '';
        $fakeMode = 'empty_sql';

        Http::fake([
            'http://ninerouter.test/*' => function ($request) use (&$capturedSummarySysPrompt, &$capturedGeneralSysPrompt, &$fakeMode) {
                $sys = (string) ($request->data()['messages'][0]['content'] ?? '');
                if (str_contains($sys, 'Database Text-to-SQL Engine')) {
                    if ($fakeMode === 'none') {
                        return Http::response(['choices' => [['message' => ['content' => 'NONE']]]]);
                    }

                    return Http::response([
                        'choices' => [[
                            'message' => [
                                'content' => "SELECT tk.end_date FROM m_karyawan k JOIN t_kontrak_karyawan tk ON tk.nik = k.nik WHERE k.atasan_langsung_nik = 'SUP001'",
                            ],
                        ]],
                    ]);
                }

                if (str_contains($sys, '[DATA AKTUAL HASIL QUERY HRIS]')) {
                    $capturedSummarySysPrompt = $sys;

                    return Http::response([], 500);
                }

                $capturedGeneralSysPrompt = $sys;

                return Http::response([], 500);
            },
        ]);

        // 1) Uji saat SQL mengembalikan 0 baris & summarizer LLM error -> tetap mengarahkan ke nama menu spesifik
        $dbAgent = app(HrisDatabaseQueryAgent::class);
        $supervisorEmp = Karyawan::where('nik', 'SUP001')->firstOrFail();
        $emptyFallback = $dbAgent->queryAndAnswer('kapan kontrak tim saya berakhir?', $supervisorEmp, [], 3);

        $this->assertStringContainsString('[CATATAN DATA KOSONG]', $capturedSummarySysPrompt);
        $this->assertStringContainsString('Menu Atasan ➔ Persetujuan Pengajuan', $capturedSummarySysPrompt);
        $this->assertStringContainsString('Menu Atasan ➔ Kandidat Bawahan', $capturedSummarySysPrompt);
        $this->assertStringContainsString('Kontrak Kerja', $capturedSummarySysPrompt);
        $this->assertStringContainsString('menu *Kontrak Kerja*', (string) $emptyFallback);

        // 2) Uji via Web Chat ketika SQL menghasilkan 0 baris & summarizer fallback
        RateLimiter::clear('web_ai_agent_limit:' . $supervisorUser->id);
        Sanctum::actingAs($supervisorUser);

        $chatRes = $this->postJson('/api/nova/chat', [
            'message' => 'apakah ada pengajuan cuti bawahan yang perlu saya approve?',
        ]);

        $chatRes->assertOk()->assertJsonPath('status', 'sent');
        $this->assertStringContainsString('menu *Menu Atasan ➔ Persetujuan Pengajuan*', (string) $chatRes->json('reply'));

        // 3) Uji jalur general mode (SQL = NONE) memverifikasi konteks atasan & semua menu masuk ke system prompt
        $fakeMode = 'none';

        RateLimiter::clear('web_ai_agent_limit:' . $supervisorUser->id);
        $generalRes = $this->postJson('/api/nova/chat', [
            'message' => 'cara cek jadwal tim gimana ya?',
        ]);

        $generalRes->assertOk();
        $this->assertStringContainsString('Status Peran: Atasan / Supervisor', $capturedGeneralSysPrompt);
        $this->assertStringContainsString('FERIANSYAH RUSMANA (SUB001, Staff IT, Bawahan Langsung)', $capturedGeneralSysPrompt);
        $this->assertStringContainsString('Menu Atasan ➔ Jadwal Tim', $capturedGeneralSysPrompt);
        $this->assertStringContainsString('Menu Atasan ➔ Template & Penugasan KPI', $capturedGeneralSysPrompt);
        $this->assertStringContainsString('Menu Atasan ➔ Pengajuan Rekrutmen', $capturedGeneralSysPrompt);
        $this->assertStringContainsString('Menu Atasan ➔ Kandidat Bawahan', $capturedGeneralSysPrompt);
        $this->assertStringContainsString('menu *Menu Atasan ➔ Jadwal Tim*', (string) $generalRes->json('reply'));
    }

    public function test_normalize_mysql_collation_comparisons_handles_cross_table_joins_without_touching_select_or_ids(): void
    {
        $dbAgent = app(HrisDatabaseQueryAgent::class);
        $refl = new \ReflectionClass($dbAgent);
        $method = $refl->getMethod('normalizeMysqlCollationComparisons');
        $method->setAccessible(true);

        $sql = "SELECT k.nik, k.nama_karyawan, tk.end_date FROM m_karyawan k JOIN t_kontrak_karyawan tk ON tk.nik = k.nik JOIN users u ON u.username = k.nik LEFT JOIN leave_requests l ON l.user_id = u.id WHERE k.atasan_langsung_nik = 'SUP001'";
        $normalized = $method->invoke($dbAgent, $sql);

        $this->assertStringContainsString('SELECT k.nik, k.nama_karyawan', $normalized);
        $this->assertStringContainsString('tk.nik COLLATE utf8mb4_unicode_ci = k.nik COLLATE utf8mb4_unicode_ci', $normalized);
        $this->assertStringContainsString('u.username COLLATE utf8mb4_unicode_ci = k.nik COLLATE utf8mb4_unicode_ci', $normalized);
        $this->assertStringContainsString("k.atasan_langsung_nik COLLATE utf8mb4_unicode_ci = 'SUP001'", $normalized);
        $this->assertStringContainsString('ON l.user_id = u.id', $normalized);
    }
}
