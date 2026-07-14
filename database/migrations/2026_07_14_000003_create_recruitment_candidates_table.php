<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vacancy_id')->nullable()->constrained('recruitment_vacancies')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 100);
            $table->string('phone', 30)->nullable();
            $table->string('resume_path', 255)->nullable();
            $table->enum('status', ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'])->default('applied');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidates');
    }
};
