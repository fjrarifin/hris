<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')->insertOrIgnore([
            'key' => 'staff-team-attendances',
            'label' => 'Kehadiran Tim',
            'path' => '/staff/team-attendances',
            'icon' => 'i-lucide-users',
            'allowed_levels' => '3',
            'sort_order' => 26,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'staff-team-attendances')->delete();
    }
};
