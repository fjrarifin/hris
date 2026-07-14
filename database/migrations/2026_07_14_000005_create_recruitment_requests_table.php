<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('requester_nik', 30)->index();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->integer('quantity')->default(1);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('vacancy_id')->nullable()->constrained('recruitment_vacancies')->nullOnDelete();
            $table->text('hrd_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_requests');
    }
};
