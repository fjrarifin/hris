<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_requests', function (Blueprint $table): void {
            $table->enum('hiring_type', ['new_hiring', 'replacement'])->default('new_hiring')->after('description');
            $table->string('replaced_employee_nik', 30)->nullable()->after('hiring_type');
            $table->string('replaced_employee_name', 150)->nullable()->after('replaced_employee_nik');
            $table->string('replaced_employee_position', 150)->nullable()->after('replaced_employee_name');
            $table->enum('employment_status', ['pkwt', 'casual'])->default('pkwt')->after('replaced_employee_position');
            $table->string('direct_report_nik', 30)->nullable()->after('employment_status');
            $table->string('direct_report_name', 150)->nullable()->after('direct_report_nik');
            $table->json('subordinates')->nullable()->after('direct_report_name');
            $table->boolean('requires_gm')->default(false)->after('subordinates');

            $table->enum('approval_step', ['manager', 'gm', 'hrbp', 'completed', 'rejected'])->default('manager')->after('requires_gm');
            $table->string('manager_nik', 30)->nullable()->after('approval_step');
            $table->string('manager_name', 150)->nullable()->after('manager_nik');
            $table->enum('manager_status', ['pending', 'approved', 'rejected'])->default('pending')->after('manager_name');
            $table->timestamp('manager_approved_at')->nullable()->after('manager_status');
            $table->text('manager_notes')->nullable()->after('manager_approved_at');

            $table->string('gm_nik', 30)->nullable()->after('manager_notes');
            $table->string('gm_name', 150)->nullable()->after('gm_nik');
            $table->enum('gm_status', ['pending', 'approved', 'rejected'])->nullable()->after('gm_name');
            $table->timestamp('gm_approved_at')->nullable()->after('gm_status');
            $table->text('gm_notes')->nullable()->after('gm_approved_at');

            $table->string('hrbp_nik', 30)->nullable()->after('gm_notes');
            $table->string('hrbp_name', 150)->nullable()->after('hrbp_nik');
            $table->enum('hrbp_status', ['pending', 'approved', 'rejected'])->default('pending')->after('hrbp_name');
            $table->timestamp('hrbp_approved_at')->nullable()->after('hrbp_status');
            $table->text('hrbp_notes')->nullable()->after('hrbp_approved_at');

            $table->string('approval_token', 64)->nullable()->index()->after('hrbp_notes');
            $table->timestamp('approval_token_expires_at')->nullable()->after('approval_token');

            $table->enum('hiring_status', ['pending', 'accepted', 'rejected', 'ongoing', 'hired', 'canceled'])->default('pending')->after('status');
            $table->date('request_date')->nullable()->after('hiring_status');
            $table->date('start_date')->nullable()->after('request_date');
            $table->date('finished_date')->nullable()->after('start_date');
            $table->string('pic_hiring_nik', 30)->nullable()->after('finished_date');
            $table->string('pic_hiring_name', 150)->nullable()->after('pic_hiring_nik');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'hiring_type',
                'replaced_employee_nik',
                'replaced_employee_name',
                'replaced_employee_position',
                'employment_status',
                'direct_report_nik',
                'direct_report_name',
                'subordinates',
                'requires_gm',
                'approval_step',
                'manager_nik',
                'manager_name',
                'manager_status',
                'manager_approved_at',
                'manager_notes',
                'gm_nik',
                'gm_name',
                'gm_status',
                'gm_approved_at',
                'gm_notes',
                'hrbp_nik',
                'hrbp_name',
                'hrbp_status',
                'hrbp_approved_at',
                'hrbp_notes',
                'approval_token',
                'approval_token_expires_at',
                'hiring_status',
                'request_date',
                'start_date',
                'finished_date',
                'pic_hiring_nik',
                'pic_hiring_name',
            ]);
        });
    }
};
