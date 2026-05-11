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
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->timestamp('second_manager_approved_at')->nullable()->after('manager_approved_at');
            $table->unsignedBigInteger('second_manager_approved_by')->nullable()->after('manager_approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['second_manager_approved_at', 'second_manager_approved_by']);
        });
    }
};
