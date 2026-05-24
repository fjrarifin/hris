<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('path');
            $table->string('icon')->nullable();
            $table->string('allowed_levels')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('frontend_menu_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frontend_menu_id')->constrained('frontend_menus')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_allowed');
            $table->timestamps();

            $table->unique(['frontend_menu_id', 'user_id'], 'frontend_menu_user_unique');
        });

        $now = now();

        DB::table('frontend_menus')->insert([
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'path' => '/dashboard',
                'icon' => null,
                'allowed_levels' => '0,1,2,3',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'employees',
                'label' => 'Karyawan',
                'path' => '/employees',
                'icon' => null,
                'allowed_levels' => '0,1,2',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll',
                'path' => '/payroll',
                'icon' => null,
                'allowed_levels' => '1,2',
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'attendance',
                'label' => 'Absensi',
                'path' => '/attendance',
                'icon' => null,
                'allowed_levels' => '1,2',
                'sort_order' => 40,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'menu-access',
                'label' => 'Akses Menu',
                'path' => '/access/menus',
                'icon' => null,
                'allowed_levels' => '0',
                'sort_order' => 90,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_menu_user_access');
        Schema::dropIfExists('frontend_menus');
    }
};
