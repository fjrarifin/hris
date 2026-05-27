<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')->where('key', 'staff-guide')->update(['sort_order' => 26]);

        DB::table('frontend_menus')->insertOrIgnore([
            'key' => 'staff-team-schedules',
            'label' => 'Jadwal Tim',
            'path' => '/staff/team-schedules',
            'icon' => 'i-lucide-calendar-range',
            'allowed_levels' => '3',
            'sort_order' => 25,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'staff-team-schedules')->delete();
        DB::table('frontend_menus')->where('key', 'staff-guide')->update(['sort_order' => 25]);
    }
};
