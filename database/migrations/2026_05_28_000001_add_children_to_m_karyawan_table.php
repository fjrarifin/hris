<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table): void {
            if (! Schema::hasColumn('m_karyawan', 'children')) {
                $table->json('children')->nullable()->after('jumlah_anak');
            }
        });

        if (
            Schema::hasColumn('m_karyawan', 'children')
            && Schema::hasColumn('m_karyawan', 'nama_anak_1')
            && Schema::hasColumn('m_karyawan', 'nama_anak_2')
            && Schema::hasColumn('m_karyawan', 'nama_anak_3')
        ) {
            DB::table('m_karyawan')
                ->select('nik', 'nama_anak_1', 'nama_anak_2', 'nama_anak_3')
                ->orderBy('nik')
                ->chunk(100, function ($employees): void {
                    foreach ($employees as $employee) {
                        $children = collect([
                            $employee->nama_anak_1,
                            $employee->nama_anak_2,
                            $employee->nama_anak_3,
                        ])
                            ->map(fn ($name) => trim((string) $name))
                            ->filter()
                            ->values()
                            ->all();

                        if ($children !== []) {
                            DB::table('m_karyawan')
                                ->where('nik', $employee->nik)
                                ->update(['children' => json_encode($children)]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table): void {
            if (Schema::hasColumn('m_karyawan', 'children')) {
                $table->dropColumn('children');
            }
        });
    }
};
