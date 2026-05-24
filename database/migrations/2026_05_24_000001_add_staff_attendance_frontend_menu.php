<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $followingMenuKeys = [
        'staff-permission',
        'staff-approvals',
        'staff-overtime',
        'staff-guide',
    ];

    public function up(): void
    {
        DB::table('frontend_menus')
            ->whereIn('key', $this->followingMenuKeys)
            ->increment('sort_order');

        DB::table('frontend_menus')->insertOrIgnore([
            'key' => 'staff-attendance',
            'label' => 'Absensi Saya',
            'path' => '/staff/attendance',
            'icon' => 'i-lucide-calendar-clock',
            'allowed_levels' => '3',
            'sort_order' => 22,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'staff-attendance')->delete();

        DB::table('frontend_menus')
            ->whereIn('key', $this->followingMenuKeys)
            ->decrement('sort_order');
    }
};
