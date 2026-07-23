<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table) {
            // Tipe lowongan: new_hire (rekrut baru) atau replacement (menggantikan karyawan)
            $table->enum('hire_type', ['new_hire', 'replacement'])->default('new_hire')->after('status');
            $table->string('replaced_employee_nik', 30)->nullable()->after('hire_type');
            $table->string('replaced_employee_name', 150)->nullable()->after('replaced_employee_nik');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table) {
            $table->dropColumn(['hire_type', 'replaced_employee_nik', 'replaced_employee_name']);
        });
    }
};
