<?php

namespace Tests\Unit;

use App\Models\Karyawan;
use App\Models\KpiTemplate;
use App\Models\MasterJabatan;
use App\Models\PerformancePeriod;
use App\Services\PerformanceManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PerformanceManagementServiceTest extends TestCase
{
    private PerformanceManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->service = app(PerformanceManagementService::class);
    }

    public function test_active_kpi_weight_must_total_exactly_one_hundred_percent(): void
    {
        $jabatan = MasterJabatan::query()->create(['nama_jabatan' => 'Staff Finance']);
        $first = $this->createKpi($jabatan, 'Akurasi laporan', 60);
        $second = $this->createKpi($jabatan, 'Ketepatan waktu', 40);

        try {
            $this->service->syncActiveKpis($jabatan, [$first->id]);
            $this->fail('Expected validation exception for incomplete KPI weight.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_ids', $exception->errors());
        }

        $this->service->syncActiveKpis($jabatan, [$first->id, $second->id]);

        $this->assertSame(2, KpiTemplate::query()->where('is_active', true)->count());
    }

    public function test_review_uses_snapshots_calculates_score_and_rejects_duplicate_employee_period(): void
    {
        $jabatan = MasterJabatan::query()->create(['nama_jabatan' => 'Staff Finance', 'departemen' => 'Finance']);
        $first = $this->createKpi($jabatan, 'Akurasi laporan', 60, '10 dokumen');
        $second = $this->createKpi($jabatan, 'Ketepatan waktu', 40, '2 hari');
        $this->service->syncActiveKpis($jabatan, [$first->id, $second->id]);
        $employee = Karyawan::query()->create(['nik' => 'EMP-001', 'nama_karyawan' => 'Staff Test', 'jabatan' => 'Staff Finance', 'departement' => 'Finance']);
        $period = PerformancePeriod::query()->create(['nama_periode' => 'Q2 2026', 'start_date' => '2026-04-01', 'end_date' => '2026-06-30', 'status' => 'active']);

        $review = $this->service->generateReview($period, $employee);
        $items = $review->items;
        $first->update(['nama_kpi' => 'Nama baru', 'target' => 'Target baru']);

        $this->assertSame('Akurasi laporan', $items->first()->nama_kpi_snapshot);
        $this->assertSame('10 dokumen', $items->first()->target_snapshot);

        $review = $this->service->saveScores($review, [
            ['id' => $items[0]->id, 'realisasi' => 10, 'score' => 90],
            ['id' => $items[1]->id, 'realisasi' => 2, 'score' => 80],
        ]);
        $this->assertSame('86.00', $review->total_score);
        $this->assertSame('submitted', $this->service->submit($review)->status);

        $this->expectException(ValidationException::class);
        $this->service->generateReview($period, $employee);
    }

    private function createKpi(MasterJabatan $jabatan, string $name, int $weight, string $target = '100'): KpiTemplate
    {
        return KpiTemplate::query()->create([
            'master_jabatan_id' => $jabatan->id,
            'nama_kpi' => $name,
            'target' => $target,
            'satuan' => 'jumlah',
            'bobot' => $weight,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->timestamps();
        });
        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('nik')->nullable();
            $table->string('nama_karyawan');
            $table->string('jabatan');
            $table->string('departement')->nullable();
            $table->string('nama_atasan_langsung')->nullable();
            $table->string('atasan_langsung_nik', 30)->nullable();
            $table->string('atasan_tidak_langsung_nik', 30)->nullable();
            $table->timestamps();
        });
        Schema::create('master_jabatans', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_jabatan');
            $table->string('departemen')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('kpi_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('master_jabatan_id');
            $table->foreignId('jobdesk_id')->nullable();
            $table->string('nama_kpi');
            $table->text('deskripsi')->nullable();
            $table->string('target');
            $table->string('satuan');
            $table->decimal('bobot', 5, 2);
            $table->text('formula_penilaian')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('performance_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_periode');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_nik');
            $table->foreignId('performance_period_id');
            $table->string('jabatan_snapshot');
            $table->string('departemen_snapshot')->nullable();
            $table->foreignId('reviewer_id')->nullable();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('performance_review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_review_id');
            $table->foreignId('kpi_template_id')->nullable();
            $table->string('nama_kpi_snapshot');
            $table->string('target_snapshot');
            $table->string('satuan_snapshot');
            $table->decimal('bobot_snapshot', 5, 2);
            $table->decimal('realisasi', 12, 2)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('weighted_score', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
