<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_jabatans', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_jabatan', 150);
            $table->string('departemen', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['nama_jabatan', 'departemen'], 'master_jabatans_name_department_index');
        });

        DB::table('m_karyawan')
            ->select(['jabatan', 'departement'])
            ->whereNotNull('jabatan')
            ->where('jabatan', '<>', '')
            ->distinct()
            ->orderBy('jabatan')
            ->chunk(200, function ($rows): void {
                $now = now();
                $records = collect($rows)->map(fn ($row): array => [
                    'nama_jabatan' => trim((string) $row->jabatan),
                    'departemen' => filled($row->departement) ? trim((string) $row->departement) : null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('master_jabatans')->insert($records);
            });

        Schema::create('jobdesks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('master_jabatan_id')->constrained('master_jabatans')->restrictOnDelete();
            $table->string('kategori', 100);
            $table->text('deskripsi');
            $table->enum('tipe_tugas', ['harian', 'mingguan', 'bulanan', 'tahunan', 'insidental']);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['master_jabatan_id', 'is_active']);
        });

        Schema::create('kpi_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('master_jabatan_id')->constrained('master_jabatans')->restrictOnDelete();
            $table->foreignId('jobdesk_id')->nullable()->constrained('jobdesks')->nullOnDelete();
            $table->string('nama_kpi', 150);
            $table->text('deskripsi')->nullable();
            $table->string('target', 150);
            $table->enum('satuan', ['persen', 'jumlah', 'hari', 'rupiah', 'skor', 'dokumen']);
            $table->decimal('bobot', 5, 2);
            $table->text('formula_penilaian')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['master_jabatan_id', 'is_active']);
        });

        Schema::create('performance_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_periode', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_nik', 50);
            $table->foreignId('performance_period_id')->constrained('performance_periods')->restrictOnDelete();
            $table->string('jabatan_snapshot', 150);
            $table->string('departemen_snapshot', 100)->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_nik', 'performance_period_id'], 'performance_reviews_employee_period_unique');
            $table->index(['reviewer_id', 'status']);
        });

        Schema::create('performance_review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('kpi_template_id')->nullable()->constrained('kpi_templates')->nullOnDelete();
            $table->string('nama_kpi_snapshot', 150);
            $table->string('target_snapshot', 150);
            $table->string('satuan_snapshot', 30);
            $table->decimal('bobot_snapshot', 5, 2);
            $table->decimal('realisasi', 12, 2)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('weighted_score', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_items');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_periods');
        Schema::dropIfExists('kpi_templates');
        Schema::dropIfExists('jobdesks');
        Schema::dropIfExists('master_jabatans');
    }
};
