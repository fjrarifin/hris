<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('m_karyawan', 'posisi_level')) {
                $table->string('posisi_level', 20)->nullable()->after('posisi');
            }

            if (! Schema::hasColumn('m_karyawan', 'posisi_title')) {
                $table->string('posisi_title', 100)->nullable()->after('posisi_level');
            }
        });

        $levels = ['Sr.', 'Md.', 'Jr.'];

        DB::table('m_karyawan')
            ->whereNotNull('posisi')
            ->where('posisi', '<>', '')
            ->select(['nik', 'posisi'])
            ->orderBy('nik')
            ->chunk(200, function ($rows) use ($levels) {
                foreach ($rows as $row) {
                    $posisi = trim((string) $row->posisi);
                    $level = null;
                    $title = $posisi;

                    foreach ($levels as $candidate) {
                        if (Str::startsWith($posisi, $candidate . ' ')) {
                            $level = $candidate;
                            $title = trim(Str::after($posisi, $candidate . ' '));
                            break;
                        }
                    }

                    DB::table('m_karyawan')
                        ->where('nik', $row->nik)
                        ->update([
                            'posisi_level' => $level,
                            'posisi_title' => $title ?: null,
                        ]);
                }
            }, 'nik');
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('m_karyawan', 'posisi_title')) {
                $table->dropColumn('posisi_title');
            }

            if (Schema::hasColumn('m_karyawan', 'posisi_level')) {
                $table->dropColumn('posisi_level');
            }
        });
    }
};
