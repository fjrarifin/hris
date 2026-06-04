<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('level')->index();
            }
        });

        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'user-management'],
            [
                'label' => 'Kelola User',
                'path' => '/it/users',
                'icon' => 'i-lucide-user-cog',
                'allowed_levels' => '0',
                'sort_order' => 92,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'user-management')->delete();

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
