<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            $table->string('slug', 180)->nullable()->unique()->after('id');
            $table->string('employment_type', 30)->nullable()->after('description');
            $table->string('workplace_type', 20)->nullable()->after('employment_type');
            $table->string('location', 150)->nullable()->after('workplace_type');
            $table->longText('responsibilities')->nullable()->after('location');
            $table->longText('requirements')->nullable()->after('responsibilities');
            $table->longText('benefits')->nullable()->after('requirements');
            $table->timestamp('published_at')->nullable()->after('benefits');
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->date('application_deadline')->nullable()->after('expires_at');
        });

        DB::table('recruitment_vacancies')->orderBy('id')->each(function (object $vacancy): void {
            DB::table('recruitment_vacancies')->where('id', $vacancy->id)->update([
                'slug' => Str::slug($vacancy->title).'-'.$vacancy->id,
                'published_at' => $vacancy->status === 'open' ? ($vacancy->created_at ?? now()) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug', 'employment_type', 'workplace_type', 'location', 'responsibilities',
                'requirements', 'benefits', 'published_at', 'expires_at', 'application_deadline',
            ]);
        });
    }
};
