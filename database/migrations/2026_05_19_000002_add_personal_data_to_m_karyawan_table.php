<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('m_karyawan', 'no_ktp')) {
                $table->string('no_ktp', 30)->nullable()->after('jenis_kelamin');
            }

            if (! Schema::hasColumn('m_karyawan', 'tempat_lahir')) {
                $table->string('tempat_lahir', 100)->nullable()->after('no_ktp');
            }

            if (! Schema::hasColumn('m_karyawan', 'alamat')) {
                $table->text('alamat')->nullable()->after('tempat_lahir');
            }

            if (! Schema::hasColumn('m_karyawan', 'npwp')) {
                $table->boolean('npwp')->default(false)->after('alamat');
            }

            if (! Schema::hasColumn('m_karyawan', 'no_npwp')) {
                $table->string('no_npwp', 30)->nullable()->after('npwp');
            }

            if (! Schema::hasColumn('m_karyawan', 'status_pernikahan')) {
                $table->string('status_pernikahan', 50)->nullable()->after('no_npwp');
            }

            if (! Schema::hasColumn('m_karyawan', 'agama')) {
                $table->string('agama', 50)->nullable()->after('status_pernikahan');
            }

            if (! Schema::hasColumn('m_karyawan', 'kewarganegaraan')) {
                $table->string('kewarganegaraan', 50)->nullable()->after('agama');
            }

            if (! Schema::hasColumn('m_karyawan', 'pendidikan_terakhir')) {
                $table->string('pendidikan_terakhir', 50)->nullable()->after('kewarganegaraan');
            }

            if (! Schema::hasColumn('m_karyawan', 'nama_institusi')) {
                $table->string('nama_institusi', 150)->nullable()->after('pendidikan_terakhir');
            }

            if (! Schema::hasColumn('m_karyawan', 'jurusan')) {
                $table->string('jurusan', 100)->nullable()->after('nama_institusi');
            }

            if (! Schema::hasColumn('m_karyawan', 'nama_pasangan')) {
                $table->string('nama_pasangan', 150)->nullable()->after('jurusan');
            }

            if (! Schema::hasColumn('m_karyawan', 'jumlah_anak')) {
                $table->unsignedSmallInteger('jumlah_anak')->nullable()->after('nama_pasangan');
            }

            if (! Schema::hasColumn('m_karyawan', 'nama_ayah')) {
                $table->string('nama_ayah', 150)->nullable()->after('jumlah_anak');
            }

            if (! Schema::hasColumn('m_karyawan', 'nama_ibu')) {
                $table->string('nama_ibu', 150)->nullable()->after('nama_ayah');
            }

            if (! Schema::hasColumn('m_karyawan', 'kontak_darurat_nama')) {
                $table->string('kontak_darurat_nama', 150)->nullable()->after('nama_ibu');
            }

            if (! Schema::hasColumn('m_karyawan', 'kontak_darurat_hubungan')) {
                $table->string('kontak_darurat_hubungan', 50)->nullable()->after('kontak_darurat_nama');
            }

            if (! Schema::hasColumn('m_karyawan', 'kontak_darurat_no_hp')) {
                $table->string('kontak_darurat_no_hp', 30)->nullable()->after('kontak_darurat_hubungan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            foreach ([
                'no_ktp',
                'tempat_lahir',
                'alamat',
                'npwp',
                'no_npwp',
                'status_pernikahan',
                'agama',
                'kewarganegaraan',
                'pendidikan_terakhir',
                'nama_institusi',
                'jurusan',
                'nama_pasangan',
                'jumlah_anak',
                'nama_ayah',
                'nama_ibu',
                'kontak_darurat_nama',
                'kontak_darurat_hubungan',
                'kontak_darurat_no_hp',
            ] as $column) {
                if (Schema::hasColumn('m_karyawan', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
