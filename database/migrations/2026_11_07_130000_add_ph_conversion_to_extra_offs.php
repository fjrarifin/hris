<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_extra_offs', function (Blueprint $table): void {
            $table->date('expires_at')->nullable();
            $table->unsignedBigInteger('public_holiday_request_id')->nullable()->unique();
        });
        Schema::table('extra_off_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_extra_off_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('extra_off_requests', function (Blueprint $table): void {
            $table->dropIndex(['employee_extra_off_id']);
            $table->dropColumn('employee_extra_off_id');
        });
        Schema::table('employee_extra_offs', function (Blueprint $table): void {
            $table->dropUnique(['public_holiday_request_id']);
            $table->dropColumn(['expires_at', 'public_holiday_request_id']);
        });
    }
};
