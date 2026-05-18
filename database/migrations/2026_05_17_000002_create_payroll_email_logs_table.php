<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('karyawan_nik')->nullable()->index();
            $table->string('recipient_email')->nullable();
            $table->string('subject')->nullable();
            $table->string('action')->default('send');
            $table->string('status')->default('simulated');
            $table->unsignedInteger('attempt_no')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_email_logs');
    }
};
