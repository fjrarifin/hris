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
            'key' => 'hr-internal-dashboard',
            'label' => 'Dashboard Internal',
            'path' => '/hr/internal-dashboard',
            'icon' => 'i-lucide-shield-check',
            'allowed_levels' => '2',
            'sort_order' => 34,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')->where('key', 'hr-internal-dashboard')->delete();
        }
    }
};
