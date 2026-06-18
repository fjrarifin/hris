<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'it-active-sessions'],
            [
                'label' => 'Sesi Login Aktif',
                'path' => '/it/active-sessions',
                'icon' => 'i-lucide-monitor-dot',
                'allowed_levels' => '0',
                'sort_order' => 94,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'it-active-sessions')->delete();
    }
};
