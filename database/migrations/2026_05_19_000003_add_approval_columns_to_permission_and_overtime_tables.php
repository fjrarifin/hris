<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_permissions', 'reject_reason')) {
                $table->string('reject_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('employee_permissions', 'manager_approved_at')) {
                $table->timestamp('manager_approved_at')->nullable()->after('reject_reason');
            }

            if (! Schema::hasColumn('employee_permissions', 'manager_approved_by')) {
                $table->unsignedBigInteger('manager_approved_by')->nullable()->after('manager_approved_at');
            }

            if (! Schema::hasColumn('employee_permissions', 'approval_token')) {
                $table->string('approval_token')->nullable()->unique()->after('manager_approved_by');
            }

            if (! Schema::hasColumn('employee_permissions', 'approval_token_expires_at')) {
                $table->timestamp('approval_token_expires_at')->nullable()->after('approval_token');
            }
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_requests', 'reject_reason')) {
                $table->string('reject_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('overtime_requests', 'manager_approved_at')) {
                $table->timestamp('manager_approved_at')->nullable()->after('reject_reason');
            }

            if (! Schema::hasColumn('overtime_requests', 'manager_approved_by')) {
                $table->unsignedBigInteger('manager_approved_by')->nullable()->after('manager_approved_at');
            }

            if (! Schema::hasColumn('overtime_requests', 'approval_token')) {
                $table->string('approval_token')->nullable()->unique()->after('manager_approved_by');
            }

            if (! Schema::hasColumn('overtime_requests', 'approval_token_expires_at')) {
                $table->timestamp('approval_token_expires_at')->nullable()->after('approval_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_permissions', function (Blueprint $table) {
            foreach ([
                'reject_reason',
                'manager_approved_at',
                'manager_approved_by',
                'approval_token',
                'approval_token_expires_at',
            ] as $column) {
                if (Schema::hasColumn('employee_permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            foreach ([
                'reject_reason',
                'manager_approved_at',
                'manager_approved_by',
                'approval_token',
                'approval_token_expires_at',
            ] as $column) {
                if (Schema::hasColumn('overtime_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
