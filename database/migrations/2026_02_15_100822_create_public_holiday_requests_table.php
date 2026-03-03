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
        Schema::create('public_holiday_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_holiday_id')->constrained()->cascadeOnDelete();

            $table->date('claim_date');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'cancelled'
            ])->default('pending');

            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('hr_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reject_reason')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'public_holiday_id']);
            // Tidak boleh claim PH yang sama dua kali
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_holiday_requests');
    }
};
