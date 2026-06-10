<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_permissions', 'end_date')) {
                $table->date('end_date')->nullable()->after('date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_permissions', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });
    }
};
