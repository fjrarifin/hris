<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dropFkIfExists(string $table, string $constraint): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($constraint) {
                $t->dropForeign($constraint);
            });
        }
    }

    public function up(): void
    {
        // 1. Drop foreign key from performance_review_items to kpi_templates if exists
        $this->dropFkIfExists('performance_review_items', 'performance_review_items_kpi_template_id_foreign');

        // 2. Drop existing foreign keys from old kpi_templates table to avoid constraint name collisions
        $this->dropFkIfExists('kpi_templates', 'kpi_templates_jobdesk_id_foreign');
        $this->dropFkIfExists('kpi_templates', 'kpi_templates_master_jabatan_id_foreign');
        $this->dropFkIfExists('kpi_templates', 'kpi_templates_created_by_foreign');
        $this->dropFkIfExists('kpi_templates', 'kpi_templates_updated_by_foreign');

        // 3. Rename old kpi_templates table to kpi_template_items
        if (Schema::hasTable('kpi_templates') && ! Schema::hasTable('kpi_template_items')) {
            Schema::rename('kpi_templates', 'kpi_template_items');
        }

        // 4. Update kpi_template_items: drop jobdesk_id if exists and add kpi_template_id, urutan
        Schema::table('kpi_template_items', function (Blueprint $table) {
            if (Schema::hasColumn('kpi_template_items', 'jobdesk_id')) {
                $table->dropColumn('jobdesk_id');
            }
            if (! Schema::hasColumn('kpi_template_items', 'kpi_template_id')) {
                $table->unsignedBigInteger('kpi_template_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('kpi_template_items', 'urutan')) {
                $table->integer('urutan')->default(0)->after('formula_penilaian');
            }
        });

        // Make master_jabatan_id nullable so ON DELETE SET NULL works
        DB::statement("ALTER TABLE kpi_template_items MODIFY master_jabatan_id BIGINT UNSIGNED NULL");

        // Re-add foreign keys on kpi_template_items with distinct constraint names
        $this->dropFkIfExists('kpi_template_items', 'kti_master_jabatan_id_fk');
        $this->dropFkIfExists('kpi_template_items', 'kti_created_by_fk');
        $this->dropFkIfExists('kpi_template_items', 'kti_updated_by_fk');

        Schema::table('kpi_template_items', function (Blueprint $table) {
            $table->foreign('master_jabatan_id', 'kti_master_jabatan_id_fk')->references('id')->on('master_jabatans')->nullOnDelete();
            $table->foreign('created_by', 'kti_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'kti_updated_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        // 5. Create new kpi_templates table (Header Template) if not exists
        if (! Schema::hasTable('kpi_templates')) {
            Schema::create('kpi_templates', function (Blueprint $table) {
                $table->id();
                $table->string('nama_template', 150);
                $table->text('deskripsi')->nullable();
                $table->foreignId('master_jabatan_id')->nullable()->constrained('master_jabatans')->nullOnDelete();
                $table->string('departemen', 100)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('creator_nik', 50)->nullable();
                $table->enum('status', ['draft', 'waiting_supervisor', 'waiting_hrd', 'approved', 'rejected'])->default('draft');
                $table->string('supervisor_nik', 50)->nullable();
                $table->timestamp('supervisor_approved_at')->nullable();
                $table->timestamp('supervisor_rejected_at')->nullable();
                $table->text('supervisor_notes')->nullable();
                $table->foreignId('hrd_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('hrd_approved_at')->nullable();
                $table->timestamp('hrd_rejected_at')->nullable();
                $table->text('hrd_notes')->nullable();
                $table->text('reject_reason')->nullable();
                $table->decimal('total_bobot', 5, 2)->default(100.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['creator_nik', 'status']);
                $table->index(['supervisor_nik', 'status']);
                $table->index(['status', 'is_active']);
            });
        }

        // 6. Migrate existing items in kpi_template_items to new kpi_templates header
        $distinctJabatans = DB::table('kpi_template_items')
            ->select('master_jabatan_id')
            ->whereNotNull('master_jabatan_id')
            ->distinct()
            ->get();

        foreach ($distinctJabatans as $row) {
            $existingTemplate = DB::table('kpi_templates')
                ->where('master_jabatan_id', $row->master_jabatan_id)
                ->first();

            if (! $existingTemplate) {
                $jabatan = DB::table('master_jabatans')->where('id', $row->master_jabatan_id)->first();
                $namaJabatan = $jabatan?->nama_jabatan ?? 'Jabatan '.$row->master_jabatan_id;
                $dept = $jabatan?->departemen;
                $items = DB::table('kpi_template_items')->where('master_jabatan_id', $row->master_jabatan_id)->get();
                $totalBobot = $items->sum('bobot');

                $templateId = DB::table('kpi_templates')->insertGetId([
                    'nama_template' => 'Template KPI - ' . $namaJabatan,
                    'deskripsi' => 'Template KPI standar untuk ' . $namaJabatan,
                    'master_jabatan_id' => $row->master_jabatan_id,
                    'departemen' => $dept,
                    'status' => 'approved',
                    'hrd_approved_at' => now(),
                    'total_bobot' => $totalBobot > 0 ? $totalBobot : 100.00,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $templateId = $existingTemplate->id;
            }

            DB::table('kpi_template_items')
                ->where('master_jabatan_id', $row->master_jabatan_id)
                ->whereNull('kpi_template_id')
                ->update(['kpi_template_id' => $templateId]);
        }

        // Add foreign key constraint from kpi_template_items to kpi_templates
        $this->dropFkIfExists('kpi_template_items', 'kpi_template_items_kpi_template_id_foreign');
        Schema::table('kpi_template_items', function (Blueprint $table) {
            $table->foreign('kpi_template_id')->references('id')->on('kpi_templates')->cascadeOnDelete();
        });

        // 7. Update performance_review_items
        if (Schema::hasColumn('performance_review_items', 'kpi_template_id') && ! Schema::hasColumn('performance_review_items', 'kpi_template_item_id')) {
            Schema::table('performance_review_items', function (Blueprint $table) {
                $table->renameColumn('kpi_template_id', 'kpi_template_item_id');
            });
        }

        if (! Schema::hasColumn('performance_review_items', 'kpi_template_id')) {
            Schema::table('performance_review_items', function (Blueprint $table) {
                $table->unsignedBigInteger('kpi_template_id')->nullable()->after('performance_review_id');
            });
        }

        $this->dropFkIfExists('performance_review_items', 'pri_kpi_template_item_id_fk');
        $this->dropFkIfExists('performance_review_items', 'pri_kpi_template_id_fk');

        Schema::table('performance_review_items', function (Blueprint $table) {
            $table->foreign('kpi_template_item_id', 'pri_kpi_template_item_id_fk')->references('id')->on('kpi_template_items')->nullOnDelete();
            $table->foreign('kpi_template_id', 'pri_kpi_template_id_fk')->references('id')->on('kpi_templates')->nullOnDelete();
        });

        // Populate performance_review_items.kpi_template_id from item's template
        DB::statement("
            UPDATE performance_review_items pri
            INNER JOIN kpi_template_items kti ON pri.kpi_template_item_id = kti.id
            SET pri.kpi_template_id = kti.kpi_template_id
            WHERE pri.kpi_template_item_id IS NOT NULL AND pri.kpi_template_id IS NULL
        ");

        // 8. Create kpi_template_assignments table
        if (! Schema::hasTable('kpi_template_assignments')) {
            Schema::create('kpi_template_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kpi_template_id')->constrained('kpi_templates')->cascadeOnDelete();
                $table->string('employee_nik', 50);
                $table->foreignId('performance_period_id')->nullable()->constrained('performance_periods')->nullOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->decimal('weight_percentage', 5, 2)->default(100.00);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['employee_nik', 'status']);
                $table->index(['kpi_template_id', 'status']);
            });
        }

        // 9. Register frontend menu for staff-kpi-templates if not exists
        if (! DB::table('frontend_menus')->where('key', 'staff-kpi-templates')->exists()) {
            DB::table('frontend_menus')->insert([
                'key' => 'staff-kpi-templates',
                'label' => 'Template & Penugasan KPI',
                'path' => '/staff/kpi-templates',
                'icon' => 'i-lucide-award',
                'allowed_levels' => '3',
                'sort_order' => 88,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'staff-kpi-templates')->delete();

        Schema::dropIfExists('kpi_template_assignments');

        $this->dropFkIfExists('performance_review_items', 'pri_kpi_template_id_fk');
        $this->dropFkIfExists('performance_review_items', 'pri_kpi_template_item_id_fk');

        if (Schema::hasColumn('performance_review_items', 'kpi_template_id')) {
            Schema::table('performance_review_items', function (Blueprint $table) {
                $table->dropColumn('kpi_template_id');
            });
        }

        if (Schema::hasColumn('performance_review_items', 'kpi_template_item_id')) {
            Schema::table('performance_review_items', function (Blueprint $table) {
                $table->renameColumn('kpi_template_item_id', 'kpi_template_id');
            });
        }

        $this->dropFkIfExists('kpi_template_items', 'kti_master_jabatan_id_fk');
        $this->dropFkIfExists('kpi_template_items', 'kti_created_by_fk');
        $this->dropFkIfExists('kpi_template_items', 'kti_updated_by_fk');
        $this->dropFkIfExists('kpi_template_items', 'kpi_template_items_kpi_template_id_foreign');

        if (Schema::hasColumn('kpi_template_items', 'kpi_template_id')) {
            Schema::table('kpi_template_items', function (Blueprint $table) {
                $table->dropColumn(['kpi_template_id', 'urutan']);
                $table->foreignId('jobdesk_id')->nullable()->constrained('jobdesks')->nullOnDelete();
            });
        }

        Schema::dropIfExists('kpi_templates');

        if (Schema::hasTable('kpi_template_items')) {
            Schema::rename('kpi_template_items', 'kpi_templates');
        }

        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->foreign('master_jabatan_id')->references('id')->on('master_jabatans')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('performance_review_items', function (Blueprint $table) {
            $table->foreign('kpi_template_id')->references('id')->on('kpi_templates')->nullOnDelete();
        });
    }
};
