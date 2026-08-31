<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_revenues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->decimal('omset', 16, 2)->default(0);
            $table->string('branch_or_unit', 100)->default('Holding');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'branch_or_unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_revenues');
    }
};
