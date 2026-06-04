<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_off_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('source_period_start');
            $table->date('source_period_end');
            $table->date('claim_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('hr_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason')->nullable();
            $table->string('approval_token')->nullable()->unique();
            $table->timestamp('approval_token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'claim_date', 'status'], 'extra_off_user_claim_status_unique');
            $table->index(['user_id', 'source_period_start', 'source_period_end'], 'extra_off_user_source_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_off_requests');
    }
};
