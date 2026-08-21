<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_accruals', function (Blueprint $table): void {
            $table->index('user_id');
            $table->dropUnique(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_accruals', function (Blueprint $table): void {
            $table->unique(['user_id', 'year', 'month']);
        });
    }
};
