<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_releases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version_code')->unique();
            $table->string('version_name', 50);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->boolean('mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_releases');
    }
};
