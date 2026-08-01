<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            if (! Schema::hasColumn('recruitment_vacancies', 'street_address')) {
                $table->string('street_address', 255)->nullable()->after('location');
            }
            if (! Schema::hasColumn('recruitment_vacancies', 'address_region')) {
                $table->string('address_region', 100)->nullable()->after('street_address');
            }
            if (! Schema::hasColumn('recruitment_vacancies', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('address_region');
            }
            if (! Schema::hasColumn('recruitment_vacancies', 'salary_min')) {
                $table->unsignedBigInteger('salary_min')->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('recruitment_vacancies', 'salary_max')) {
                $table->unsignedBigInteger('salary_max')->nullable()->after('salary_min');
            }
            if (! Schema::hasColumn('recruitment_vacancies', 'hide_salary')) {
                $table->boolean('hide_salary')->default(false)->after('salary_max');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            $table->dropColumn([
                'street_address',
                'address_region',
                'postal_code',
                'salary_min',
                'salary_max',
                'hide_salary',
            ]);
        });
    }
};
