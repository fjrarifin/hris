<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            if (! Schema::hasColumn('payrolls', 'payroll_proration')) {
                $table->json('payroll_proration')->nullable()->after('formula_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            if (Schema::hasColumn('payrolls', 'payroll_proration')) {
                $table->dropColumn('payroll_proration');
            }
        });
    }
};