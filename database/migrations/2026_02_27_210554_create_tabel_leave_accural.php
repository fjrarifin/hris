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
        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->year('year'); // contoh: 2026
            $table->tinyInteger('month'); // 1-12
            $table->date('accrued_at'); // tanggal accrual
            $table->decimal('days', 4, 2)->default(1); // default 1 hari
            $table->date('expired_at'); // 31 Des 2026
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_accruals');
    }
};
