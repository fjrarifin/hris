<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('frontend_menus')) {
            return;
        }

        DB::table('frontend_menus')->insertOrIgnore([
            'key' => 'hr-attendance-minimum',
            'label' => 'Monitoring Minimum',
            'path' => '/hr/attendance/minimum-monitoring',
            'icon' => 'i-lucide-gauge',
            'allowed_levels' => '2',
            'sort_order' => 42,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('frontend_menus')) {
            return;
        }

        DB::table('frontend_menus')->where('key', 'hr-attendance-minimum')->delete();
    }
};
