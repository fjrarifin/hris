<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('m_karyawan', 'pin')) {
                $table->string('pin', 50)->nullable()->after('id');
                $table->index('pin', 'm_karyawan_pin_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('m_karyawan', 'pin')) {
                $table->dropIndex('m_karyawan_pin_index');
                $table->dropColumn('pin');
            }
        });
    }
};
