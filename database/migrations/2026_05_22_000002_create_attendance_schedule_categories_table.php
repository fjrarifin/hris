<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_schedule_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('type', 30)->default('work');
            $table->boolean('is_workday')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::table('attendance_schedule_categories')->insert($this->defaultCategories());
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_schedule_categories');
    }

    private function defaultCategories(): array
    {
        $now = now();

        return collect([
            ['code' => 'P0', 'name' => 'Pagi 0', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'P1', 'name' => 'Pagi 1', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'P2', 'name' => 'Pagi 2', 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'P3', 'name' => 'Pagi 3', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'M0', 'name' => 'Middle 0', 'start_time' => '10:00:00', 'end_time' => '18:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'M1', 'name' => 'Middle 1', 'start_time' => '11:00:00', 'end_time' => '19:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'M2', 'name' => 'Middle 2', 'start_time' => '12:00:00', 'end_time' => '20:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'M3', 'name' => 'Middle 3', 'start_time' => '13:00:00', 'end_time' => '21:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'S1', 'name' => 'Siang 1', 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'type' => 'work', 'is_workday' => true],
            ['code' => 'O', 'name' => 'Libur', 'start_time' => null, 'end_time' => null, 'type' => 'off', 'is_workday' => false],
            ['code' => 'C', 'name' => 'Cuti', 'start_time' => null, 'end_time' => null, 'type' => 'leave', 'is_workday' => false],
            ['code' => 'PH', 'name' => 'Public Holiday / Libur Nasional', 'start_time' => null, 'end_time' => null, 'type' => 'public_holiday', 'is_workday' => false],
        ])->map(fn (array $row) => $row + [
            'is_active' => true,
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
    }
};
