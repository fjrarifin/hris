<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_requests', 'requested_by_user_id')) {
                $table->unsignedBigInteger('requested_by_user_id')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('overtime_requests', 'hr_approved_at')) {
                $table->timestamp('hr_approved_at')->nullable()->after('manager_approved_by');
            }

            if (! Schema::hasColumn('overtime_requests', 'hr_approved_by')) {
                $table->unsignedBigInteger('hr_approved_by')->nullable()->after('hr_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            foreach (['requested_by_user_id', 'hr_approved_at', 'hr_approved_by'] as $column) {
                if (Schema::hasColumn('overtime_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
