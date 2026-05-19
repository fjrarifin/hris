<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email_updated_at')) {
                $table->timestamp('email_updated_at')->nullable()->after('email_verified_at');
            }
        });

        Schema::table('m_karyawan', function (Blueprint $table) {
            if (!Schema::hasColumn('m_karyawan', 'phone_updated_at')) {
                $table->timestamp('phone_updated_at')->nullable()->after('no_hp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'email_updated_at')) {
                $table->dropColumn('email_updated_at');
            }
        });

        Schema::table('m_karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('m_karyawan', 'phone_updated_at')) {
                $table->dropColumn('phone_updated_at');
            }
        });
    }
};
