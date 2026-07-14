<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Tables
        Schema::create('master_position_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Extract and Seed Position Titles
        $hardcodedPositions = ['Operator', 'Staff', 'Leader', 'Supervisor', 'Asst. Manager', 'Manager', 'GM', 'Advisor'];
        $dbPositions = Schema::hasTable('m_karyawan') 
            ? DB::table('m_karyawan')->distinct()->pluck('posisi_title')->filter()->toArray() 
            : [];
        $positions = collect(array_merge($hardcodedPositions, $dbPositions))
            ->map(fn($item) => trim((string)$item))
            ->filter(fn($item) => $item !== '' && $item !== '-')
            ->unique();
        
        foreach ($positions as $position) {
            DB::table('master_position_titles')->insertOrIgnore([
                'name' => $position,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Extract and Seed Divisions
        $hardcodedDivisions = ['Business Partner', 'Commercial Business'];
        $dbDivisions = Schema::hasTable('m_karyawan')
            ? DB::table('m_karyawan')->distinct()->pluck('divisi')->filter()->toArray()
            : [];
        $divisions = collect(array_merge($hardcodedDivisions, $dbDivisions))
            ->map(fn($item) => trim((string)$item))
            ->filter(fn($item) => $item !== '' && $item !== '-')
            ->unique();

        foreach ($divisions as $division) {
            DB::table('master_divisions')->insertOrIgnore([
                'name' => $division,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Extract and Seed Departments
        $hardcodedDepartments = ['GM', 'Marketing', 'Sales', 'Finance', 'HRBP', 'Activity', 'General Affair', 'Hompim Store', 'Sales Marketing', 'Proyek'];
        $dbDepartments = Schema::hasTable('m_karyawan')
            ? DB::table('m_karyawan')->distinct()->pluck('departement')->filter()->toArray()
            : [];
        $departments = collect(array_merge($hardcodedDepartments, $dbDepartments))
            ->map(fn($item) => trim((string)$item))
            ->filter(fn($item) => $item !== '' && $item !== '-')
            ->unique();

        foreach ($departments as $department) {
            DB::table('master_departments')->insertOrIgnore([
                'name' => $department,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Extract and Seed Units
        $dbUnits = Schema::hasTable('m_karyawan')
            ? DB::table('m_karyawan')->distinct()->pluck('unit')->filter()->toArray()
            : [];
        $units = collect($dbUnits)
            ->map(fn($item) => trim((string)$item))
            ->filter(fn($item) => $item !== '' && $item !== '-')
            ->unique();

        if ($units->isEmpty()) {
            $units = collect(['Pusat', 'Cabang']);
        }

        foreach ($units as $unit) {
            DB::table('master_units')->insertOrIgnore([
                'name' => $unit,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_units');
        Schema::dropIfExists('master_departments');
        Schema::dropIfExists('master_divisions');
        Schema::dropIfExists('master_position_titles');
    }
};
