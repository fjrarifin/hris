<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $columns = [
                'libur_nasional',
                'izin',
                'sakit_surat',
                'sakit_tanpa_surat',
                'tanpa_keterangan',
                'cuti_tahunan',
                'cuti_normatif',
                'ph',
            ];

            foreach ($columns as $column) {
                if (! Schema::hasColumn('payrolls', $column)) {
                    $table->string($column, 45)->default('0');
                }
            }
        });

        Schema::table('payroll_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_items', 'component_id')) {
                $table->unsignedBigInteger('component_id')->nullable();
            }

            if (! Schema::hasColumn('payroll_items', 'nama_item')) {
                $table->string('nama_item', 250)->nullable();
            }
        });

        Schema::table('payroll_components', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_components', 'header')) {
                $table->string('header', 45)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Baseline only: existing production columns must not be removed.
    }
};
