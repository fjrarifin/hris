<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('m_karyawan') && !Schema::hasColumn('m_karyawan', 'rfid_tag')) {
            Schema::table('m_karyawan', function (Blueprint $table) {
                $table->string('rfid_tag', 100)->nullable()->after('pin')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('m_karyawan') && Schema::hasColumn('m_karyawan', 'rfid_tag')) {
            Schema::table('m_karyawan', function (Blueprint $table) {
                $table->dropIndex(['rfid_tag']);
                $table->dropColumn('rfid_tag');
            });
        }
    }
};
